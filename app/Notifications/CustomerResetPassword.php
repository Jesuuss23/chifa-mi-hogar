<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomerResetPassword extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Trust configured APP_URL, never the incoming Host header.
        $url = rtrim(config('app.url'), '/').route('customer.password.reset', [
            'token' => $this->token, 'email' => $notifiable->email,
        ], false);

        return (new MailMessage)
            ->subject('Restablecer contraseña | Chifa Mi Hogar')
            ->greeting('Hola')
            ->line('Recibimos una solicitud para establecer una contraseña para tu cuenta.')
            ->action('Establecer contraseña', $url)
            ->line('El enlace caduca en '.config('auth.passwords.users.expire').' minutos y solo puede utilizarse una vez.')
            ->line('Si no solicitaste este cambio, puedes ignorar este mensaje.');
    }
}
