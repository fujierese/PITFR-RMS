<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] public string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $isInitialSetup = $notifiable->password === null;
        $path = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);
        $url = rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');

        $message = (new MailMessage)
            ->subject($isInitialSetup ? 'Set Up Your PITFR-RMS Password' : 'Reset Your PITFR-RMS Password')
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line($isInitialSetup
                ? 'A PITFR-RMS account has been created for you. Use the button below to set your password.'
                : 'You are receiving this email because we received a request to reset your PITFR-RMS account password.')
            ->action($isInitialSetup ? 'Set Password' : 'Reset Password', $url)
            ->line('This link will expire in 60 minutes.');

        if ($isInitialSetup) {
            $message->line('If you were not expecting this account setup email, you can ignore it or contact the Supply Office.');
        } else {
            $message->line('If you did not request a password reset, no further action is required.');
        }

        return $message->salutation('Regards, PITFR-RMS');
    }
}