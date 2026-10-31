<?php

namespace App\Enums;

/**
 * Online payment gateways (client, 2026-10-29: both built, the admin picks one in Settings).
 * API keys live in .env (config/services.php); a gateway can be chosen only once its keys are set.
 */
enum PaymentGatewayName: string
{
    case Stripe = 'stripe';
    case Razorpay = 'razorpay';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::Razorpay => 'Razorpay',
        };
    }

    public function isConfigured(): bool
    {
        return match ($this) {
            self::Stripe => filled(config('services.stripe.secret')) && filled(config('services.stripe.webhook_secret')),
            self::Razorpay => filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'))
                && filled(config('services.razorpay.webhook_secret')),
        };
    }

    /** The .env keys an operator sets to enable this gateway. */
    public function envKeys(): string
    {
        return match ($this) {
            self::Stripe => 'STRIPE_SECRET, STRIPE_WEBHOOK_SECRET',
            self::Razorpay => 'RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET, RAZORPAY_WEBHOOK_SECRET',
        };
    }
}
