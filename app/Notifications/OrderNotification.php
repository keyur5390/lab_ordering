<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\NotificationTemplateService;
use App\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One order event, on every channel that is switched on, has active wording and can reach the
 * recipient. Admin alerts go by email only. Queued, and sent only after the order is saved.
 */
class OrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NotificationEvent $event,
        public Order $order,
        /** Payment or refund amount, in minor units. */
        public ?int $amount = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $settings = app(Settings::class);
        $channels = [];

        if (($this->event->forAdmins() || $settings->notifyByEmail()) && $this->message(NotificationChannel::Mail)) {
            $channels[] = 'mail';
        }

        if ($notifiable instanceof User
            && in_array(NotificationChannel::Sms, $this->event->channels(), true)
            && $settings->smsDriver()
            && ! $notifiable->sms_opt_out
            && $notifiable->routeNotificationForSms()
            && $this->message(NotificationChannel::Sms)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message(NotificationChannel::Mail);
        $mail = (new MailMessage)->subject($message['subject']);

        // Paragraphs are separated by blank lines; lines are escaped by the mail template.
        foreach (preg_split('/\R{2,}/', trim($message['body'])) as $paragraph) {
            $mail->line($paragraph);
        }

        return $mail->action('View order', $notifiable instanceof AnonymousNotifiable
            ? route('admin.orders.show', $this->order)
            : route('orders.show', $this->order));
    }

    public function toSms(object $notifiable): ?string
    {
        return $this->message(NotificationChannel::Sms)['body'] ?? null;
    }

    /**
     * @return array{subject: string, body: string}|null
     */
    private function message(NotificationChannel $channel): ?array
    {
        return app(NotificationTemplateService::class)->render($this->event, $channel, $this->order, $this->amount);
    }
}
