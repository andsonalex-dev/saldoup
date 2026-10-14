<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CreditCard;
use App\Models\FinancialTransaction;
use App\Services\Finance\Cards;
use App\Services\Finance\Ledger;
use App\Services\Finance\Money;
use App\Services\Memberships\ActiveWorkspace;
use App\Services\Memberships\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CardController extends Controller
{
    private function workspace(Request $r)
    {
        $w = app(ActiveWorkspace::class)->resolve($r);
        abort_unless($w, 409, 'Selecione um espaço financeiro.');

        return $w;
    }

    public function index(Request $r, Cards $service)
    {
        $w = $this->workspace($r);
        $allowed = app(Permissions::class)->snapshot($r->user(), $w);
        $cards = $service->query($r->user(), $w)->orderBy('name')->paginate(20, ['*'], 'cards_page');
        $statements = DB::table('card_statements')->where('workspace_id', $w->id)->whereIn('card_id', $service->query($r->user(), $w)->select('credit_cards.id'))->orderBy('due_date')->paginate(20, ['*'], 'statements_page');
        $ids = $statements->getCollection()->pluck('id');
        $names = $service->query($r->user(), $w)->whereIn('credit_cards.id', $statements->getCollection()->pluck('card_id'))->pluck('name', 'id');
        $installments = DB::table('card_installments as i')->join('card_purchases as p', 'p.id', '=', 'i.purchase_id')
            ->where('i.workspace_id', $w->id)->whereIn('i.statement_id', $ids)->orderBy('p.date')->orderBy('i.number')
            ->get(['i.statement_id', 'i.number', 'i.amount', 'p.description', 'p.installments', 'p.date'])->groupBy('statement_id');
        $totals = DB::table('card_installments')->where('workspace_id', $w->id)->whereIn('statement_id', $ids)->groupBy('statement_id')->selectRaw('statement_id, SUM(amount) AS total')->pluck('total', 'statement_id');
        $paid = FinancialTransaction::where('workspace_id', $w->id)->whereIn('statement_id', $ids)->where('status', 'posted')->where('type', 'card_payment')->groupBy('statement_id')->selectRaw('statement_id, SUM(amount) AS total')->pluck('total', 'statement_id');
        $today = now($w->timezone)->toDateString();
        foreach ($statements as $s) {
            $s->card_name = $names[$s->card_id];
            $s->total = (string) ($totals[$s->id] ?? '0.00');
            $s->paid = (string) ($paid[$s->id] ?? '0.00');
            $s->remaining = bcsub($s->total, $s->paid, 2);
            $s->status = bccomp($s->remaining, '0', 2) <= 0 ? 'Paga' : ($s->due_date < $today ? 'Vencida' : (bccomp($s->paid, '0', 2) > 0 ? 'Paga parcialmente' : ($s->closing_date < $today ? 'Fechada' : 'Aberta')));
        }
        $accounts = app(Permissions::class)->allows($r->user(), $w, 'accounts.view') ? app(Ledger::class)->writable($r->user(), $w)->orderBy('name')->get() : collect();
        $members = $w->memberships()->where('status', 'active')->with('user')->get();
        $writableCardIds = $service->writable($r->user(), $w)->whereIn('credit_cards.id', $cards->getCollection()->pluck('id')->merge($statements->getCollection()->pluck('card_id')))->pluck('id')->all();
        $grants = DB::table('card_access')->where('card_access.workspace_id', $w->id)->whereIn('card_id', $cards->getCollection()->where('created_by', $r->user()->id)->pluck('id'))->join('users', 'users.id', '=', 'card_access.user_id')->get(['card_access.card_id', 'users.name', 'users.id'])->groupBy('card_id');

        return view('finance.cards', compact('cards', 'statements', 'accounts', 'w', 'members', 'grants', 'installments', 'allowed', 'writableCardIds'));
    }

    public function store(Request $r, Ledger $ledger)
    {
        $w = $this->workspace($r);
        app(Permissions::class)->authorize($r->user(), $w, 'cards.create');
        $data = $r->validate(['name' => 'required|string|max:100', 'visibility' => ['required', Rule::in(['private', 'shared'])], 'credit_limit' => 'required|string', 'closing_day' => 'required|integer|min:1|max:31', 'due_day' => 'required|integer|min:1|max:31', 'due_month_offset' => 'required|integer|min:0|max:1', 'purchase_on_closing_next' => 'required|boolean']);
        $data['credit_limit'] = Money::normalize($data['credit_limit'], true);
        abort_if((int) $data['due_month_offset'] === 0 && (int) $data['due_day'] < (int) $data['closing_day'], 422, 'Vencimento anterior ao fechamento exige o mês seguinte.');
        DB::transaction(function () use ($r, $w, $data, $ledger) {
            $card = CreditCard::create($data + ['workspace_id' => $w->id, 'created_by' => $r->user()->id]);
            $ledger->audit($r->user(), $w->id, 'credit_card', $card->id, 'created', array_keys($data));
        });

        return back()->with('status', 'Cartão criado.');
    }

    public function purchase(Request $r, int $card, Cards $service)
    {
        $data = $r->validate(['description' => 'required|string|max:150', 'amount' => 'required|string', 'date' => 'required|date_format:Y-m-d|after_or_equal:1000-01-01|before_or_equal:9999-12-31', 'installments' => 'required|integer|min:1|max:120', 'idempotency_key' => 'required|uuid']);
        $service->purchase($r->user(), $this->workspace($r), $card, $data);

        return back()->with('status', 'Compra e parcelas registradas.');
    }

    public function pay(Request $r, int $statement, Cards $service)
    {
        $data = $r->validate(['amount' => 'required|string', 'account_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d|after_or_equal:1000-01-01|before_or_equal:9999-12-31', 'idempotency_key' => 'required|uuid']);
        $service->pay($r->user(), $this->workspace($r), $statement, $data);

        return back()->with('status', 'Pagamento registrado sem duplicar despesa de consumo.');
    }

    public function grant(Request $r, int $card, Cards $service, Ledger $ledger)
    {
        $w = $this->workspace($r);
        $c = $service->card($r->user(), $w, $card);
        abort_unless($c->visibility === 'private' && $c->created_by === $r->user()->id, 403);
        $data = $r->validate(['user_id' => 'required|integer', 'can_manage' => 'required|boolean', 'current_password' => 'required|string|current_password']);
        abort_unless($w->memberships()->where('status', 'active')->where('user_id', $data['user_id'])->exists(), 404);
        DB::transaction(function () use ($r, $w, $c, $data, $ledger) {
            DB::table('card_access')->updateOrInsert(['card_id' => $c->id, 'user_id' => $data['user_id']], ['workspace_id' => $w->id, 'can_manage' => $data['can_manage'], 'granted_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $ledger->audit($r->user(), $w->id, 'credit_card', $c->id, 'access_granted', ['user_id', 'can_manage']);
        });

        return back()->with('status', 'Acesso ao cartão autorizado.');
    }

    public function revoke(Request $r, int $card, int $user, Cards $service, Ledger $ledger)
    {
        $w = $this->workspace($r);
        $c = $service->card($r->user(), $w, $card);
        abort_unless($c->created_by === $r->user()->id, 403);
        $r->validate(['current_password' => 'required|string|current_password']);
        DB::transaction(function () use ($r, $w, $c, $user, $ledger) {
            DB::table('card_access')->where('card_id', $c->id)->where('user_id', $user)->delete();
            $ledger->audit($r->user(), $w->id, 'credit_card', $c->id, 'access_revoked', ['user_id']);
        });

        return back()->with('status','Acesso ao cartão revogado.');
    }
}
