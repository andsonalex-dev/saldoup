<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Services\Finance\Cards;
use App\Services\Finance\DashboardSummary;
use App\Services\Finance\Ledger;
use App\Services\Memberships\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CardsRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    private $workspace;

    private User $owner;

    private Account $account;

    private CreditCard $card;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-14 12:00:00 UTC'));
        $this->owner = User::factory()->create(['password' => 'uma-senha-segura-123']);
        $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Família', 'timezone' => 'America/Sao_Paulo']);
        $this->account = Account::create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id, 'name' => 'Banco', 'type' => 'checking', 'visibility' => 'shared', 'opening_balance' => '1000.00']);
        $this->card = CreditCard::create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id, 'name' => 'Cartão', 'visibility' => 'shared', 'credit_limit' => '2000.00', 'closing_day' => 10, 'due_day' => 5, 'due_month_offset' => 1, 'purchase_on_closing_next' => false]);
        $this->actingAs($this->owner)->withSession(['active_workspace_id' => $this->workspace->id]);
    }

    private function purchase(array $extra = []): array
    {
        return $extra + ['description' => 'Compra parcelada', 'amount' => '100.00', 'installments' => 3, 'date' => '2026-10-10', 'idempotency_key' => (string) Str::uuid()];
    }

    public function test_purchase_is_atomic_idempotent_and_does_not_reduce_bank_balance(): void
    {
        $data = $this->purchase();
        $this->post(route('cards.purchase', $this->card), $data)->assertRedirect();
        $this->post(route('cards.purchase', $this->card), $data)->assertRedirect();
        $this->assertDatabaseCount('card_purchases', 1);
        $this->assertDatabaseCount('card_installments', 3);
        $this->assertDatabaseCount('card_statements', 3);
        $this->assertSame('100.00', (string) DB::table('card_installments')->sum('amount'));
        $this->assertSame('1000.00', app(Ledger::class)->balance($this->account));
        $this->assertDatabaseCount('transactions', 0);
        $this->get(route('cards.index'))->assertOk()->assertSee('33,34');
    }

    public function test_partial_and_full_payments_reduce_cash_and_liability_without_duplicate_consumption(): void
    {
        $this->post(route('cards.purchase', $this->card), $this->purchase(['installments' => 1]))->assertRedirect();
        $s = DB::table('card_statements')->first();
        $data = ['account_id' => $this->account->id, 'amount' => '40.00', 'date' => '2026-10-14', 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('cards.pay', $s->id), $data)->assertRedirect();
        $this->post(route('cards.pay', $s->id), $data)->assertRedirect();
        $this->assertSame('960.00', app(Ledger::class)->balance($this->account));
        $this->assertSame('60.00', app(Cards::class)->totals($this->workspace->id, $s->id)['remaining']);
        $this->assertSame(0, FinancialTransaction::where('type', 'expense')->count());
        $this->get(route('cards.index'))->assertOk()->assertSee('Paga parcialmente');
        $data['amount'] = '61.00';
        $data['idempotency_key'] = (string) Str::uuid();
        $this->post(route('cards.pay', $s->id), $data)->assertStatus(422);
        $this->assertDatabaseCount('transactions', 1);
        $data['amount'] = '60.00';
        $this->post(route('cards.pay', $s->id), $data)->assertRedirect();
        $this->assertSame('900.00', app(Ledger::class)->balance($this->account));
        $this->assertSame('0.00', app(Cards::class)->totals($this->workspace->id, $s->id)['remaining']);
        $this->post(route('finance.transactions.status', FinancialTransaction::first()), ['action' => 'reverse'])->assertStatus(422);
    }

    public function test_foreign_and_private_cards_do_not_bypass_resource_authorization(): void
    {
        $other = User::factory()->create();
        $this->workspace->memberships()->create(['user_id' => $other->id, 'role' => 'member', 'permissions' => ['cards.view', 'cards.purchase'], 'status' => 'active']);
        $this->card->update(['created_by' => $other->id, 'visibility' => 'private', 'name' => 'Privacidade do outro']);
        $this->get(route('cards.index'))->assertDontSee('Privacidade do outro');
        $this->post(route('cards.purchase', $this->card), $this->purchase())->assertNotFound();
        $foreign = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Outro', 'timezone' => 'UTC']);
        $this->card->update(['workspace_id' => $foreign->id, 'visibility' => 'shared']);
        $this->post(route('cards.purchase', $this->card), $this->purchase())->assertNotFound();
    }

    public function test_reader_cannot_purchase_or_pay_even_with_mutation_permission_flags(): void
    {
        $reader = User::factory()->create();
        $this->workspace->memberships()->create(['user_id' => $reader->id, 'role' => 'reader', 'permissions' => ['cards.view', 'cards.purchase', 'cards.pay'], 'status' => 'active']);
        $this->actingAs($reader)->post(route('cards.purchase', $this->card), $this->purchase())->assertForbidden();
        $this->assertDatabaseCount('card_purchases', 0);
    }

    public function test_statement_and_payment_privacy_must_be_consistent(): void
    {
        $this->card->update(['visibility' => 'private']);
        $this->post(route('cards.purchase', $this->card), $this->purchase(['installments' => 1]))->assertRedirect();
        $s = DB::table('card_statements')->first();
        $this->post(route('cards.pay', $s->id), ['account_id' => $this->account->id, 'amount' => '100.00', 'date' => '2026-10-14', 'idempotency_key' => (string) Str::uuid()])->assertStatus(422);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_manual_monthly_recurrence_is_idempotent_and_preserves_month_end_anchor(): void
    {
        $data = ['account_id' => $this->account->id, 'type' => 'expense', 'description' => 'Aluguel', 'amount' => '12.34', 'date' => '2027-01-31', 'until' => '2027-03-31', 'frequency' => 'monthly', 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('finance.recurrences.store'), $data)->assertRedirect();
        $this->post(route('finance.recurrences.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('transactions', 3);
        $this->assertDatabaseCount('recurrence_batches', 1);
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], DB::table('transactions')->orderBy('date')->pluck('date')->all());
        $this->assertSame('1000.00', app(Ledger::class)->balance($this->account));
        $this->assertSame('962.98', app(Ledger::class)->balance($this->account, true));
    }

    public function test_recurrence_limit_rolls_back_all_occurrences_if_batch_is_too_large(): void
    {
        $this->post(route('finance.recurrences.store'), ['account_id' => $this->account->id, 'type' => 'income', 'description' => 'Semanal', 'amount' => '10.00', 'date' => '2026-01-01', 'until' => '2030-12-31', 'frequency' => 'weekly', 'idempotency_key' => (string) Str::uuid()])->assertStatus(422);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('recurrence_batches', 0);
    }

    public function test_monthly_dashboard_excludes_private_values_and_separates_card_consumption_from_payments(): void
    {
        $private = Account::create(['workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id, 'name' => 'Privada', 'type' => 'cash', 'visibility' => 'private', 'opening_balance' => '10000.00']);
        app(Ledger::class)->transaction($this->owner, $this->workspace, ['account_id' => $private->id, 'type' => 'income', 'status' => 'posted', 'description' => 'Privado', 'amount' => '500.00', 'date' => '2026-10-14', 'idempotency_key' => (string) Str::uuid()]);
        $this->post(route('cards.purchase', $this->card), $this->purchase(['installments' => 1]));
        $statement = DB::table('card_statements')->first();
        $this->post(route('cards.pay', $statement->id), ['account_id' => $this->account->id, 'amount' => '40.00', 'date' => '2026-10-14', 'idempotency_key' => (string) Str::uuid()]);
        app(Ledger::class)->transaction($this->owner, $this->workspace, ['account_id' => $this->account->id, 'type' => 'expense', 'status' => 'planned', 'description' => 'Previsto', 'amount' => '25.00', 'date' => '2026-10-20', 'idempotency_key' => (string) Str::uuid()]);
        $summary = app(DashboardSummary::class)->forMonth($this->owner, $this->workspace, '2026-10');
        $this->assertSame('960.00', $summary['balance']);
        $this->assertSame('935.00', $summary['projected']);
        $this->assertSame('0.00', $summary['income']);
        $this->assertSame('0.00', $summary['expense']);
        $this->assertSame('40.00', $summary['cash_card_payments']);
        $this->assertSame('100.00', $summary['card_purchases']);
        $next = app(DashboardSummary::class)->forMonth($this->owner, $this->workspace, '2026-11');
        $this->assertSame('60.00', $next['card_outstanding']);
        $this->get(route('dashboard', ['month' => '2026-10']))->assertOk()->assertSee('960,00')->assertDontSee('10500,00');
    }
}
