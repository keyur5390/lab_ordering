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
     * @param  Builder<ServiceArea>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
