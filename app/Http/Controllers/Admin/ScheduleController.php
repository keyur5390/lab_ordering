<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateScheduleRequest;
use App\Models\ServiceArea;
use App\Models\ServiceAreaHour;
use App\Services\SlotService;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A service area's collection schedule (client, 2026-10-29: per area), with a preview of its slots.
 */
class ScheduleController extends Controller
{
    public function edit(ServiceArea $serviceArea, SlotService $slots, Settings $settings): Response
    {
        Gate::authorize('update', $serviceArea);

        return Inertia::render('admin/service-areas/schedule', [
            'area' => ['id' => $serviceArea->id, 'name' => $serviceArea->name],
            'schedule' => [
                'slot_minutes' => $serviceArea->slot_minutes,
                'capacity_per_slot' => $serviceArea->capacity_per_slot,
                'lead_time_hours' => $serviceArea->lead_time_minutes !== null ? $serviceArea->lead_time_minutes / 60 : null,
                'booking_window_days' => $serviceArea->booking_window_days,
                'hours' => $serviceArea->hours()->get()->map(fn (ServiceAreaHour $range) => [
                    'weekday' => $range->weekday,
                    'opens_at' => substr($range->opens_at, 0, 5),
                    'closes_at' => substr($range->closes_at, 0, 5),
                ]),
            ],
            'timezone' => $settings->timezone(),
            'preview' => $slots->days($serviceArea),
        ]);
    }

    public function update(UpdateScheduleRequest $request, ServiceArea $serviceArea): RedirectResponse
    {
        DB::transaction(function () use ($request, $serviceArea) {
            $serviceArea->update($request->scheduleAttributes());
            $serviceArea->hours()->delete();
            $serviceArea->hours()->createMany($request->validated('hours', []));
        });

        return to_route('admin.service-areas.schedule.edit', $serviceArea)->with('status', 'Schedule saved.');
    }
}
