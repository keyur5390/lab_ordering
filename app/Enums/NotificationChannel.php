<?php

namespace App\Enums;

/**
 * Channels for order notifications (client, 2026-10-29: email and SMS; WhatsApp added in B-31).
 */
enum NotificationChannel: string
{
    case Mail = 'mail';
    case Sms = 'sms';
    /** Twilio Content Templates; the wording lives in Twilio (App\Models\WhatsAppTemplate). */
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
