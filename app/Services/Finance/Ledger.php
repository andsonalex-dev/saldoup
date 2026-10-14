<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Memberships\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ledger
{
    private array $balanceCache = [];

    public function preloadBalances(iterable $accounts): void
    {
        $accounts = collect($accounts);
        $sums = FinancialTransaction::whereIn('account_id', $accounts->pluck('id'))
            ->whereIn('workspace_id', $accounts->pluck('workspace_id')->unique())
            ->whereIn('status', ['posted', 'planned'])
            ->selectRaw("account_id, status, SUM(CASE WHEN type IN ('income','transfer_in','adjustment_in') THEN amount ELSE -amount END) AS total")
            ->groupBy('account_id', 'status')->get()->groupBy('account_id');
        foreach ($accounts as $account) {
            $posted = '0.00';
            $planned = '0.00';
            foreach ($sums->get($account->id, collect()) as $sum) {
                if ($sum->status === 'posted') {
                    $posted = (string) $sum->total;
                } else {
                    $planned = (string) $sum->total;
                }
            }
            $this->balanceCache[$account->id.':0'] = bcadd($account->opening_balance, $posted, 2);
            $this->balanceCache[$account->id.':1'] = bcadd($this->balanceCache[$account->id.':0'], $planned, 2);
        }
    }

    public function accounts(User $user, Workspace $workspace): Builder
    {
        app(Permissions::class)->authorize($user, $workspace, 'accounts.view');

        return Account::where('workspace_id', $workspace->id)->where(function ($q) use ($user) {
            $q->where('visibility', 'shared')->orWhere('created_by', $user->id)->orWhereExists(function ($q) use ($user) {
                $q->selectRaw('1')->from('account_access')->whereColumn('account_access.account_id', 'accounts.id')->where('account_access.user_id', $user->id);
            });
        });
    }

    public function account(User $user, Workspace $workspace, int $id, bool $write = false): Account
    {
        $a = $this->accounts($user, $workspace)->findOrFail($id);
        if ($write && $a->visibility === 'private' && $a->created_by !== $user->id) {
            $authorization = DB::table('account_access')->where('account_id', $a->id)->where('user_id', $user->id)->where('can_manage', true);
            if (DB::transactionLevel() > 0) {
                $authorization->lockForUpdate();
            }
            abort_unless($authorization->first(), 403);
        }

        return $a;
    }

    public function writable(User $user, Workspace $workspace): Builder
    {
        return $this->accounts($user, $workspace)->where(function ($query) use ($user): void {
            $query->where('visibility', 'shared')->orWhere('created_by', $user->id)->orWhereExists(function ($query) use ($user): void {
                $query->selectRaw('1')->from('account_access')->whereColumn('account_access.account_id', 'accounts.id')->where('user_id', $user->id)->where('can_manage', true);
            });
        });
    }

    public function balance(Account $account, bool $projected = false): string
    {
        if (isset($this->balanceCache[$account->id.':'.(int) $projected])) {
            return $this->balanceCache[$account->id.':'.(int) $projected];
        }
        $q = FinancialTransaction::where('account_id', $account->id)->where('workspace_id', $account->workspace_id)->whereIn('status', $projected ? ['posted', 'planned'] : ['posted']);
        $sum = (string) ($q->selectRaw("COALESCE(SUM(CASE WHEN type IN ('income','transfer_in','adjustment_in') THEN amount ELSE -amount END),0) AS balance")->value('balance') ?? '0');

        return bcadd($account->opening_balance, $sum, 2);
    }

    public function audit(User $user, int $workspace, string $entity, int $id, string $action, array $fields): void
    {
        AuditLog::create(['workspace_id' => $workspace, 'actor_id' => $user->id, 'entity_type' => $entity, 'entity_id' => $id, 'action' => $action, 'changed_fields' => $fields, 'created_at' => now()]);
    }

    public function transaction(User $user, Workspace $workspace, array $data): FinancialTransaction
    {
        return DB::transaction(function () use ($user, $workspace, $data) {
            app(Permissions::class)->authorize($user, $workspace, 'transactions.create');
            $a = $this->account($user, $workspace, (int) $data['account_id'], true);
            Account::whereKey($a->id)->lockForUpdate()->firstOrFail();
            $data['amount'] = Money::normalize($data['amount'], true);
            if ($data['status'] === 'posted') FinancialDates::assertActual($data['date'], $workspace);
            if (! empty($data['category_id'])) {
                abort_unless(DB::table('categories')->where('workspace_id', $workspace->id)->where('id', $data['category_id'])->exists(), 404);
            }
            $attributes = ['workspace_id' => $workspace->id, 'account_id' => $a->id, 'created_by' => $user->id, 'type' => $data['type'], 'status' => $data['status'], 'description' => $data['description'], 'amount' => $data['amount'], 'date' => $data['date'], 'category_id' => $data['category_id'] ?? null, 'idempotency_key' => $data['idempotency_key']];
            $existing = FinancialTransaction::where('workspace_id', $workspace->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                foreach ($attributes as $key => $value) {
                    $actual = $key === 'date' ? $existing->date->format('Y-m-d') : $existing->getAttribute($key);
                    if ((string) $actual !== (string) $value) {
                        throw ValidationException::withMessages(['idempotency_key' => 'Esta operação já foi enviada com outros dados. Recarregue o formulário.']);
                    }
                }

                return $existing;
            }
            $tx = FinancialTransaction::create($attributes + ['expected_date' => $data['status'] === 'planned' ? $data['date'] : null]);
            $this->audit($user, $workspace->id, 'transaction', $tx->id, 'created', array_keys($attributes));

            return $tx;
        }, 3);
    }

    public function changeStatus(User $user, Workspace $workspace, int $id, string $action, ?string $actualDate = null): void
    {
        DB::transaction(function () use ($user, $workspace, $id, $action, $actualDate) {
            app(Permissions::class)->authorize($user, $workspace, 'transactions.reverse');
            $tx = FinancialTransaction::where('workspace_id', $workspace->id)->lockForUpdate()->findOrFail($id);
            $this->account($user, $workspace, $tx->account_id, true);
            abort_unless(in_array($tx->type, ['income', 'expense'], true), 422, 'Esta operação usa seu fluxo específico.');
            abort_if($tx->transfer_id || $tx->reversal_of || $tx->statement_id, 422, 'Esta operação exige seu fluxo específico.');
            if ($action === 'post') {
                if ($tx->status === 'posted') {
                    return;
                } abort_unless($tx->status === 'planned', 422);
                $date = $actualDate ?? now($workspace->timezone)->toDateString();
                FinancialDates::assertActual($date, $workspace);
                $tx->update(['status' => 'posted', 'date' => $date]);
                $this->audit($user, $workspace->id, 'transaction', $tx->id, 'posted', ['status', 'date']);
            } elseif ($action === 'cancel') {
                if ($tx->status === 'cancelled') {
                    return;
                } abort_unless($tx->status === 'planned', 422, 'Movimentos efetivados devem ser estornados.');
                $tx->update(['status' => 'cancelled']);
                $this->audit($user, $workspace->id, 'transaction', $tx->id, 'cancelled', ['status']);
            } else {
                abort_unless($tx->status === 'posted', 422);
                if (FinancialTransaction::where('reversal_of', $tx->id)->exists()) {
                    return;
                }
                $reverse = FinancialTransaction::create(['workspace_id' => $workspace->id, 'account_id' => $tx->account_id, 'created_by' => $user->id, 'type' => $tx->type === 'income' ? 'expense' : 'income', 'status' => 'posted', 'description' => 'Estorno do lançamento '.$tx->id, 'amount' => $tx->amount, 'date' => now($workspace->timezone)->toDateString(), 'reversal_of' => $tx->id, 'idempotency_key' => 'reversal:'.$tx->id]);
                $this->audit($user, $workspace->id, 'transaction', $reverse->id, 'reversed', ['reversal_of']);
            }
        }, 3);
    }

    public function transfer(User $user, Workspace $workspace, array $data): void
    {
        DB::transaction(function () use ($user, $workspace, $data) {
            app(Permissions::class)->authorize($user, $workspace, 'transactions.create');
            $from = $this->account($user, $workspace, (int) $data['from_account_id'], true);
            $to = $this->account($user, $workspace, (int) $data['to_account_id'], true);
            abort_if($from->id === $to->id, 422, 'Escolha contas diferentes.');
            abort_if($from->visibility !== $to->visibility || ($from->visibility === 'private' && $from->created_by !== $to->created_by), 422, 'As contas devem ter a mesma privacidade e, quando privadas, o mesmo dono.');
            if ($from->visibility === 'private') {
                $access = fn ($id) => DB::table('account_access')->where('account_id', $id)->orderBy('user_id')->get(['user_id', 'can_manage'])->toJson();
                abort_unless($access($from->id) === $access($to->id), 422, 'As autorizações privadas das duas contas devem ser iguais.');
            }
            Account::whereIn('id', [$from->id, $to->id])->orderBy('id')->lockForUpdate()->get();
            $amount = Money::normalize($data['amount'], true);
            FinancialDates::assertActual($data['date'], $workspace);
            $existing = DB::table('transfers')->where('workspace_id', $workspace->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                $legs = FinancialTransaction::where('transfer_id', $existing->id)->get();
                abort_unless($existing->created_by === $user->id && $legs->count() === 2 && $legs->where('type', 'transfer_out')->first()->account_id === $from->id && $legs->where('type', 'transfer_in')->first()->account_id === $to->id && $legs->first()->amount === $amount && $legs->first()->date->format('Y-m-d') === $data['date'], 422);

                return;
            }
            $id = DB::table('transfers')->insertGetId(['workspace_id' => $workspace->id, 'created_by' => $user->id, 'idempotency_key' => $data['idempotency_key'], 'created_at' => now(), 'updated_at' => now()]);
            foreach ([[$from, 'transfer_out'], [$to, 'transfer_in']] as [$account,$type]) {
                FinancialTransaction::create(['workspace_id' => $workspace->id, 'account_id' => $account->id, 'created_by' => $user->id, 'type' => $type, 'status' => 'posted', 'description' => 'Transferência entre contas', 'amount' => $amount, 'date' => $data['date'], 'transfer_id' => $id, 'idempotency_key' => 'transfer:'.$id.':'.$type]);
            }
            $this->audit($user, $workspace->id, 'transfer', $id, 'created', ['from_account_id', 'to_account_id', 'amount', 'date']);
        }, 3);
    }
}
