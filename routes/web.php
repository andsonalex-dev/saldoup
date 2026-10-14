<?php

use App\Http\Controllers\Admin\UserAdministrationController;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\CardController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Memberships\InvitationController;
use App\Http\Controllers\Memberships\MemberController;
use App\Http\Controllers\Memberships\WorkspaceController;
use App\Http\Middleware\NoStore;
use Illuminate\Support\Facades\Route;

foreach (['workspace', 'member', 'account', 'statement', 'card', 'invitation', 'user', 'transaction'] as $parameter) {
    Route::pattern($parameter, '[0-9]+');
}

Route::view('/', 'welcome')->name('home');

Route::middleware(NoStore::class)->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::view('/cadastro', 'auth.register')->name('register');
        Route::post('/cadastro', [AuthenticationController::class, 'register'])->middleware('throttle:5,1');
        Route::view('/entrar', 'auth.login')->name('login');
        Route::post('/entrar', [AuthenticationController::class, 'login'])->middleware('throttle:20,1');
        Route::view('/recuperar-senha', 'auth.forgot-password')->name('password.request');
        Route::post('/recuperar-senha', [AuthenticationController::class, 'forgotPassword'])->middleware('throttle:5,1')->name('password.email');
        Route::view('/redefinir-senha/{token}', 'auth.reset-password')->name('password.reset');
        Route::post('/redefinir-senha', [AuthenticationController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
    });

    Route::middleware(['auth', 'auth.session'])->group(function (): void {
        Route::get('/painel', DashboardController::class)->name('dashboard');
        Route::get('/espacos', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('/espacos', [WorkspaceController::class, 'store'])->middleware('throttle:20,1')->name('workspaces.store');
        Route::get('/espacos/{workspace}', [WorkspaceController::class, 'show'])->whereNumber('workspace')->name('workspaces.show');
        Route::patch('/espacos/{workspace}', [WorkspaceController::class, 'update'])->whereNumber('workspace')->name('workspaces.update');
        Route::post('/espacos/{workspace}/selecionar', [WorkspaceController::class, 'activate'])->whereNumber('workspace')->name('workspaces.activate');
        Route::get('/administracao/usuarios', [UserAdministrationController::class, 'index'])->name('admin.users.index');
        Route::patch('/administracao/usuarios/{user}', [UserAdministrationController::class, 'update'])->middleware('throttle:10,1')->name('admin.users.update');
        Route::get('/espacos/{workspace}/membros', [MemberController::class, 'index'])->name('members.index');
        Route::post('/espacos/{workspace}/convites', [MemberController::class, 'invite'])->middleware('throttle:10,1')->name('members.invite');
        Route::patch('/espacos/{workspace}/membros/{member}', [MemberController::class, 'update'])->middleware('throttle:10,1')->name('members.update');
        Route::delete('/espacos/{workspace}/membros/{member}', [MemberController::class, 'remove'])->middleware('throttle:10,1')->name('members.remove');
        Route::delete('/espacos/{workspace}/convites/{invitation}', [MemberController::class, 'cancel'])->middleware('throttle:10,1')->name('members.cancel');
        Route::get('/convites/{token}', [InvitationController::class, 'show'])->name('invitations.show');
        Route::post('/convites/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');
        Route::get('/financas', [FinanceController::class, 'index'])->name('finance.index');
        Route::post('/financas/contas', [FinanceController::class, 'createAccount'])->name('finance.accounts.store');
        Route::patch('/financas/contas/{account}', [FinanceController::class, 'updateAccount'])->name('finance.accounts.update');
        Route::post('/financas/contas/{account}/autorizacoes', [FinanceController::class, 'grant'])->middleware('throttle:10,1')->name('finance.accounts.grant');
        Route::delete('/financas/contas/{account}/autorizacoes/{user}', [FinanceController::class, 'revoke'])->middleware('throttle:10,1')->name('finance.accounts.revoke');
        Route::post('/financas/categorias', [FinanceController::class, 'category'])->name('finance.categories.store');
        Route::post('/financas/lancamentos', [FinanceController::class, 'store'])->name('finance.transactions.store');
        Route::post('/financas/lancamentos/{transaction}/situacao', [FinanceController::class, 'status'])->name('finance.transactions.status');
        Route::post('/financas/transferencias', [FinanceController::class, 'transfer'])->name('finance.transfers.store');
        Route::get('/cartoes', [CardController::class, 'index'])->name('cards.index');
        Route::post('/cartoes', [CardController::class, 'store'])->name('cards.store');
        Route::post('/cartoes/{card}/compras', [CardController::class, 'purchase'])->name('cards.purchase');
        Route::post('/faturas/{statement}/pagamentos', [CardController::class, 'pay'])->name('cards.pay');
        Route::post('/cartoes/{card}/autorizacoes', [CardController::class, 'grant'])->middleware('throttle:10,1')->name('cards.grant');
        Route::delete('/cartoes/{card}/autorizacoes/{user}', [CardController::class, 'revoke'])->middleware('throttle:10,1')->name('cards.revoke');
        Route::post('/financas/recorrencias', [FinanceController::class, 'recurrence'])->name('finance.recurrences.store');
        Route::post('/financas/contas/{account}/saldo', [FinanceController::class, 'adjust'])->middleware('throttle:10,1')->name('finance.accounts.adjust');
        Route::post('/espacos/{workspace}/propriedade', [MemberController::class, 'transferOwnership'])->middleware('throttle:5,1')->name('members.transfer-ownership');
        Route::post('/sair', [AuthenticationController::class, 'logout'])->name('logout');
    });
});
