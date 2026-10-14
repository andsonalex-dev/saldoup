<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Memberships\Permissions;
use Illuminate\Support\Facades\DB;

class Cards
{
    public function writable(User $user, Workspace $workspace)
    {
        return $this->query($user, $workspace)->where(function ($query) use ($user): void {
            $query->where('visibility', 'shared')->orWhere('created_by', $user->id)->orWhereExists(function ($query) use ($user): void {
                $query->selectRaw('1')->from('card_access')->whereColumn('card_access.card_id', 'credit_cards.id')->where('user_id', $user->id)->where('can_manage', true);
            });
        });
    }

    public function query(User $u, Workspace $w)
    {
        app(Permissions::class)->authorize($u, $w, 'cards.view');

        return CreditCard::where('workspace_id', $w->id)->where(function ($q) use ($u) {
            $q->where('visibility', 'shared')->orWhere('created_by', $u->id)->orWhereExists(fn ($q) => $q->selectRaw('1')->from('card_access')->whereColumn('card_access.card_id', 'credit_cards.id')->where('card_access.user_id', $u->id));
        });
    }

    public function card(User $u, Workspace $w, int $id, bool $write = false): CreditCard
    {
        $c = $this->query($u, $w)->findOrFail($id);
        if ($write && $c->visibility === 'private' && $c->created_by !== $u->id) {
            $authorization = DB::table('card_access')->where('card_id', $id)->where('user_id', $u->id)->where('can_manage', true);
            if (DB::transactionLevel() > 0) {
                $authorization->lockForUpdate();
            }
            abort_unless($authorization->first(), 403);
        }

        return $c;
    }

    public function purchase(User $u, Workspace $w, int $cardId, array $data): void
    {
        DB::transaction(function () use ($u, $w, $cardId, $data) {
            app(Permissions::class)->authorize($u, $w, 'cards.purchase');
            $card = $this->card($u, $w, $cardId, true);
            CreditCard::whereKey($cardId)->lockForUpdate()->firstOrFail();
            $amount = Money::normalize($data['amount'], true);
            FinancialDates::assertActual($data['date'], $w);
            $old = DB::table('card_purchases')->where('workspace_id', $w->id)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($old) {
                abort_unless($old->created_by === $u->id && $old->card_id === $cardId && $old->amount === $amount && $old->installments === (int) $data['installments'] && $old->date === $data['date'] && $old->description === $data['description'], 422);

                return;
            }
            $purchase = DB::table('card_purchases')->insertGetId(['workspace_id' => $w->id, 'card_id' => $cardId, 'created_by' => $u->id, 'description' => $data['description'], 'amount' => $amount, 'installments' => $data['installments'], 'date' => $data['date'], 'idempotency_key' => $data['idempotency_key'], 'created_at' => now(), 'updated_at' => now()]);
            $schedule = app(CardSchedule::class);
            foreach ($schedule->installments($amount, (int) $data['installments']) as $index => $part) {
                [$close,$due] = $schedule->dates($data['date'], $card->closing_day, $card->due_day, $card->due_month_offset, $card->purchase_on_closing_next, $index);
                abort_if($close > '9999-12-31' || $due > '9999-12-31', 422, 'As datas da fatura excedem o intervalo suportado.');
                $statement = DB::table('card_statements')->where('card_id', $cardId)->where('closing_date', $close)->lockForUpdate()->first();
                $id = $statement?->id ?? DB::table('card_statements')->insertGetId(['workspace_id' => $w->id, 'card_id' => $cardId, 'closing_date' => $close, 'due_date' => $due, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('card_installments')->insert(['workspace_id' => $w->id, 'card_id' => $cardId, 'purchase_id' => $purchase, 'statement_id' => $id, 'number' => $index + 1, 'amount' => $part, 'created_at' => now(), 'updated_at' => now()]);
            }
            app(Ledger::class)->audit($u, $w->id, 'card_purchase', $purchase, 'created', ['amount', 'installments', 'date', 'card_id']);
        }, 3);
    }

    public function totals(int $workspace, int $statement, bool $lock = false): array
    {
        $parts = DB::table('card_installments')->where('workspace_id', $workspace)->where('statement_id', $statement);
        $payments = FinancialTransaction::where('workspace_id', $workspace)->where('statement_id', $statement)->where('type', 'card_payment')->where('status', 'posted');
        if ($lock) {
            $parts->lockForUpdate();
            $payments->lockForUpdate();
        }
        $total = '0.00';
        $paid = '0.00';
        foreach ($parts->get() as $part) {
            $total = bcadd($total, $part->amount, 2);
        }foreach ($payments->get() as $payment) {
            $paid = bcadd($paid, $payment->amount, 2);
        }

        return ['total' => $total, 'paid' => $paid, 'remaining' => bcsub($total, $paid, 2)];
    }

    public function pay(User $u, Workspace $w, int $statement, array $data): void
    {
        DB::transaction(function () use ($u, $w, $statement, $data) {
            app(Permissions::class)->authorize($u, $w, 'cards.pay');
            $s = DB::table('card_statements')->where('workspace_id', $w->id)->where('id', $statement)->firstOrFail();
            $card = $this->card($u, $w, $s->card_id, true);
            CreditCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            DB::table('card_statements')->where('id', $statement)->lockForUpdate()->first();
            app(Permissions::class)->authorize($u, $w, 'transactions.create');
            $a = app(Ledger::class)->account($u, $w, (int) $data['account_id'], true);
            Account::whereKey($a->id)->lockForUpdate()->first();
            abort_unless($a->visibility === $card->visibility && ($a->visibility === 'shared' || $a->created_by === $card->created_by), 422, 'Cartão e conta devem ter privacidade compatível.');
            if ($a->visibility === 'private') {
                $accountAccess = DB::table('account_access')->where('account_id', $a->id)->orderBy('user_id')->get(['user_id', 'can_manage'])->toJson();
                $cardAccess = DB::table('card_access')->where('card_id', $card->id)->orderBy('user_id')->get(['user_id', 'can_manage'])->toJson();
                abort_unless($accountAccess === $cardAccess, 422, 'As autorizações privadas da conta e do cartão devem ser iguais.');
            }
            $amount = Money::normalize($data['amount'], true);
            FinancialDates::assertActual($data['date'], $w);
            $key = 'card:payment:'.$data['idempotency_key'];
            $old = FinancialTransaction::where('workspace_id', $w->id)->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($old) {
                abort_unless($old->created_by === $u->id && $old->statement_id === $statement && $old->amount === $amount && $old->account_id === $a->id && $old->date->format('Y-m-d') === $data['date'], 422);

                return;
            }
            abort_if(bccomp($amount, $this->totals($w->id, $statement, true)['remaining'], 2) > 0, 422, 'O pagamento não pode exceder o saldo da fatura.');
            $tx = FinancialTransaction::create(['workspace_id' => $w->id, 'account_id' => $a->id, 'created_by' => $u->id, 'type' => 'card_payment', 'status' => 'posted', 'description' => 'Pagamento de fatura', 'amount' => $amount, 'date' => $data['date'], 'statement_id' => $statement, 'idempotency_key' => $key]);
            app(Ledger::class)->audit($u, $w->id, 'transaction', $tx->id, 'card_payment', ['amount', 'statement_id', 'account_id', 'date']);
        }, 3);
    }
}
