<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Http\Resources\LabPackageResource;
use App\Models\Faq;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\ServiceArea;
use App\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public homepage: featured tests, packages, stats and service areas
 * (client, 2026-11-07).
 */
class HomeController extends Controller
{
    public function show(Settings $settings): Response
    {
        $home = $settings->home();
        $ids = $home['featured_package_ids'];

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

        return Inertia::render('home', [
            ...$data,
            'hero' => ['title' => $home['hero_title'], 'text' => $home['hero_text'], 'button' => $home['hero_button']],
            // Unpublished packages drop out; the rest keep the admin's order.
            'featured' => LabPackageResource::collection(
                LabPackage::active()->whereIn('id', $ids)->with(['tests', 'categories'])->get()
                    ->sortBy(fn (LabPackage $package) => array_search($package->id, $ids, true))->values(),
            ),
            'faqs' => $this->faqs(),
        ]);
    }

    public function faq(): Response
    {
        return Inertia::render('faq', ['faqs' => $this->faqs()]);
    }

    /**
     * @return Collection<int, array{id: int, question: string, html: string}>
     */
    private function faqs(): Collection
    {
        return Faq::ordered()->get()->map(fn (Faq $faq) => [
            'id' => $faq->id,
            'question' => $faq->question,
            'html' => $faq->html(),
        ]);
    }
}
