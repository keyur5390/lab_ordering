<?php

namespace App\Enums;

/**
 * Channels for order notifications (client, 2026-10-29: email and SMS; WhatsApp later).
 */
enum NotificationChannel: string
{
    case Mail = 'mail';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::Sms => 'SMS',
        };
    }
}
