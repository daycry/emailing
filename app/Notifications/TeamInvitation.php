<?php

namespace App\Notifications;

use App\Support\TrustedUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification
{
    public function __construct(private string $accountName, private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitación al equipo de '.$this->accountName)
            ->greeting('Te han invitado a colaborar')
            ->line('Únete a la cuenta '.$this->accountName.' con la dirección de email que recibió esta invitación.')
            ->action('Revisar invitación', TrustedUrl::route('invitations.show', ['token' => $this->token]))
            ->line('El enlace caduca en siete días y solo puede utilizarse una vez. Si no esperabas esta invitación, puedes ignorarla.')
            ->salutation('El equipo de Correo');
    }
}
