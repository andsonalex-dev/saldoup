<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\FinancialTransaction;
use App\Services\Finance\BalanceAdjustments;
use App\Services\Finance\Ledger;
use App\Services\Finance\Money;
use App\Services\Finance\Recurrences;
use App\Services\Memberships\ActiveWorkspace;
use App\Services\Memberships\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    private function workspace(Request $r)
    {
        $w = app(ActiveWorkspace::class)->resolve($r);
        abort_unless($w, 409, 'Selecione um espaço financeiro primeiro.');

        return $w;
    }

    public function index(Request $r, Ledger $ledger)
    {
        $w = $this->workspace($r);
        $accounts = $ledger->accounts($r->user(), $w)->orderBy('name')->paginate(20, ['*'], 'accounts_page');
        $ledger->preloadBalances($accounts->getCollection());
        $allowed = app(Permissions::class)->snapshot($r->user(), $w);
        $canView = $allowed['transactions.view'];
        $members = $w->memberships()->where('status', 'active')->with('user')->get();
        $authorizations = DB::table('account_access')->where('account_access.workspace_id', $w->id)
            ->whereIn('account_id', $accounts->getCollection()->where('created_by', $r->user()->id)->pluck('id'))
            ->join('users', 'users.id', '=', 'account_access.user_id')
            ->get(['account_access.account_id', 'users.name', 'users.id'])->groupBy('account_id');

        $transactions = $canView ? FinancialTransaction::with('account:id,name')->where('workspace_id', $w->id)->whereIn('account_id', $ledger->accounts($r->user(), $w)->select('accounts.id'))->orderByDesc('date')->orderByDesc('id')->paginate(20, ['*'], 'transactions_page') : null;
        $accountIds = $accounts->getCollection()->pluck('id')->merge($transactions?->getCollection()->pluck('account_id') ?? []);
        $writableAccountIds = $ledger->writable($r->user(), $w)->whereIn('accounts.id', $accountIds)->pluck('id')->all();
        $writableAccounts = $accounts->getCollection()->whereIn('id', $writableAccountIds);

        return view('finance.index', ['workspace' => $w, 'accounts' => $accounts, 'transactions' => $transactions, 'categories' => DB::table('categories')->where('workspace_id', $w->id)->orderBy('name')->get(), 'ledger' => $ledger, 'members' => $members, 'authorizations' => $authorizations, 'allowed' => $allowed, 'writableAccountIds' => $writableAccountIds, 'writableAccounts' => $writableAccounts]);

    }

    public function createAccount(Request $r, Ledger $ledger)
    {
        $w = $this->workspace($r);
        app(Permissions::class)->authorize($r->user(), $w, 'accounts.create');
        $data = $r->validate(['name' => 'required|string|max:100', 'type' => ['required', Rule::in(['checking', 'savings', 'cash', 'investment'])], 'visibility' => ['required', Rule::in(['private', 'shared'])], 'opening_balance' => 'required|string']);
        $data['opening_balance'] = Money::normalize($data['opening_balance']);
        abort_if(bccomp($data['opening_balance'], '0', 2) < 0, 422, 'Saldos iniciais negativos ainda não estão disponíveis.');
        DB::transaction(function () use ($r, $w, $data, $ledger) {
            $a = Account::create($data + ['workspace_id' => $w->id, 'created_by' => $r->user()->id, 'currency' => 'BRL']);
            $ledger->audit($r->user(), $w->id, 'account', $a->id, 'created', array_keys($data));
        });

        return back()->with('status', 'Conta criada.');
    }

    public function updateAccount(Request $r, int $account, Ledger $ledger)
    {
        $w = $this->workspace($r);
        app(Permissions::class)->authorize($r->user(), $w, 'accounts.update');
        $data = $r->validate(['name' => 'required|string|max:100']);
        DB::transaction(function () use ($r, $w, $account, $ledger, $data) {
            $a = $ledger->account($r->user(), $w, $account, true);
            Account::whereKey($a->id)->lockForUpdate()->firstOrFail();
            $a->update($data);
            $ledger->audit($r->user(), $w->id, 'account', $a->id, 'renamed', ['name']);
        });

        return back()->with('status', 'Conta atualizada.');
    }

    public function grant(Request $r, int $account, Ledger $ledger)
    {
        $w = $this->workspace($r);
        $a = $ledger->account($r->user(), $w, $account);
        abort_unless($a->created_by === $r->user()->id && $a->visibility === 'private', 403);
        $data = $r->validate(['user_id' => 'required|integer', 'can_manage' => 'required|boolean', 'current_password' => 'required|string|current_password']);
        abort_unless($w->memberships()->where('status', 'active')->where('user_id', $data['user_id'])->exists(), 404);
        DB::transaction(function () use ($r, $w, $a, $data, $ledger) {
            DB::table('account_access')->updateOrInsert(['account_id' => $a->id, 'user_id' => $data['user_id']], ['workspace_id' => $w->id, 'granted_by' => $r->user()->id, 'can_manage' => $data['can_manage'], 'created_at' => now(), 'updated_at' => now()]);
            $ledger->audit($r->user(), $w->id, 'account', $a->id, 'access_granted', ['user_id', 'can_manage']);
        });

        return back()->with('status', 'Acesso explícito registrado.');
    }

    public function revoke(Request $r, int $account, int $user, Ledger $ledger)
    {
        $w = $this->workspace($r);
        $a = $ledger->account($r->user(), $w, $account);
        abort_unless($a->created_by === $r->user()->id, 403);
        $r->validate(['current_password' => 'required|string|current_password']);
        DB::transaction(function () use ($r, $w, $a, $user, $ledger) {
            DB::table('account_access')->where('account_id', $a->id)->where('user_id', $user)->delete();
            $ledger->audit($r->user(), $w->id, 'account', $a->id, 'access_revoked', ['user_id']);
        });

        return back()->with('status', 'Autorização revogada.');
    }

    public function category(Request $r, Ledger $ledger)
    {
        $w = $this->workspace($r);
        app(Permissions::class)->authorize($r->user(), $w, 'transactions.create');
        $data = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('categories')->where('workspace_id', $w->id)]]);
        DB::transaction(function () use ($r, $w, $data, $ledger) {
            $id = DB::table('categories')->insertGetId($data + ['workspace_id' => $w->id, 'created_at' => now(), 'updated_at' => now()]);
            $ledger->audit($r->user(), $w->id, 'category', $id, 'created', ['name']);
        });

        return back()->with('status', 'Categoria criada.');
    }

    public function store(Request $r, Ledger $ledger)
    {
        $data = $r->validate(['account_id' => 'required|integer', 'category_id' => 'nullable|integer', 'type' => ['required', Rule::in(['income', 'expense'])], 'status' => ['required', Rule::in(['planned', 'posted'])], 'description' => 'required|string|max:150', 'amount' => 'required|string', 'date' => 'required|date_format:Y-m-d|after_or_equal:1000-01-01|before_or_equal:9999-12-31', 'idempotency_key' => 'required|uuid']);
        $ledger->transaction($r->user(), $this->workspace($r), $data);

        return back()->with('status', 'Lançamento registrado.');
    }

    public function status(Request $r, int $transaction, Ledger $ledger)
    {
        $data = $r->validate(['action' => ['required', Rule::in(['post', 'cancel', 'reverse'])], 'date' => 'nullable|date_format:Y-m-d|after_or_equal:1000-01-01|before_or_equal:9999-12-31']);
        $ledger->changeStatus($r->user(), $this->workspace($r), $transaction, $data['action'], $data['date'] ?? null);

        return back()->with('status', 'Operação aplicada.');
    }

    public function transfer(Request $r, Ledger $ledger)
    {
        $data = $r->validate(['from_account_id' => 'required|integer', 'to_account_id' => 'required|integer', 'amount' => 'required|string', 'date' => 'required|date_format:Y-m-d|after_or_equal:1000-01-01|before_or_equal:9999-12-31', 'idempotency_key' => 'required|uuid']);
        $ledger->transfer($r->user(), $this->workspace($r), $data);

        return back()->with('status', 'Transferência registrada.');
    }

    public function recurrence(Request $r)
    {
        $data = $r->validate(['account_id' => 'required|integer', 'category_id' => 'nullable|integer', 'type' => ['required', Rule::in(['income', 'expense'])], 'description' => 'required|string|max:150', 'amount' => 'required|string', 'date' => 'required|date_format:Y-m-d|after_or_equal:1000-01-01|before_or_equal:9999-12-31', 'until' => 'required|date_format:Y-m-d|after_or_equal:date|before_or_equal:9999-12-31', 'frequency' => ['required', Rule::in(['monthly', 'weekly'])], 'idempotency_key' => 'required|uuid']);
        $count = app(Recurrences::class)->generate($r->user(), $this->workspace($r), $data);

        return back()->with('status', $count.' ocorrências previstas geradas.');
    }

    public function adjust(Request $r, int $account)
    {
        $data = $r->validate(['target_balance' => 'required|string', 'current_password' => 'required|string|current_password', 'idempotency_key' => 'required|uuid']);
        app(BalanceAdjustments::class)->set($r->user(), $this->workspace($r), $account, $data);

        return back()->with('status', 'Saldo informado registrado com ajuste e histórico preservado.');
    }
}
