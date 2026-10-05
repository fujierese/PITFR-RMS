<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;
    use UsesNotificationPreferences;

    public function via(object $notifiable): array
    {
        return $this->channelsWithPreference($notifiable, 'security_alerts', ['database', 'mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your PITFR-RMS password was changed')
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line('Your PITFR-RMS account password was changed.')
            ->line('If you did not make this change, reset your password immediately.')
            ->action('Reset Password', route('password.request'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'security_alert',
            'title' => 'Password Changed',
            'body' => 'Your PITFR-RMS account password was changed. If you did not make this change, reset your password immediately.',
        ];
    }
}
