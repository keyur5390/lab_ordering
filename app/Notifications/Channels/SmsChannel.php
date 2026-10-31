<?php

namespace App\Notifications\Channels;

use App\Enums\SmsDriver;
use App\Notifications\OrderNotification;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\TwilioSmsSender;
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

        $sender = match ($driver) {
            SmsDriver::Log => app(LogSmsSender::class),
            SmsDriver::Twilio => app(TwilioSmsSender::class),
        };

        // A failure is thrown so the queued job is retried.
        $sender->send($to, $message);
    }
}
