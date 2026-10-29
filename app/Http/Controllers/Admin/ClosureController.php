<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClosureRequest;
use App\Models\CollectionClosure;
use App\Models\ServiceArea;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Holidays and other closures (client, 2026-10-29: whole or part days; one area or all).
 */
class ClosureController extends Controller
{
    public function index(Settings $settings): Response
    {
        $today = CarbonImmutable::now($settings->timezone())->toDateString();

        return Inertia::render('admin/closures', [
            // Current and upcoming closures; past ones are kept but not listed.
            'closures' => CollectionClosure::with('serviceArea:id,name')
                ->whereDate('ends_on', '>=', $today)
                ->orderBy('starts_on')
                ->orderBy('starts_at')
                ->get()
                ->map(fn (CollectionClosure $closure) => [
                    'id' => $closure->id,
                    'area' => $closure->serviceArea?->name,
                    'starts_on' => $closure->starts_on->toDateString(),
                    'ends_on' => $closure->ends_on->toDateString(),
                    'starts_at' => $closure->starts_at ? substr($closure->starts_at, 0, 5) : null,
                    'ends_at' => $closure->ends_at ? substr($closure->ends_at, 0, 5) : null,
                    'reason' => $closure->reason,
                ]),
            'areas' => ServiceArea::orderBy('name')->get(['id', 'name'])
                ->map(fn (ServiceArea $area) => ['value' => (string) $area->id, 'label' => $area->name]),
            'today' => $today,
        ]);
    }

    public function store(StoreClosureRequest $request): RedirectResponse
    {
        CollectionClosure::create($request->closureAttributes());

        return to_route('admin.closures.index')->with('status', 'Closure added.');
    }

    public function destroy(CollectionClosure $closure): RedirectResponse
    {
        $closure->delete();

        return to_route('admin.closures.index')->with('status', 'Closure removed.');
    }
}
