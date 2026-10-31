<?php

use App\Models\CollectionClosure;
use App\Models\ServiceArea;
use App\Services\SlotService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;

beforeEach(function () {
    app(Settings::class)->setTimezone('Asia/Kolkata');
    $this->slots = app(SlotService::class);
    // Thursday 29 Oct 2026, 06:00 in Kolkata.
    $this->now = CarbonImmutable::parse('2026-10-29 06:00', 'Asia/Kolkata');
});

/**
 * @return array<string, string> time => status for one date
 */
function slotsOn(array $days, string $date): array
{
    $day = collect($days)->firstWhere('date', $date);

    return collect($day['slots'])->mapWithKeys(fn (array $slot) => [$slot['time'] => $slot['status']])->all();
}

it('steps opening hours by the slot length, in the site timezone', function () {
    $area = ServiceArea::factory()->scheduled(slotMinutes: 45, opens: '08:00', closes: '10:30')->create();

    $days = $this->slots->days($area, $this->now);
    $first = $days[0]['slots'][0];

    expect(array_keys(slotsOn($days, '2026-10-29')))->toBe(['08:00', '08:45', '09:30'])
        ->and($first['starts_at'])->toBe('2026-10-29T08:00:00+05:30')
        ->and($first['ends_at'])->toBe('2026-10-29T08:45:00+05:30')
        ->and($first['remaining'])->toBe(2);
});

it('covers today through the booking window', function () {
    $area = ServiceArea::factory()->scheduled(windowDays: 3)->create();

    expect(array_column($this->slots->days($area, $this->now), 'date'))->toBe(['2026-10-29', '2026-10-30', '2026-10-31']);
});

it('supports several ranges per day and closed weekdays', function () {
    $area = ServiceArea::factory()->create([
        'slot_minutes' => 60, 'capacity_per_slot' => 1, 'lead_time_minutes' => 0, 'booking_window_days' => 2,
    ]);
    // Thursday only: morning and evening.
    $area->hours()->createMany([
        ['weekday' => 4, 'opens_at' => '17:00', 'closes_at' => '19:00'],
        ['weekday' => 4, 'opens_at' => '07:00', 'closes_at' => '09:00'],
    ]);

    $days = $this->slots->days($area, $this->now);

    expect(array_keys(slotsOn($days, '2026-10-29')))->toBe(['07:00', '08:00', '17:00', '18:00'])
        ->and($days[1]['slots'])->toBe([]);
});

it('marks slots inside the lead time as too soon', function () {
    $area = ServiceArea::factory()->scheduled(leadMinutes: 150)->create();

    // Now 06:00 + 2.5 h = 08:30, so 08:00 is too soon and 09:00 is the first bookable slot.
    expect(slotsOn($this->slots->days($area, $this->now), '2026-10-29'))
        ->toBe(['08:00' => 'too_soon', '09:00' => 'available', '10:00' => 'available', '11:00' => 'available']);
});

it('closes whole days, including date ranges and closures for all areas', function () {
    $area = ServiceArea::factory()->scheduled()->create();
    CollectionClosure::factory()->create(['starts_on' => '2026-10-30', 'ends_on' => '2026-10-31', 'reason' => 'Festival']);

    $days = collect($this->slots->days($area, $this->now))->keyBy('date');

    expect($days['2026-10-30']['closed_reason'])->toBe('Festival')
        ->and(array_unique(array_values(slotsOn($days->all(), '2026-10-31'))))->toBe(['closed'])
        ->and($days['2026-11-01']['closed_reason'])->toBeNull();
});

it('closes only the slots that overlap a part-day closure', function () {
    $area = ServiceArea::factory()->scheduled()->create();
    CollectionClosure::factory()->create([
        'service_area_id' => $area->id, 'starts_on' => '2026-10-29', 'ends_on' => '2026-10-29',
        'starts_at' => '09:30', 'ends_at' => '10:15',
    ]);

    expect(slotsOn($this->slots->days($area, $this->now), '2026-10-29'))
        ->toBe(['08:00' => 'available', '09:00' => 'closed', '10:00' => 'closed', '11:00' => 'available']);
});

it("ignores another area's closures", function () {
    $area = ServiceArea::factory()->scheduled()->create();
    CollectionClosure::factory()->create(['service_area_id' => ServiceArea::factory()->create()->id, 'starts_on' => '2026-10-29', 'ends_on' => '2026-10-29']);

    expect(array_unique(array_values(slotsOn($this->slots->days($area, $this->now), '2026-10-29'))))->toBe(['available']);
});

it('offers no slots for an area without a complete schedule', function () {
    $area = ServiceArea::factory()->scheduled()->create();
    $area->update(['capacity_per_slot' => null]);

    expect($this->slots->days($area, $this->now))->toBe([]);
});

it('keeps wall-clock slot times across a daylight-saving change', function () {
    app(Settings::class)->setTimezone('Europe/London');
    $area = ServiceArea::factory()->scheduled(windowDays: 3)->create();
    // UK clocks go back at 02:00 on Sunday 25 Oct 2026.
    $now = CarbonImmutable::parse('2026-10-24 06:00', 'Europe/London');

    $days = $this->slots->days($area, $now);

    expect(array_keys(slotsOn($days, '2026-10-24')))->toBe(['08:00', '09:00', '10:00', '11:00'])
        ->and(array_keys(slotsOn($days, '2026-10-25')))->toBe(['08:00', '09:00', '10:00', '11:00'])
        ->and(collect($days)->firstWhere('date', '2026-10-24')['slots'][0]['starts_at'])->toBe('2026-10-24T08:00:00+01:00')
        ->and(collect($days)->firstWhere('date', '2026-10-25')['slots'][0]['starts_at'])->toBe('2026-10-25T08:00:00+00:00');
});

it('says whether a given slot can be booked', function () {
    $area = ServiceArea::factory()->scheduled(leadMinutes: 150)->create();

    expect($this->slots->isBookable($area, CarbonImmutable::parse('2026-10-29T09:00:00+05:30'), $this->now))->toBeTrue()
        ->and($this->slots->isBookable($area, CarbonImmutable::parse('2026-10-29T08:00:00+05:30'), $this->now))->toBeFalse()
        ->and($this->slots->isBookable($area, CarbonImmutable::parse('2026-10-29T09:15:00+05:30'), $this->now))->toBeFalse();
});
