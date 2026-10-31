<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBranchRequest;
use App\Http\Requests\Admin\UpdateScheduleRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\BranchHour;
use App\Services\SlotService;
use App\Settings\Settings;
use App\Support\Countries;
use App\Support\MapConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lab branches for lab visits (client, 2026-10-30), each with its own slot schedule.
 */
class BranchController extends Controller
{
    /** Branch photos are public (not personal data). */
    private const PHOTO_DISK = 'public';

    public function index(): Response
    {
        return Inertia::render('admin/branches/index', [
            'branches' => BranchResource::collection(Branch::orderBy('name')->get()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/branches/form', ['branch' => null, ...$this->formOptions()]);
    }

    public function store(SaveBranchRequest $request): RedirectResponse
    {
        $branch = Branch::create($request->branchAttributes());
        $this->savePhoto($request, $branch);

        return to_route('admin.branches.schedule.edit', $branch)->with('status', 'Branch created. Now set its opening hours and slots.');
    }

    public function edit(Branch $branch): Response
    {
        return Inertia::render('admin/branches/form', ['branch' => BranchResource::make($branch), ...$this->formOptions()]);
    }

    public function update(SaveBranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->branchAttributes());
        $this->savePhoto($request, $branch);

        return to_route('admin.branches.index')->with('status', 'Branch saved.');
    }

    /** Branches with orders stay for the order history; unpublish them instead. */
    public function destroy(Branch $branch): RedirectResponse
    {
        if ($branch->orders()->exists()) {
            return back()->withErrors(['delete' => 'This branch has bookings, so it can\'t be deleted. Unpublish it instead.']);
        }

        if ($branch->photo_path) {
            Storage::disk(self::PHOTO_DISK)->delete($branch->photo_path);
        }
        $branch->delete();

        return to_route('admin.branches.index')->with('status', 'Branch deleted.');
    }

    public function editSchedule(Branch $branch, SlotService $slots, Settings $settings): Response
    {
        return Inertia::render('admin/service-areas/schedule', [
            'owner' => ['name' => $branch->name, 'kind' => 'branch'],
            'submitUrl' => route('admin.branches.schedule.update', $branch),
            'backUrl' => route('admin.branches.index'),
            'backLabel' => 'Branches',
            'schedule' => [
                'slot_minutes' => $branch->slot_minutes,
                'capacity_per_slot' => $branch->capacity_per_slot,
                'lead_time_hours' => $branch->lead_time_minutes !== null ? $branch->lead_time_minutes / 60 : null,
                'booking_window_days' => $branch->booking_window_days,
                'hours' => $branch->hours()->get()->map(fn (BranchHour $range) => [
                    'weekday' => $range->weekday,
                    'opens_at' => substr($range->opens_at, 0, 5),
                    'closes_at' => substr($range->closes_at, 0, 5),
                ]),
            ],
            'timezone' => $settings->timezone(),
            'preview' => $slots->days($branch),
        ]);
    }

    public function updateSchedule(UpdateScheduleRequest $request, Branch $branch): RedirectResponse
    {
        DB::transaction(function () use ($request, $branch) {
            $branch->update($request->scheduleAttributes());
            $branch->hours()->delete();
            $branch->hours()->createMany($request->validated('hours', []));
        });

        return to_route('admin.branches.schedule.edit', $branch)->with('status', 'Schedule saved.');
    }

    private function savePhoto(SaveBranchRequest $request, Branch $branch): void
    {
        $old = $branch->photo_path;
        if ($request->hasFile('photo')) {
            $branch->update(['photo_path' => $request->file('photo')->store('branches', self::PHOTO_DISK)]);
        } elseif ($request->boolean('remove_photo')) {
            $branch->update(['photo_path' => null]);
        }

        if ($old && $old !== $branch->photo_path) {
            Storage::disk(self::PHOTO_DISK)->delete($old);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'countries' => collect(Countries::all())->map(fn (string $name, string $code) => ['value' => $code, 'label' => $name])->values(),
            'map' => MapConfig::toArray(),
        ];
    }
}
