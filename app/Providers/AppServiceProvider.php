<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            return (new MailMessage)
                ->subject('Redefina sua senha — SaldoUP')
                ->greeting('Olá!')
                ->line('Recebemos um pedido para redefinir a senha da sua conta.')
                ->action('Redefinir senha', route('password.reset', ['token' => $token, 'email' => $user->email]))
                ->line('Este link expira em '.config('auth.passwords.users.expire').' minutos e pode ser usado uma única vez.')
                ->line('Se você não fez este pedido, ignore esta mensagem.')
                ->salutation('Equipe SaldoUP');
        });
    }
}
