<?php

namespace App\Http\Controllers;

use App\Http\Resources\LabPackageResource;
use App\Http\Resources\LabTestResource;
use App\Models\Category;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Services\CatalogueService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public catalogue: published packages and tests only, with search, categories, filters and sorting
 * (client, 2026-10-31).
 */
class CatalogueController extends Controller
{
    public function index(Request $request, CatalogueService $catalogue): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(CatalogueService::TYPES)],
            'no_fasting' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(CatalogueService::SORTS)],
        ]);
        $category = isset($filters['category']) ? Category::where('slug', $filters['category'])->first() : null;

        $results = $catalogue->search([
            'q' => $filters['q'] ?? null,
            'category' => $category,
            'type' => $filters['type'] ?? null,
            'no_fasting' => $request->boolean('no_fasting'),
            'sort' => $filters['sort'] ?? 'name',
        ]);

        return Inertia::render('catalogue/index', [
            'packages' => LabPackageResource::collection($results['packages']),
            'tests' => LabTestResource::collection($results['tests']),
            // Only categories with something published in them.
            'categories' => Category::where(fn ($query) => $query
                ->whereHas('tests', fn ($tests) => $tests->where('is_active', true))
                ->orWhereHas('packages', fn ($packages) => $packages->where('is_active', true)))
                ->orderBy('name')->get(['name', 'slug']),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'category' => $category?->slug,
                'type' => $filters['type'] ?? null,
                'no_fasting' => $request->boolean('no_fasting'),
                'sort' => $filters['sort'] ?? 'name',
            ],
        ]);
    }

    public function showTest(LabTest $test): Response
    {
        abort_unless($test->is_active, 404);

        return Inertia::render('catalogue/test', [
            'test' => LabTestResource::make($test->load('categories')),
        ]);
    }

    public function showPackage(LabPackage $package): Response
    {
        abort_unless($package->is_active, 404);

        return Inertia::render('catalogue/package', [
            'package' => LabPackageResource::make($package->load(['tests', 'categories'])),
        ]);
    }
}
