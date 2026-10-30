<?php

namespace App\Settings;

use App\Enums\Currency;
use App\Enums\PaymentGatewayName;
use App\Enums\SmsDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Single source for admin-editable settings (⚙️ in PROJECT.md §11).
 * Values are cached as one map and the cache is cleared on every write.
 */
class Settings
{
    private const CACHE_KEY = 'settings';

    public const CURRENCY = 'currency';

    public const TIMEZONE = 'timezone';

    public const CANCELLATION_CUTOFF_HOURS = 'cancellation_cutoff_hours';

    public const PAYMENT_GATEWAY = 'payment_gateway';

    public const PAYMENT_HOLD_MINUTES = 'payment_hold_minutes';

    public const FREE_COLLECTION_ABOVE = 'free_collection_above';

    public const MINIMUM_ORDER_AMOUNT = 'minimum_order_amount';

    public const TAX_RATE_BASIS_POINTS = 'tax_rate_basis_points';

    public const TAX_INCLUSIVE = 'tax_inclusive';

    public const NOTIFY_BY_EMAIL = 'notify_by_email';

    public const SMS_DRIVER = 'sms_driver';

    public const REMINDER_HOURS_BEFORE = 'reminder_hours_before';

    public const ADMIN_NOTIFICATION_EMAIL = 'admin_notification_email';

    // TODO(client): confirm the default reminder time.
    public const DEFAULT_REMINDER_HOURS_BEFORE = 24;

    // TODO(client): confirm the default hold for unpaid online orders.
    public const DEFAULT_PAYMENT_HOLD_MINUTES = 15;

    /** Site currency, or null until an admin has chosen one. */
    public function currency(): ?Currency
    {
        return Currency::tryFrom((string) $this->get(self::CURRENCY));
    }

    public function setCurrency(Currency $currency): void
    {
        $this->set(self::CURRENCY, $currency->value);
    }

    /**
     * Timezone for collection slots and other local times (PROJECT.md §12).
     * Falls back to config('app.timezone') until an admin chooses one.
     */
    public function timezone(): string
    {
        $timezone = (string) $this->get(self::TIMEZONE);

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : config('app.timezone');
    }

    public function setTimezone(string $timezone): void
    {
        $this->set(self::TIMEZONE, $timezone);
    }

    /**
     * Customers may cancel or reschedule until this many hours before their slot
     * (client, 2026-10-29). Null until an admin sets it; until then only admins can.
     */
    public function cancellationCutoffHours(): ?int
    {
        $hours = $this->get(self::CANCELLATION_CUTOFF_HOURS);

        return is_numeric($hours) ? (int) $hours : null;
    }

    public function setCancellationCutoffHours(?int $hours): void
    {
        $this->set(self::CANCELLATION_CUTOFF_HOURS, $hours);
    }

    /**
     * Gateway for online payments, or null when online payment is off (client, 2026-10-29).
     * A chosen gateway whose keys have since been removed from .env counts as off.
     */
    public function paymentGateway(): ?PaymentGatewayName
    {
        $gateway = PaymentGatewayName::tryFrom((string) $this->get(self::PAYMENT_GATEWAY));

        return $gateway?->isConfigured() ? $gateway : null;
    }

    public function setPaymentGateway(?PaymentGatewayName $gateway): void
    {
        $this->set(self::PAYMENT_GATEWAY, $gateway?->value);
    }

    /** How long an unpaid online order holds its slot before it is cancelled (client, 2026-10-29). */
    public function paymentHoldMinutes(): int
    {
        $minutes = $this->get(self::PAYMENT_HOLD_MINUTES);

        return is_numeric($minutes) ? (int) $minutes : self::DEFAULT_PAYMENT_HOLD_MINUTES;
    }

    public function setPaymentHoldMinutes(?int $minutes): void
    {
        $this->set(self::PAYMENT_HOLD_MINUTES, $minutes);
    }

    /**
     * Home collection is free when the tests total (after any coupon) reaches this, in minor units.
     * Null: never free (client, 2026-10-30: per-area fee, free above a site-wide value).
     */
    public function freeCollectionAbove(): ?int
    {
        $amount = $this->get(self::FREE_COLLECTION_ABOVE);

        return is_numeric($amount) ? (int) $amount : null;
    }

    public function setFreeCollectionAbove(?int $amount): void
    {
        $this->set(self::FREE_COLLECTION_ABOVE, $amount);
    }

    /** Smallest tests total that can be checked out, in minor units; null for no minimum. */
    public function minimumOrderAmount(): ?int
    {
        $amount = $this->get(self::MINIMUM_ORDER_AMOUNT);

        return is_numeric($amount) ? (int) $amount : null;
    }

    public function setMinimumOrderAmount(?int $amount): void
    {
        $this->set(self::MINIMUM_ORDER_AMOUNT, $amount);
    }

    /** Tax rate in basis points (1800 = 18%); null when no tax is applied. */
    public function taxRateBasisPoints(): ?int
    {
        $rate = $this->get(self::TAX_RATE_BASIS_POINTS);

        return is_numeric($rate) && (int) $rate > 0 ? (int) $rate : null;
    }

    public function setTaxRateBasisPoints(?int $rate): void
    {
        $this->set(self::TAX_RATE_BASIS_POINTS, $rate);
    }

    /**
     * Whether prices already include tax (the tax is shown as a part of the total) or tax is added
     * on top. TODO(client): defaults to included; confirm for the launch country.
     */
    public function taxInclusive(): bool
    {
        return (bool) $this->get(self::TAX_INCLUSIVE, true);
    }

    public function setTaxInclusive(bool $inclusive): void
    {
        $this->set(self::TAX_INCLUSIVE, $inclusive);
    }

    /** Whether customers get order emails (client, 2026-10-29: admins switch channels site-wide). On until turned off. */
    public function notifyByEmail(): bool
    {
        return (bool) $this->get(self::NOTIFY_BY_EMAIL, true);
    }

    public function setNotifyByEmail(bool $enabled): void
    {
        $this->set(self::NOTIFY_BY_EMAIL, $enabled);
    }

    /** SMS driver, or null when SMS is off. A driver whose keys have since been removed counts as off. */
    public function smsDriver(): ?SmsDriver
    {
        $driver = SmsDriver::tryFrom((string) $this->get(self::SMS_DRIVER));

        return $driver?->isConfigured() ? $driver : null;
    }

    public function setSmsDriver(?SmsDriver $driver): void
    {
        $this->set(self::SMS_DRIVER, $driver?->value);
    }

    /** Hours before the slot to send the collection reminder; 0 turns reminders off. */
    public function reminderHoursBefore(): int
    {
        $hours = $this->get(self::REMINDER_HOURS_BEFORE);

        return is_numeric($hours) ? (int) $hours : self::DEFAULT_REMINDER_HOURS_BEFORE;
    }

    public function setReminderHoursBefore(?int $hours): void
    {
        $this->set(self::REMINDER_HOURS_BEFORE, $hours);
    }

    /** Where admin alerts are emailed; null turns them off. */
    public function adminNotificationEmail(): ?string
    {
        return $this->get(self::ADMIN_NOTIFICATION_EMAIL) ?: null;
    }

    public function setAdminNotificationEmail(?string $email): void
    {
        $this->set(self::ADMIN_NOTIFICATION_EMAIL, $email);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        DB::table('settings')->upsert(
            [['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')
            ->pluck('value', 'key')
            ->map(fn (?string $value) => $value === null ? null : json_decode($value, true))
            ->all());
    }
}
