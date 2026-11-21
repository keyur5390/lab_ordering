<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\ServiceArea;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public homepage: featured tests, packages, stats and service areas
 * (client, 2026-11-07).
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $data = Cache::remember('homepage-data', 3600, function () {
            return [
                'featured_tests' => LabTest::active()->with('categories')
                    ->orderBy('price_amount')
                    ->limit(6)
                    ->get()
                    ->map(fn ($test) => $test->only(['id', 'name', 'code', 'slug', 'description', 'sample_type', 'preparation', 'requires_prescription', 'turnaround_hours', 'price_amount', 'categories'])),

                'featured_packages' => LabPackage::active()->with(['tests', 'categories'])
                    ->limit(3)
                    ->get()
                    ->map(fn ($pkg) => $pkg->only(['id', 'name', 'code', 'slug', 'description', 'price_amount', 'tests_total_amount', 'turnaround_hours', 'tests_count', 'categories'])),

                'stats' => [
                    'tests' => LabTest::active()->count(),
                    'packages' => LabPackage::active()->count(),
                    'service_areas' => ServiceArea::active()->count(),
                    'branches' => Branch::where('is_active', true)->count(),
                ],

                'service_areas' => ServiceArea::active()
                    ->limit(4)
                    ->get()
                    ->map(fn ($area) => $area->only(['id', 'name', 'city', 'collection_fee_amount'])),
            ];
        });

        return Inertia::render('home', $data);
    }
}
