<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvite;
use App\Services\Finance\Ledger;
use App\Services\Memberships\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministrationFinanceTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'uma-senha-segura-local-123';

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-14 12:00:00 UTC'));
        $this->owner = User::factory()->create(['password' => self::PASSWORD]);
        $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Família', 'timezone' => 'America/Sao_Paulo']);
        $this->asUser($this->owner)->withSession(['active_workspace_id' => $this->workspace->id]);
    }

    private function asUser(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user)->withSession(['active_workspace_id' => $this->workspace->id]);
    }

    private function account(string $name = 'Conta', string $visibility = 'shared', ?User $creator = null, string $balance = '100.00'): Account
    {
        return Account::create(['workspace_id' => $this->workspace->id, 'created_by' => ($creator ?? $this->owner)->id, 'name' => $name, 'type' => 'checking', 'visibility' => $visibility, 'opening_balance' => $balance]);
    }

    private function member(array $permissions = [], string $role = 'member'): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $this->workspace->memberships()->create(['user_id' => $user->id, 'role' => $role, 'permissions' => $permissions, 'status' => 'active']);

        return $user;
    }

    private function payload(Account $a, array $extra = []): array
    {
        return $extra + ['account_id' => $a->id, 'type' => 'expense', 'status' => 'posted', 'description' => 'Mercado', 'amount' => '10,25', 'date' => '2026-10-14', 'idempotency_key' => (string) Str::uuid()];
    }

    public function test_platform_panel_is_restricted_and_admin_does_not_get_financial_access(): void
    {
        $this->get(route('admin.users.index'))->assertForbidden();
        $admin = User::factory()->create(['password' => self::PASSWORD]);
        $admin->forceFill(['is_platform_admin' => true])->save();
        $this->asUser($admin)->get(route('admin.users.index'))->assertOk();
        $this->withSession(['active_workspace_id' => $this->workspace->id])->get(route('finance.index'))->assertStatus(409);
        $this->get(route('members.index', $this->workspace))->assertNotFound();
    }

    public function test_registration_cannot_assign_platform_privileges(): void
    {
        auth()->logout();
        $this->post(route('register'), ['name' => 'Intruso', 'email' => 'intruso@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'is_platform_admin' => true, 'is_active' => false])->assertRedirect();
        $this->assertFalse(User::where('email', 'intruso@example.com')->sole()->is_platform_admin);
    }

    public function test_blocking_requires_reauthentication_and_blocks_existing_session(): void
    {
        $admin = User::factory()->create(['password' => self::PASSWORD]);
        $admin->forceFill(['is_platform_admin' => true])->save();
        $this->asUser($admin)->patch(route('admin.users.update', $this->owner), ['is_active' => false, 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->patch(route('admin.users.update', $this->owner), ['is_active' => false, 'current_password' => self::PASSWORD])->assertRedirect();
        $this->asUser($this->owner->fresh())->get(route('dashboard'))->assertForbidden();
        $this->assertGuest();
        $this->post(route('login'), ['email' => $this->owner->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
    }

    public function test_local_admin_command_preserves_existing_users_and_is_not_available_in_production(): void
    {
        $this->artisan('saldoup:create-admin', ['email' => $this->owner->email, '--generate-password' => true])->assertFailed();
        $this->assertFalse($this->owner->fresh()->is_platform_admin);
        $this->artisan('saldoup:create-admin', ['email' => 'admin@test.local', '--generate-password' => true])->assertSuccessful();
        $this->assertTrue(User::where('email', 'admin@test.local')->sole()->is_platform_admin);
        $this->assertDatabaseCount('users', 2);
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('saldoup:create-admin', ['email' => 'prod@test.local', '--generate-password' => true])->assertFailed();
    }

    public function test_invitation_delivery_and_acceptance_are_bound_to_email_expiration_and_one_use(): void
    {
        Notification::fake();
        $recipient = User::factory()->create(['email' => 'convidado@example.com']);
        $this->post(route('members.invite', $this->workspace), ['email' => strtoupper($recipient->email), 'role' => 'member', 'permissions' => ['accounts.view'], 'current_password' => self::PASSWORD])->assertRedirect();
        Notification::assertSentOnDemand(WorkspaceInvite::class, function ($notification, $channels, $notifiable) use (&$token) {
            $token = basename(parse_url($notification->toMail($notifiable)->actionUrl, PHP_URL_PATH));

            return $notifiable->routes['mail'] === 'convidado@example.com';
        });
        $invitation = WorkspaceInvitation::sole();
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->get(route('invitations.show', $token))->assertForbidden();
        $this->asUser($recipient)->get(route('invitations.show', $token))->assertOk();
        $this->post(route('invitations.accept', $token))->assertRedirect(route('dashboard'));
        $this->assertSame($this->workspace->id, session('active_workspace_id'));
        $this->post(route('invitations.accept', $token))->assertStatus(410);
        $this->assertDatabaseCount('workspace_members', 2);
    }

    public function test_expired_cancelled_and_inviter_revoked_invitations_are_not_accepted(): void
    {
        $recipient = User::factory()->create();
        $token = Str::random(64);
        $i = WorkspaceInvitation::create(['workspace_id' => $this->workspace->id, 'email' => $recipient->email, 'token_hash' => hash('sha256', $token), 'role' => 'member', 'permissions' => [], 'invited_by' => $this->owner->id, 'expires_at' => now()->subMinute()]);
        $this->asUser($recipient)->post(route('invitations.accept', $token))->assertStatus(410);
        $i->update(['expires_at' => now()->addDay(), 'cancelled_at' => now()]);
        $this->post(route('invitations.accept', $token))->assertStatus(410);
        $i->update(['cancelled_at' => null]);
        $this->workspace->memberships()->where('user_id', $this->owner->id)->update(['status' => 'inactive']);
        $this->post(route('invitations.accept', $token))->assertStatus(410);
    }

    public function test_permissions_panel_prevents_delegated_escalation_and_owner_removal(): void
    {
        $admin = $this->member(['members.manage', 'accounts.view'], 'administrator');
        $member = $this->member(['accounts.view']);
        $membership = $this->workspace->memberships()->where('user_id', $member->id)->sole();
        $this->asUser($admin)->patch(route('members.update', [$this->workspace, $membership]), ['role' => 'administrator', 'permissions' => ['members.manage'], 'current_password' => self::PASSWORD])->assertForbidden();
        $this->patch(route('members.update', [$this->workspace, $membership]), ['role' => 'member', 'permissions' => ['accounts.create'], 'current_password' => self::PASSWORD])->assertForbidden();
        $ownerMembership = $this->workspace->memberships()->where('user_id', $this->owner->id)->sole();
        $this->delete(route('members.remove', [$this->workspace, $ownerMembership]), ['current_password' => self::PASSWORD])->assertForbidden();
        $this->asUser($this->owner)->patch(route('members.update', [$this->workspace, $membership]), ['role' => 'reader', 'permissions' => ['accounts.view', 'accounts.create'], 'current_password' => self::PASSWORD])->assertRedirect();
        $this->assertSame(['accounts.view'], $membership->fresh()->permissions);
    }

    public function test_private_accounts_are_hidden_even_from_workspace_owner_until_explicit_grant(): void
    {
        $creator = $this->member(['accounts.view', 'accounts.create', 'transactions.create', 'transactions.view']);
        $private = $this->account('Segredo privado', 'private', $creator);
        $this->get(route('finance.index'))->assertOk()->assertDontSee('Segredo privado');
        $this->post(route('finance.transactions.store'), $this->payload($private))->assertNotFound();
        $this->post(route('finance.accounts.grant', $private), ['user_id' => $this->owner->id, 'can_manage' => true, 'current_password' => self::PASSWORD])->assertNotFound();
        $this->asUser($creator)->post(route('finance.accounts.grant', $private), ['user_id' => $this->owner->id, 'can_manage' => false, 'current_password' => self::PASSWORD])->assertRedirect();
        $this->asUser($this->owner)->get(route('finance.index'))->assertOk()->assertSee('Segredo privado');
        $this->post(route('finance.transactions.store'), $this->payload($private))->assertForbidden();
        $this->asUser($creator)->delete(route('finance.accounts.revoke', [$private, $this->owner]), ['current_password' => self::PASSWORD])->assertRedirect();
        $this->asUser($this->owner)->get(route('finance.index'))->assertDontSee('Segredo privado');
    }

    public function test_reader_and_revoked_members_cannot_mutate_or_read_finances(): void
    {
        $a = $this->account();
        $reader = $this->member(['accounts.view', 'accounts.create', 'transactions.create'], 'reader');
        $this->asUser($reader)->get(route('finance.index'))->assertOk();
        $this->post(route('finance.transactions.store'), $this->payload($a))->assertForbidden();
        $this->workspace->memberships()->where('user_id', $reader->id)->update(['status' => 'inactive']);
        $this->get(route('finance.index'))->assertStatus(409);
    }

    public function test_planned_posted_cancelled_and_reversed_transactions_preserve_exact_balances_and_history(): void
    {
        $a = $this->account(balance: '100.00');
        $data = $this->payload($a, ['status' => 'planned']);
        $this->post(route('finance.transactions.store'), $data)->assertRedirect();
        $ledger = app(Ledger::class);
        $this->assertSame('100.00', $ledger->balance($a));
        $this->assertSame('89.75', $ledger->balance($a, true));
        $tx = FinancialTransaction::sole();
        $this->post(route('finance.transactions.status', $tx), ['action' => 'post'])->assertRedirect();
        $this->assertSame('89.75', $ledger->balance($a));
        $this->post(route('finance.transactions.status', $tx), ['action' => 'reverse'])->assertRedirect();
        $this->post(route('finance.transactions.status', $tx), ['action' => 'reverse'])->assertRedirect();
        $this->assertSame('100.00', $ledger->balance($a));
        $this->assertDatabaseCount('transactions', 2);
        $this->assertSame('posted', $tx->fresh()->status);
        $planned = app(Ledger::class)->transaction($this->owner, $this->workspace, $this->payload($a, ['status' => 'planned']));
        $this->post(route('finance.transactions.status', $planned), ['action' => 'cancel'])->assertRedirect();
        $this->assertSame('100.00', $ledger->balance($a, true));
    }

    public function test_transaction_retry_is_idempotent_and_conflicting_payload_is_rejected(): void
    {
        $a = $this->account();
        $data = $this->payload($a);
        $this->post(route('finance.transactions.store'), $data)->assertRedirect();
        $this->post(route('finance.transactions.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('transactions', 1);
        $data['amount'] = '999.99';
        $this->post(route('finance.transactions.store'), $data)->assertSessionHasErrors('idempotency_key');
        $this->assertSame('89.75', app(Ledger::class)->balance($a));
    }

    public function test_foreign_workspace_accounts_and_categories_cannot_be_used(): void
    {
        $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Outro', 'timezone' => 'UTC']);
        $a = Account::create(['workspace_id' => $other->id, 'created_by' => $other->owner_id, 'name' => 'Alheia', 'type' => 'cash', 'visibility' => 'shared', 'opening_balance' => '0.00']);
        $this->post(route('finance.transactions.store'), $this->payload($a))->assertNotFound();
        $category = DB::table('categories')->insertGetId(['workspace_id' => $other->id, 'name' => 'Outra', 'created_at' => now(), 'updated_at' => now()]);
        $own = $this->account();
        $this->post(route('finance.transactions.store'), $this->payload($own, ['category_id' => $category]))->assertNotFound();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transfers_are_atomic_idempotent_and_do_not_change_aggregate_assets(): void
    {
        $from = $this->account('Origem', balance: '200.00');
        $to = $this->account('Destino', balance: '50.00');
        $data = ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => '100.00', 'date' => '2026-10-14', 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('finance.transfers.store'), $data)->assertRedirect();
        $this->post(route('finance.transfers.store'), $data)->assertRedirect();
        $l = app(Ledger::class);
        $this->assertSame('100.00', $l->balance($from));
        $this->assertSame('150.00', $l->balance($to));
        $this->assertSame('250.00', bcadd($l->balance($from), $l->balance($to), 2));
        $this->assertDatabaseCount('transfers', 1);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_transfers_reject_inconsistent_privacy_without_partial_movements(): void
    {
        $from = $this->account('Compartilhada');
        $to = $this->account('Privada', 'private');
        $this->post(route('finance.transfers.store'), ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => '10.00', 'date' => '2026-10-14', 'idempotency_key' => (string) Str::uuid()])->assertStatus(422);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_account_creation_ignores_forged_ownership_and_money_precision_is_enforced(): void
    {
        $this->post(route('finance.accounts.store'), ['name' => 'Carteira', 'type' => 'cash', 'visibility' => 'private', 'opening_balance' => '10,23', 'workspace_id' => 999, 'created_by' => 999])->assertRedirect();
        $a = Account::sole();
        $this->assertSame($this->workspace->id, $a->workspace_id);
        $this->assertSame($this->owner->id, $a->created_by);
        $this->assertSame('10.23', $a->opening_balance);
        $this->post(route('finance.transactions.store'), $this->payload($a, ['amount' => '0.001']))->assertSessionHasErrors('amount');
        $this->post(route('finance.transactions.store'), $this->payload($a, ['amount' => '-1.00']))->assertSessionHasErrors('amount');
        $this->get(route('finance.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_transfer_rolls_back_both_sides_when_audit_fails(): void
    {
        $from = $this->account('Origem');
        $to = $this->account('Destino');
        AuditLog::creating(function (): void {
            throw new \RuntimeException('Falha simulada');
        });
        try {
            app(Ledger::class)->transfer($this->owner, $this->workspace, ['from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => '100.00', 'date' => '2026-10-14', 'idempotency_key' => (string) Str::uuid()]);
            $this->fail('Era esperado rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha simulada', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transfers', 0);
        $this->assertSame('100.00', app(Ledger::class)->balance($from));
        $this->assertSame('100.00', app(Ledger::class)->balance($to));
    }

    public function test_manual_balance_adjustment_preserves_history_is_idempotent_and_not_income(): void
    {
        $account = $this->account('Investimentos', 'private', balance: '100.00');
        $account->update(['type' => 'investment']);
        $data = ['target_balance' => '120.00', 'current_password' => self::PASSWORD, 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('finance.accounts.adjust', $account), $data)->assertRedirect();
        $this->assertSame('120.00', app(Ledger::class)->balance($account));
        app(Ledger::class)->transaction($this->owner, $this->workspace, $this->payload($account, ['amount' => '5.00']));
        $this->post(route('finance.accounts.adjust', $account), $data)->assertRedirect();
        $this->assertSame('115.00', app(Ledger::class)->balance($account));
        $data['target_balance'] = '110.00';
        $data['idempotency_key'] = (string) Str::uuid();
        $this->post(route('finance.accounts.adjust', $account), $data)->assertRedirect();
        $this->assertSame('110.00', app(Ledger::class)->balance($account));
        $this->assertSame('100.00', $account->fresh()->opening_balance);
        $this->assertDatabaseCount('transactions', 3);
        $this->assertDatabaseCount('balance_adjustments', 2);
        $this->assertSame(0, FinancialTransaction::where('type', 'income')->count());
        $this->get(route('finance.index'))->assertOk()->assertSee('Ajuste manual de saldo');
    }

    public function test_ownership_transfer_requires_confirmation_and_preserves_private_access_boundaries(): void
    {
        $target = $this->member(['accounts.view'], 'reader');
        $private = $this->account('Conta privada original', 'private');
        $this->get(route('members.index', $this->workspace))->assertOk()->assertSee('Transferir propriedade');
        $data = ['user_id' => $target->id, 'current_password' => self::PASSWORD, 'former_owner_permissions' => ['accounts.view', 'transactions.view', 'cards.view']];
        $this->post(route('members.transfer-ownership', $this->workspace), $data)->assertSessionHasErrors('confirm_transfer');
        $this->post(route('members.transfer-ownership', $this->workspace), $data + ['confirm_transfer' => '1'])->assertRedirect(route('dashboard'));
        $this->assertSame($target->id, $this->workspace->fresh()->owner_id);
        $this->assertSame('member', $this->workspace->memberships()->where('user_id', $this->owner->id)->sole()->role->value);
        $this->assertSame('owner', $this->workspace->memberships()->where('user_id', $target->id)->sole()->role->value);
        $this->assertSame($this->owner->id, $private->fresh()->created_by);
        $this->asUser($target)->get(route('finance.index'))->assertOk()->assertDontSee('Conta privada original');
        $this->post(route('finance.transactions.store'), $this->payload($private))->assertNotFound();
        $this->asUser($this->owner)->get(route('finance.index'))->assertOk()->assertSee('Conta privada original');
        $this->get(route('members.index', $this->workspace))->assertForbidden();
        $this->assertSame(3, DB::table('audit_logs')->whereIn('action', ['ownership_transferred', 'ownership_relinquished', 'ownership_received'])->count());
    }

    public function test_ownership_cannot_be_transferred_to_outsiders_or_by_non_owners(): void
    {
        $outsider = User::factory()->create();
        $this->post(route('members.transfer-ownership', $this->workspace), ['user_id' => $outsider->id, 'current_password' => self::PASSWORD, 'confirm_transfer' => '1'])->assertNotFound();
        $admin = $this->member(['members.manage'], 'administrator');
        $this->asUser($admin)->post(route('members.transfer-ownership', $this->workspace), ['user_id' => $outsider->id, 'current_password' => self::PASSWORD, 'confirm_transfer' => '1'])->assertForbidden();
        $this->assertSame($this->owner->id, $this->workspace->fresh()->owner_id);
    }

    public function test_platform_roles_require_reauthentication_and_self_lockout_is_prevented(): void
    {
        $admin = User::factory()->create(['password' => self::PASSWORD]);
        $admin->forceFill(['is_platform_admin' => true])->save();
        $target = User::factory()->create();
        $this->asUser($admin)->patch(route('admin.users.update', $target), ['is_active' => true, 'is_platform_admin' => true, 'current_password' => self::PASSWORD])->assertRedirect();
        $this->assertTrue($target->fresh()->is_platform_admin);
        $this->patch(route('admin.users.update', $admin), ['is_active' => false, 'is_platform_admin' => false, 'current_password' => self::PASSWORD])->assertForbidden();
        $this->assertTrue($admin->fresh()->is_platform_admin);
        $this->assertTrue($admin->fresh()->is_active);
        $this->patch(route('admin.users.update', $target), ['is_active' => true, 'is_platform_admin' => false, 'current_password' => self::PASSWORD])->assertRedirect();
        $this->asUser($target->fresh())->get(route('admin.users.index'))->assertForbidden();
    }
}
