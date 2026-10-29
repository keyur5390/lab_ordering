<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One opening range on one weekday, in local time.
 *
 * @property int $id
 * @property int $service_area_id
 * @property int $weekday ISO-8601: 1 = Monday … 7 = Sunday
 * @property string $opens_at HH:MM[:SS]
 * @property string $closes_at HH:MM[:SS]
 */
class ServiceAreaHour extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['weekday', 'opens_at', 'closes_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    /**
     * @return BelongsTo<ServiceArea, $this>
     */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }
}
