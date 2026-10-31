<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveLabPackageRequest;
use App\Http\Resources\LabPackageResource;
use App\Http\Resources\LabTestResource;
use App\Models\Category;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Settings\Settings;
use App\Support\Money;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LabPackageController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', LabPackage::class);

        return Inertia::render('admin/packages/index', [
            'packages' => LabPackageResource::collection(LabPackage::with('tests')->orderBy('name')->paginate(25)),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', LabPackage::class);

        return Inertia::render('admin/packages/form', [
            'package' => null,
            'form' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveLabPackageRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $package = LabPackage::create([
                ...$request->modelAttributes(),
                'slug' => Slug::unique(LabPackage::class, $request->validated('name')),
            ]);
            $package->tests()->sync($request->testIds());
            $package->categories()->sync($request->categoryIds());
        });

        return to_route('admin.packages.index')->with('status', 'Package created.');
    }

    public function edit(LabPackage $package, Settings $settings): Response
    {
        Gate::authorize('update', $package);

        $package->load(['tests', 'categories']);
        $currency = $settings->currency();

        return Inertia::render('admin/packages/form', [
            'package' => LabPackageResource::make($package),
            // Current values as the form edits them (decimal strings for money).
            'form' => [
                'price' => $package->price_amount === null ? '' : Money::toDecimal($package->price_amount, $currency),
                'discount_value' => match ($package->discount_type) {
                    DiscountType::Amount => Money::toDecimal((int) $package->discount_value, $currency),
                    DiscountType::Percent => (string) $package->discount_value,
                    null => '',
                },
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveLabPackageRequest $request, LabPackage $package): RedirectResponse
    {
        DB::transaction(function () use ($request, $package) {
            $package->update($request->modelAttributes());
            $package->tests()->sync($request->testIds());
            $package->categories()->sync($request->categoryIds());
        });

        return to_route('admin.packages.index')->with('status', 'Package saved.');
    }

    public function destroy(LabPackage $package): RedirectResponse
    {
        Gate::authorize('delete', $package);

        $package->delete();

        return to_route('admin.packages.index')->with('status', 'Package deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'tests' => LabTestResource::collection(LabTest::orderBy('name')->get()),
            'minTests' => SaveLabPackageRequest::MIN_TESTS,
            'categories' => Category::orderBy('name')->get(['id', 'name'])->map->only(['id', 'name'])->all(),
        ];
    }
}
