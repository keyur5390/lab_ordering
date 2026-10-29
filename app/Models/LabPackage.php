<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PackagePricingMode;
use Database\Factories\LabPackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A bundle of lab tests (PROJECT.md §3). Its price is worked out by App\Services\PackagePricingService.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $slug
 * @property string|null $description
 * @property PackagePricingMode $pricing_mode
 * @property int|null $price_amount
 * @property DiscountType|null $discount_type
 * @property int|null $discount_value
 * @property bool $is_active
 */
class LabPackage extends Model
{
    /** @use HasFactory<LabPackageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'slug',
        'description',
        'pricing_mode',
        'price_amount',
        'discount_type',
        'discount_value',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_mode' => PackagePricingMode::class,
            'price_amount' => 'integer',
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<LabTest, $this>
     */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(LabTest::class)->orderBy('name');
    }

    /**
     * @param  Builder<LabPackage>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
