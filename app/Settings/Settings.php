<?php

namespace App\Settings;

use App\Enums\Currency;
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

    /** Site currency, or null until an admin has chosen one. */
    public function currency(): ?Currency
    {
        return Currency::tryFrom((string) $this->get(self::CURRENCY));
    }

    public function setCurrency(Currency $currency): void
    {
        $this->set(self::CURRENCY, $currency->value);
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
