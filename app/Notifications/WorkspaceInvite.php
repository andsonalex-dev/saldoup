<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvite extends Notification
{
    public function __construct(private readonly string $token, private readonly string $workspaceName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Convite para o SaldoUP')->greeting('Olá!')->line('Você foi convidado para o espaço '.$this->workspaceName.'.')->action('Ver convite', route('invitations.show', $this->token))->line('Entre ou cadastre-se com o e-mail que recebeu este convite. O link expira em 7 dias e só pode ser usado uma vez.')->salutation('Equipe SaldoUP');
    }
}
