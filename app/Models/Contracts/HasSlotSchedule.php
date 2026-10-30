<?php

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something that offers bookable slots from a weekly schedule: a service area (home collection)
 * or a branch (lab visits). Used by App\Services\SlotService.
 *
 * @property int $id
 * @property int|null $slot_minutes
 * @property int|null $capacity_per_slot
 * @property int|null $lead_time_minutes
 * @property int|null $booking_window_days
 */
interface HasSlotSchedule
{
    /** Weekly opening ranges, by weekday then opening time. */
    public function hours(): HasMany;

    /** Has everything needed to offer slots. */
    public function hasSchedule(): bool;

    /** Column on orders and closures that points at this, e.g. service_area_id. */
    public function scheduleKey(): string;
}
