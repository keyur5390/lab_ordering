<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A promo code (client, 2026-10-30). Checked and applied by App\Services\CouponService.
 *
 * @property int $id
 * @property string $code
 * @property string|null $description
 * @property DiscountType $discount_type
 * @property int $discount_value percent (1–100) or minor units
 * @property int|null $max_discount_amount
 * @property int|null $min_order_amount
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $usage_limit
 * @property int|null $per_customer_limit
 * @property bool $is_active
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code', 'description', 'discount_type', 'discount_value', 'max_discount_amount', 'min_order_amount',
        'starts_on', 'ends_on', 'usage_limit', 'per_customer_limit', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'max_discount_amount' => 'integer',
            'min_order_amount' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Codes are matched without regard to case or surrounding spaces. */
    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
