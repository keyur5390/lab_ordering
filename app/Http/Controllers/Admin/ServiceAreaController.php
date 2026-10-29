<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceAreaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveServiceAreaRequest;
use App\Http\Resources\ServiceAreaResource;
use App\Models\ServiceArea;
use App\Services\ServiceAreaService;
use App\Support\Countries;
use App\Support\MapConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where home collection is available (PROJECT.md §6).
 */
class ServiceAreaController extends Controller
{
    public function __construct(private ServiceAreaService $areas) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', ServiceArea::class);

        return Inertia::render('admin/service-areas/index', [
            'areas' => ServiceAreaResource::collection(ServiceArea::withCount('postcodes')->orderBy('name')->get()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ServiceArea::class);

        return Inertia::render('admin/service-areas/form', ['area' => null, ...$this->formOptions()]);
    }

    public function store(SaveServiceAreaRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $area = ServiceArea::create($request->modelAttributes());
            $this->areas->syncPostcodes($area, $request->postcodes());
        });

        return to_route('admin.service-areas.index')->with('status', 'Service area created.');
    }

    public function edit(ServiceArea $serviceArea): Response
    {
        Gate::authorize('update', $serviceArea);

        return Inertia::render('admin/service-areas/form', [
            'area' => ServiceAreaResource::make($serviceArea->load('postcodes')),
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveServiceAreaRequest $request, ServiceArea $serviceArea): RedirectResponse
    {
        DB::transaction(function () use ($request, $serviceArea) {
            $serviceArea->update($request->modelAttributes());
            $this->areas->syncPostcodes($serviceArea, $request->postcodes());
        });

        return to_route('admin.service-areas.index')->with('status', 'Service area saved.');
    }

    public function destroy(ServiceArea $serviceArea): RedirectResponse
    {
        Gate::authorize('delete', $serviceArea);

        $serviceArea->delete();

        return to_route('admin.service-areas.index')->with('status', 'Service area deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'types' => array_map(fn (ServiceAreaType $type) => ['value' => $type->value, 'label' => $type->label()], ServiceAreaType::cases()),
            'countries' => collect(Countries::all())->map(fn (string $name, string $code) => ['value' => $code, 'label' => $name])->values(),
            'map' => MapConfig::toArray(),
        ];
    }
}
