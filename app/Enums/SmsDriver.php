<?php

namespace App\Enums;

/**
 * How SMS are sent (client, 2026-10-29: a driver per provider, the admin picks one in Settings).
 * Provider keys live in .env; a driver can be chosen only once its keys are set.
 */
enum SmsDriver: string
{
    /** Writes messages to the application log instead of sending them; for testing. */
    case Log = 'log';
    case Twilio = 'twilio';

    public function label(): string
    {
        return match ($this) {
            self::Log => 'Log only (testing, nothing is sent)',
            self::Twilio => 'Twilio',
        };
    }

    public function isConfigured(): bool
    {
        return match ($this) {
            self::Log => true,
            self::Twilio => filled(config('services.twilio.sid')) && filled(config('services.twilio.token'))
                && filled(config('services.twilio.from')),
        };
    }

    /** The .env keys an operator sets to enable this driver. */
    public function envKeys(): ?string
    {
        return match ($this) {
            self::Log => null,
            self::Twilio => 'TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM',
        };
    }
}
