<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticationController extends Controller
{
    public function __construct(private readonly AuthenticationService $authentication) {}

    public function register(RegisterRequest $request): RedirectResponse
    {
        Auth::login($this->authentication->register($request->validated()));
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $this->authentication->login($request->validated(), $request->ip());
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgotPassword(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->authentication->sendResetLink($request->validated());

        return back()->with('status', 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.');
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        if (! $this->authentication->resetPassword($request->validated())) {
            return back()->withErrors(['email' => 'Este link é inválido ou expirou. Solicite um novo link.'])->withInput($request->only('email'));
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Senha atualizada. Entre com a nova senha.');
    }
}
