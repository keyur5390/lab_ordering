<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a new staff member to set their password. An account email like verification and password
 * reset, so its wording isn't in the editable order notifications.
 */
class StaffInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('You\'ve been invited to '.config('app.name'))
            ->line("You've been given a {$notifiable->role->label()} account at ".config('app.name').'.')
            ->action('Set your password', $url)
            ->line("This link expires in {$minutes} minutes. If it has expired, use \"Forgot password\" on the sign-in page.");
    }
}
