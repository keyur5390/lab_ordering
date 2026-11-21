<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Models\NotificationLog;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\OrderNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records every order email, SMS and WhatsApp message (B-24, B-31): sent ones from Laravel's NotificationSent event, failed
 * ones from the queue's failed-job hook. Queued notifications run one job per channel, so each entry
 * is one message on one channel.
 */
class NotificationLogger
{
    public function sent(object $notifiable, OrderNotification $notification, string $channel): void
    {
        $this->log($notifiable, $notification, $channel, NotificationLog::SENT);
    }

    public function failed(object $notifiable, OrderNotification $notification, string $channel, Throwable $e): void
    {
        $this->log($notifiable, $notification, $channel, NotificationLog::FAILED, Str::limit($e->getMessage(), 1000));
    }

    /** Laravel's channel name ('mail', or our SMS channel class) as our enum, or null for others. */
    public static function channel(string $channel): ?NotificationChannel
    {
        return match ($channel) {
            'mail' => NotificationChannel::Mail,
            SmsChannel::class => NotificationChannel::Sms,
            WhatsAppChannel::class => NotificationChannel::WhatsApp,
            default => null,
        };
    }

    private function log(object $notifiable, OrderNotification $notification, string $channel, string $status, ?string $error = null): void
    {
        $type = self::channel($channel);
        if (! $type) {
            return;
        }

        NotificationLog::create([
            'order_id' => $notification->order->id,
            'event' => $notification->event,
            'channel' => $type,
            'recipient' => $this->recipient($notifiable, $type, $notification),
            'status' => $status,
            'error' => $error,
            'amount' => $notification->amount,
        ]);
    }

    private function recipient(object $notifiable, NotificationChannel $channel, OrderNotification $notification): ?string
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return $notifiable->routes['mail'] ?? null;
        }

        return match ($channel) {
            NotificationChannel::Mail => $notifiable->routeNotificationFor('mail', $notification),
            NotificationChannel::Sms => $notifiable->routeNotificationFor('sms', $notification),
            NotificationChannel::WhatsApp => $notifiable->routeNotificationFor('whatsapp', $notification),
        } ?: null;
    }
}
