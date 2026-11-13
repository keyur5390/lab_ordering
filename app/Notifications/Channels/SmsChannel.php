<?php

namespace App\Notifications\Channels;

use App\Notifications\OrderNotification;
use App\Settings\Settings;

/**
 * Sends a notification's SMS text through the driver chosen in Admin → Settings.
 */
class SmsChannel
{
    public function __construct(private Settings $settings) {}

    public function send(object $notifiable, OrderNotification $notification): void
    {
        $driver = $this->settings->smsDriver();
        $to = $notifiable->routeNotificationFor('sms', $notification);
        $message = $notification->toSms($notifiable);

        if (! $driver || ! $to || $message === null) {
            return;
        }

        // A failure is thrown so the queued job is retried.
        $driver->sender()->send($to, $message);
    }
}
