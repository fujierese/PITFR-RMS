<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserAccountNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Welcome to PITFR-RMS - Set Up Your Account')
            ->greeting('Welcome to PITFR-RMS!')
            ->line('An administrator created a PIT Facility Request System account for you.')
            ->line('Use the button below to create your personal password before signing in.')
            ->action('Set Up Your Password', $url)
            ->line('This setup link will expire in 60 minutes.')
            ->line('For your security, do not share this link with anyone.')
            ->salutation('Regards, PITFR-RMS');
    }
}
