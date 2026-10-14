<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Memberships\Permissions;
use Illuminate\Support\Facades\DB;

class BalanceAdjustments
{
    public function set(User $u, Workspace $w, int $id, array $data): void
    {
        DB::transaction(function () use ($u, $w, $id, $data) {
            app(Permissions::class)->authorize($u, $w, 'accounts.adjust');
            $ledger = app(Ledger::class);
            $ledger->account($u, $w, $id, true);
            $account = Account::where('workspace_id', $w->id)->lockForUpdate()->findOrFail($id);
            $target = Money::normalize($data['target_balance']);
            abort_if(bccomp($target, '0', 2) < 0, 422, 'Saldos informados negativos ainda não estão disponíveis.');
            $old = DB::table('balance_adjustments')->where('workspace_id', $w->id)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($old) {
                abort_unless($old->account_id === $id && $old->created_by === $u->id && $old->target_balance === $target, 422);

                return;
            }
            $balance = $account->opening_balance;
            foreach (FinancialTransaction::where('workspace_id', $w->id)->where('account_id', $id)->where('status', 'posted')->lockForUpdate()->get() as $tx) {
                $balance = in_array($tx->type, ['income', 'transfer_in', 'adjustment_in'], true) ? bcadd($balance, $tx->amount, 2) : bcsub($balance, $tx->amount, 2);
            }
            $delta = bcsub($target, $balance, 2);
            $adjustment = DB::table('balance_adjustments')->insertGetId(['workspace_id' => $w->id, 'account_id' => $id, 'created_by' => $u->id, 'target_balance' => $target, 'idempotency_key' => $data['idempotency_key'], 'created_at' => now(), 'updated_at' => now()]);
            if (bccomp($delta, '0', 2) !== 0) {
                FinancialTransaction::create(['workspace_id' => $w->id, 'account_id' => $id, 'created_by' => $u->id, 'type' => bccomp($delta, '0', 2) > 0 ? 'adjustment_in' : 'adjustment_out', 'status' => 'posted', 'description' => 'Ajuste manual de saldo', 'amount' => bccomp($delta, '0', 2) > 0 ? $delta : bcsub('0', $delta, 2), 'date' => now($w->timezone)->toDateString(), 'idempotency_key' => 'adjustment:'.$data['idempotency_key']]);
            }
            $ledger->audit($u,$w->id,'balance_adjustment',$adjustment,'created',['target_balance', 'account_id']);
        }, 3);
    }
}
