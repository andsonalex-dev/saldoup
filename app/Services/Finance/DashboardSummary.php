<?php

namespace App\Services\Finance;

use App\Models\FinancialTransaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Memberships\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardSummary
{
    public function forMonth(User $u, Workspace $w, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, $w->timezone);
        $from = $start->toDateString();
        $to = $start->endOfMonth()->toDateString();
        $permissions = app(Permissions::class);
        $result = ['month' => $month, 'balance' => null, 'projected' => null, 'income' => null, 'expense' => null, 'expected_income' => null, 'expected_expense' => null, 'card_purchases' => null, 'card_outstanding' => null, 'cash_card_payments' => null];
        if ($permissions->allows($u, $w, 'accounts.view')) {
            $accounts = app(Ledger::class)->accounts($u, $w)->where('visibility', 'shared')->get();
            $ledger = app(Ledger::class);
            $ledger->preloadBalances($accounts);
            $balance = '0.00';
            foreach ($accounts as $a) {
                $balance = bcadd($balance, $ledger->balance($a), 2);
            }$result['balance'] = $balance;
            if ($permissions->allows($u, $w, 'transactions.view')) {
                $rows = FinancialTransaction::where('workspace_id', $w->id)->whereIn('account_id', $accounts->modelKeys())->whereBetween('date', [$from, $to])->whereIn('status', ['posted', 'planned'])->selectRaw("status, COALESCE(SUM(CASE WHEN type='income' AND reversal_of IS NULL THEN amount WHEN type='expense' AND reversal_of IS NOT NULL THEN -amount ELSE 0 END),0) AS income, COALESCE(SUM(CASE WHEN type='expense' AND reversal_of IS NULL THEN amount WHEN type='income' AND reversal_of IS NOT NULL THEN -amount ELSE 0 END),0) AS expense, COALESCE(SUM(CASE WHEN type='card_payment' THEN amount ELSE 0 END),0) AS card_payments")->groupBy('status')->get()->keyBy('status');
                $result['income'] = (string) ($rows->get('posted')?->income ?? '0.00');
                $result['expense'] = (string) ($rows->get('posted')?->expense ?? '0.00');
                $result['cash_card_payments'] = (string) ($rows->get('posted')?->card_payments ?? '0.00');
                $result['expected_income'] = (string) ($rows->get('planned')?->income ?? '0.00');
                $result['expected_expense'] = (string) ($rows->get('planned')?->expense ?? '0.00');
                $result['projected'] = bcsub(bcadd($balance, $result['expected_income'], 2), $result['expected_expense'], 2);
            }
        }
        if ($permissions->allows($u, $w, 'cards.view')) {
            $cards = app(Cards::class)->query($u, $w)->where('visibility', 'shared')->pluck('id');
            $result['card_purchases'] = (string) (DB::table('card_purchases')->where('workspace_id', $w->id)->whereIn('card_id', $cards)->whereBetween('date', [$from, $to])->sum('amount') ?? '0');
            $statements = DB::table('card_statements')->where('workspace_id', $w->id)->whereIn('card_id', $cards)->whereBetween('due_date', [$from, $to])->pluck('id');
            $due = (string) (DB::table('card_installments')->where('workspace_id', $w->id)->whereIn('statement_id', $statements)->sum('amount') ?? '0');
            $paid = (string) (FinancialTransaction::where('workspace_id', $w->id)->whereIn('statement_id', $statements)->where('status', 'posted')->where('type', 'card_payment')->sum('amount') ?? '0');
            $result['card_outstanding'] = bcsub($due,$paid,2);
        }

        return $result;
    }
}
