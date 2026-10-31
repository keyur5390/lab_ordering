<?php

namespace App\Models;

use App\Models\Contracts\HasSlotSchedule;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lab branch customers can visit (client, 2026-10-30), with its own slot schedule.
 *
 * @property int $id
 * @property string $name
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string|null $region
 * @property string $postcode
 * @property string $country
 * @property float $latitude
 * @property float $longitude
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $directions
 * @property string|null $photo_path public disk
 * @property bool $is_active
 * @property int|null $slot_minutes
 * @property int|null $capacity_per_slot
 * @property int|null $lead_time_minutes
 * @property int|null $booking_window_days
 */
class Branch extends Model implements HasSlotSchedule
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name', 'line1', 'line2', 'city', 'region', 'postcode', 'country', 'latitude', 'longitude',
        'phone', 'email', 'directions', 'photo_path', 'is_active',
        'slot_minutes', 'capacity_per_slot', 'lead_time_minutes', 'booking_window_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
            'slot_minutes' => 'integer',
            'capacity_per_slot' => 'integer',
            'lead_time_minutes' => 'integer',
            'booking_window_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<BranchHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(BranchHour::class)->orderBy('weekday')->orderBy('opens_at');
    }

    public function hasSchedule(): bool
    {
        return $this->slot_minutes !== null
            && $this->capacity_per_slot !== null
            && $this->lead_time_minutes !== null
            && $this->booking_window_days !== null;
    }

    public function scheduleKey(): string
    {
        return 'branch_id';
    }

    /**
     * The address as stored on lab-visit orders (same shape as a customer's address).
     *
     * @return array<string, mixed>
     */
    public function addressSnapshot(): array
    {
        return [
            'label' => $this->name,
            ...$this->only(['line1', 'line2', 'city', 'region', 'postcode', 'country', 'latitude', 'longitude']),
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
