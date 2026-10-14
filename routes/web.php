<?php

use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Middleware\NoStore;
use Illuminate\Support\Facades\Route;

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
        Route::view('/painel', 'dashboard')->name('dashboard');
        Route::post('/sair', [AuthenticationController::class, 'logout'])->name('logout');
    });
});
