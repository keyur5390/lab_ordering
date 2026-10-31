<?php

use App\Models\CollectionClosure;
use App\Models\ServiceArea;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

function schedulePayload(array $overrides = []): array
{
    return [
        'slot_minutes' => 30,
        'capacity_per_slot' => 3,
        'lead_time_hours' => 1.5,
        'booking_window_days' => 10,
        'hours' => [
            ['weekday' => 1, 'opens_at' => '07:00', 'closes_at' => '11:00'],
            ['weekday' => 1, 'opens_at' => '17:00', 'closes_at' => '19:00'],
            ['weekday' => 6, 'opens_at' => '08:00', 'closes_at' => '12:00'],
        ],
        ...$overrides,
    ];
}

it('shows the schedule with a slot preview', function () {
    $area = ServiceArea::factory()->scheduled()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.service-areas.schedule.edit', $area))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/service-areas/schedule')
            ->where('schedule.slot_minutes', 60)
            ->has('schedule.hours', 7)
            ->where('schedule.hours.0.opens_at', '08:00')
            ->has('preview', 7)
            ->has('timezone')
        );
});

it('saves the schedule, converting lead time to minutes and replacing hours', function () {
    $area = ServiceArea::factory()->scheduled()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.service-areas.schedule.update', $area), schedulePayload())
        ->assertRedirect(route('admin.service-areas.schedule.edit', $area));

    $area->refresh();
    expect($area->slot_minutes)->toBe(30)
        ->and($area->capacity_per_slot)->toBe(3)
        ->and($area->lead_time_minutes)->toBe(90)
        ->and($area->booking_window_days)->toBe(10)
        ->and($area->hours()->count())->toBe(3)
        ->and($area->hasSchedule())->toBeTrue();
});

it('rejects invalid schedules', function (array $overrides, string $field) {
    $area = ServiceArea::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.service-areas.schedule.update', $area), schedulePayload($overrides))
        ->assertSessionHasErrors($field);

    expect($area->fresh()->hasSchedule())->toBeFalse();
})->with([
    'missing slot length' => [['slot_minutes' => ''], 'slot_minutes'],
    'zero capacity' => [['capacity_per_slot' => 0], 'capacity_per_slot'],
    'negative lead time' => [['lead_time_hours' => -1], 'lead_time_hours'],
    'zero window' => [['booking_window_days' => 0], 'booking_window_days'],
    'bad weekday' => [['hours' => [['weekday' => 8, 'opens_at' => '07:00', 'closes_at' => '08:00']]], 'hours.0.weekday'],
    'bad time' => [['hours' => [['weekday' => 1, 'opens_at' => '7am', 'closes_at' => '08:00']]], 'hours.0.opens_at'],
    'closes before opens' => [['hours' => [['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '08:00']]], 'hours.0.closes_at'],
    'overlapping ranges' => [['hours' => [
        ['weekday' => 2, 'opens_at' => '07:00', 'closes_at' => '10:00'],
        ['weekday' => 2, 'opens_at' => '09:00', 'closes_at' => '12:00'],
    ]], 'hours.1.opens_at'],
]);

it('allows touching ranges and the same times on different days', function () {
    $area = ServiceArea::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.service-areas.schedule.update', $area), schedulePayload(['hours' => [
            ['weekday' => 2, 'opens_at' => '07:00', 'closes_at' => '10:00'],
            ['weekday' => 2, 'opens_at' => '10:00', 'closes_at' => '12:00'],
            ['weekday' => 3, 'opens_at' => '07:00', 'closes_at' => '10:00'],
        ]]))
        ->assertSessionHasNoErrors();
});

it('lists upcoming closures and adds whole-day and part-day ones', function () {
    $area = ServiceArea::factory()->create(['name' => 'North']);
    CollectionClosure::factory()->create(['starts_on' => now()->subDays(5)->toDateString(), 'ends_on' => now()->subDays(4)->toDateString()]);

    $this->actingAs($this->admin)
        ->get(route('admin.closures.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/closures')->has('closures', 0)->has('areas', 1));

    $date = now()->addDays(2)->toDateString();
    $this->actingAs($this->admin)->post(route('admin.closures.store'), [
        'service_area_id' => '', 'starts_on' => $date, 'ends_on' => '', 'whole_day' => true, 'reason' => 'Holiday',
    ])->assertRedirect(route('admin.closures.index'));
    $this->actingAs($this->admin)->post(route('admin.closures.store'), [
        'service_area_id' => $area->id, 'starts_on' => $date, 'whole_day' => false, 'starts_at' => '13:00', 'ends_at' => '15:30',
    ])->assertSessionHasNoErrors();

    [$whole, $partial] = CollectionClosure::whereDate('starts_on', $date)->orderBy('id')->get();
    expect($whole->service_area_id)->toBeNull()
        ->and($whole->ends_on->toDateString())->toBe($date)
        ->and($whole->isWholeDay())->toBeTrue()
        ->and($partial->service_area_id)->toBe($area->id)
        ->and(substr($partial->ends_at, 0, 5))->toBe('15:30');
});

it('rejects invalid closures', function (array $data, string $field) {
    $this->actingAs($this->admin)
        ->post(route('admin.closures.store'), ['whole_day' => true, 'starts_on' => '2026-12-25', ...$data])
        ->assertSessionHasErrors($field);

    expect(CollectionClosure::count())->toBe(0);
})->with([
    'missing date' => [['starts_on' => ''], 'starts_on'],
    'end before start' => [['ends_on' => '2026-12-24'], 'ends_on'],
    'unknown area' => [['service_area_id' => 999], 'service_area_id'],
    'part day without times' => [['whole_day' => false], 'starts_at'],
    'part day end before start' => [['whole_day' => false, 'starts_at' => '15:00', 'ends_at' => '14:00'], 'ends_at'],
    'part day across dates' => [['whole_day' => false, 'starts_at' => '10:00', 'ends_at' => '11:00', 'ends_on' => '2026-12-26'], 'ends_on'],
]);

it('removes a closure', function () {
    $closure = CollectionClosure::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.closures.destroy', $closure))->assertRedirect(route('admin.closures.index'));

    $this->assertModelMissing($closure);
});

it('forbids customers from managing schedules and closures', function (string $method, Closure $url) {
    $area = ServiceArea::factory()->create();
    $closure = CollectionClosure::factory()->create();

    $this->actingAs(User::factory()->create())
        ->{$method}($url($area, $closure), schedulePayload())
        ->assertForbidden();

    $this->assertModelExists($closure);
})->with([
    'schedule edit' => ['get', fn ($area) => route('admin.service-areas.schedule.edit', $area)],
    'schedule update' => ['put', fn ($area) => route('admin.service-areas.schedule.update', $area)],
    'closures index' => ['get', fn () => route('admin.closures.index')],
    'closures store' => ['post', fn () => route('admin.closures.store')],
    'closures destroy' => ['delete', fn ($area, $closure) => route('admin.closures.destroy', $closure)],
]);
