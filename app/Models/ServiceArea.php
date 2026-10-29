<?php

namespace App\Models;

use App\Enums\ServiceAreaType;
use Database\Factories\ServiceAreaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where home collection is available (PROJECT.md §6). Matching lives in App\Services\ServiceAreaService.
 *
 * @property int $id
 * @property string $name
 * @property ServiceAreaType $type
 * @property string|null $country
 * @property float|null $center_latitude
 * @property float|null $center_longitude
 * @property int|null $radius_meters
 * @property list<array{0: float, 1: float}>|null $polygon
 * @property bool $is_active
 * @property int|null $slot_minutes
 * @property int|null $capacity_per_slot
 * @property int|null $lead_time_minutes
 * @property int|null $booking_window_days
 */
class ServiceArea extends Model
{
    /** @use HasFactory<ServiceAreaFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name', 'type', 'country', 'center_latitude', 'center_longitude', 'radius_meters', 'polygon', 'is_active',
        'slot_minutes', 'capacity_per_slot', 'lead_time_minutes', 'booking_window_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceAreaType::class,
            'center_latitude' => 'float',
            'center_longitude' => 'float',
            'radius_meters' => 'integer',
            'polygon' => 'array',
            'is_active' => 'boolean',
            'slot_minutes' => 'integer',
            'capacity_per_slot' => 'integer',
            'lead_time_minutes' => 'integer',
            'booking_window_days' => 'integer',
        ];
    }

    /**
     * Normalised postcodes (type = postcodes).
     *
     * @return HasMany<ServiceAreaPostcode, $this>
     */
    public function postcodes(): HasMany
    {
        return $this->hasMany(ServiceAreaPostcode::class)->orderBy('postcode');
    }

    /**
     * Weekly opening hours.
     *
     * @return HasMany<ServiceAreaHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(ServiceAreaHour::class)->orderBy('weekday')->orderBy('opens_at');
    }

    /**
     * Closures for this area only (closures for all areas have no area).
     *
     * @return HasMany<CollectionClosure, $this>
     */
    public function closures(): HasMany
    {
        return $this->hasMany(CollectionClosure::class);
    }

    /** Has everything needed to offer slots. */
    public function hasSchedule(): bool
    {
        return $this->slot_minutes !== null
            && $this->capacity_per_slot !== null
            && $this->lead_time_minutes !== null
            && $this->booking_window_days !== null;
    }

    /**
     * @param  Builder<ServiceArea>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
