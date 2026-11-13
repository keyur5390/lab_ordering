<?php

namespace App\Notifications\Channels;

use App\Notifications\OrderNotification;
use App\Services\WhatsApp\TwilioWhatsAppSender;

/**
 * Sends a notification as a WhatsApp template message (B-31).
 */
class WhatsAppChannel
{
    public function __construct(private TwilioWhatsAppSender $sender) {}

    public function send(object $notifiable, OrderNotification $notification): void
    {
        $to = $notifiable->routeNotificationFor('whatsapp', $notification);
        $message = $notification->toWhatsApp($notifiable);

        if (! $to || $message === null) {
            return;
        }

        $this->sender->send($to, $message['content_sid'], $message['variables']);
    }
}
