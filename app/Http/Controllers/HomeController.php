<?php

namespace App\Http\Controllers;

use App\Http\Resources\LabPackageResource;
use App\Models\Faq;
use App\Models\LabPackage;
use App\Settings\Settings;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page and the FAQ page, with the content admins set (B-28).
 */
class HomeController extends Controller
{
    public function show(Settings $settings): Response
    {
        $home = $settings->home();
        $ids = $home['featured_package_ids'];

        return Inertia::render('home', [
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
        return Faq::ordered()->get()->map(fn (Faq $faq) => ['id' => $faq->id, 'question' => $faq->question, 'html' => $faq->html()]);
    }
}
