<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function register(array $data): User
    {
        $user = User::create($data);
        event(new Registered($user));

        return $user;
    }

    public function login(array $credentials, string $ip): void
    {
        $key = 'login:'.hash('sha256', $credentials['email'].'|'.$ip);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Aguarde um minuto e tente novamente.']);
        }

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        RateLimiter::clear($key);
    }

    public function sendResetLink(array $credentials): void
    {
        Password::sendResetLink($credentials);
    }

    public function resetPassword(array $credentials): bool
    {
        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset;
    }
}
