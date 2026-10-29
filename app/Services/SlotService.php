<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\CollectionClosure;
use App\Models\Order;
use App\Models\ServiceArea;
use App\Models\ServiceAreaHour;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Home-collection slots for a service area (PROJECT.md §6). Slots come from the area's weekly
 * hours, stepped by its slot length, in the site timezone. Every slot in the booking window is
 * returned with a status, so the picker can show unavailable ones disabled (DESIGN.md §6).
 */
class SlotService
{
    public const AVAILABLE = 'available';

    public const FULL = 'full';

    public const CLOSED = 'closed';

    /** Starts within the lead time. */
    public const TOO_SOON = 'too_soon';

    public function __construct(private Settings $settings) {}

    /**
     * Days from today through the booking window (today + booking_window_days − 1), each with its slots.
     * An area without a complete schedule has no days.
     *
     * @return list<array{date: string, closed_reason: string|null, slots: list<array{starts_at: string, ends_at: string, time: string, remaining: int, status: string}>}>
     */
    public function days(ServiceArea $area, ?CarbonImmutable $now = null): array
    {
        if (! $area->hasSchedule()) {
            return [];
        }

        $timezone = $this->settings->timezone();
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);
        $firstDay = $now->startOfDay();
        $lastDay = $firstDay->addDays($area->booking_window_days - 1);
        $earliestStart = $now->addMinutes($area->lead_time_minutes);

        $hoursByWeekday = $area->hours()->get()->groupBy('weekday');
        $closures = $this->closures($area, $firstDay, $lastDay);
        $booked = $this->bookedCounts($area, $firstDay, $lastDay->endOfDay(), $now);

        $days = [];
        for ($day = $firstDay; $day->lte($lastDay); $day = $day->addDay()) {
            $dayClosures = $closures->filter(fn (CollectionClosure $closure) => $day->betweenIncluded(
                $closure->starts_on->toDateString(), $closure->ends_on->toDateString(),
            ));
            $wholeDay = $dayClosures->first(fn (CollectionClosure $closure) => $closure->isWholeDay());

            $slots = [];
            foreach ($hoursByWeekday->get($day->isoWeekday(), []) as $range) {
                foreach ($this->slotStarts($day, $range, $area->slot_minutes) as $start) {
                    $end = $start->addMinutes($area->slot_minutes);
                    $remaining = max(0, $area->capacity_per_slot - ($booked[$start->toIso8601String()] ?? 0));

                    $slots[] = [
                        'starts_at' => $start->toIso8601String(),
                        'ends_at' => $end->toIso8601String(),
                        'time' => $start->format('H:i'),
                        'remaining' => $remaining,
                        'status' => match (true) {
                            $wholeDay !== null, $this->overlapsClosure($start, $end, $dayClosures) => self::CLOSED,
                            $start->lt($earliestStart) => self::TOO_SOON,
                            $remaining === 0 => self::FULL,
                            default => self::AVAILABLE,
                        },
                    ];
                }
            }

            usort($slots, fn (array $a, array $b) => strcmp($a['starts_at'], $b['starts_at']));

            $days[] = [
                'date' => $day->toDateString(),
                'closed_reason' => $wholeDay ? ($wholeDay->reason ?? 'Closed') : null,
                'slots' => $slots,
            ];
        }

        return $days;
    }

    /**
     * Whether a slot starting at this moment can be booked right now.
     */
    public function isBookable(ServiceArea $area, CarbonImmutable $startsAt, ?CarbonImmutable $now = null): bool
    {
        foreach ($this->days($area, $now) as $day) {
            foreach ($day['slots'] as $slot) {
                if ($slot['status'] === self::AVAILABLE && CarbonImmutable::parse($slot['starts_at'])->equalTo($startsAt)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Slot start times in one opening range, stepped by wall-clock minutes so daylight-saving
     * changes don't shift later slots.
     *
     * @return list<CarbonImmutable>
     */
    private function slotStarts(CarbonImmutable $day, ServiceAreaHour $range, int $slotMinutes): array
    {
        $opens = $this->minutesOfDay($range->opens_at);
        $closes = $this->minutesOfDay($range->closes_at);
        $starts = [];

        for ($minute = $opens; $minute + $slotMinutes <= $closes; $minute += $slotMinutes) {
            $starts[] = $day->setTime(intdiv($minute, 60), $minute % 60);
        }

        return $starts;
    }

    /**
     * @param  Collection<int, CollectionClosure>  $closures
     */
    private function overlapsClosure(CarbonImmutable $start, CarbonImmutable $end, Collection $closures): bool
    {
        return $closures->contains(function (CollectionClosure $closure) use ($start, $end) {
            if ($closure->isWholeDay()) {
                return true;
            }

            $from = $this->atTime($start, (string) $closure->starts_at);
            $to = $this->atTime($start, (string) $closure->ends_at);

            return $start->lt($to) && $end->gt($from);
        });
    }

    /**
     * Closures for this area or for all areas that touch the date range.
     *
     * @return Collection<int, CollectionClosure>
     */
    private function closures(ServiceArea $area, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return CollectionClosure::query()
            ->where(fn ($query) => $query->whereNull('service_area_id')->orWhere('service_area_id', $area->id))
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->get();
    }

    /**
     * Orders holding a place, per slot start (ISO-8601 in the site timezone). Each order is one
     * home visit, so it uses one place whatever the number of patients. Unpaid online orders stop
     * holding their place when their payment hold passes, even before they are cancelled.
     *
     * @return array<string, int>
     */
    private function bookedCounts(ServiceArea $area, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): array
    {
        return Order::query()
            ->where('service_area_id', $area->id)
            ->whereIn('status', OrderStatus::holdingSlot())
            ->where(fn ($query) => $query
                ->where('status', '!=', OrderStatus::PendingPayment)
                ->orWhere('payment_expires_at', '>', $now->utc()))
            ->whereBetween('slot_starts_at', [$from->utc(), $to->utc()])
            ->toBase()
            ->selectRaw('slot_starts_at, count(*) as booked')
            ->groupBy('slot_starts_at')
            ->get()
            ->mapWithKeys(fn (object $row) => [
                CarbonImmutable::parse($row->slot_starts_at, 'UTC')->setTimezone($from->getTimezone())->toIso8601String() => (int) $row->booked,
            ])
            ->all();
    }

    /** The given local wall-clock time on the same day as $day. */
    private function atTime(CarbonImmutable $day, string $time): CarbonImmutable
    {
        $minutes = $this->minutesOfDay($time);

        return $day->setTime(intdiv($minutes, 60), $minutes % 60);
    }

    private function minutesOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
