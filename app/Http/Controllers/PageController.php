<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Settings\Settings;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages of site copy written by admins (privacy policy, terms).
 */
class PageController extends Controller
{
    public function show(string $slug, Settings $settings): Response
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return Inertia::render('page', [
            'page' => ['slug' => $page->slug, 'title' => $page->title, 'html' => $page->html(),
                // Plain-text start of the page, for search results.
                'summary' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($page->html()))), 200), 'updated_on' => $page->updated_at?->setTimezone($settings->timezone())->toDateString()],
            // The privacy policy always ends with who to contact about your data.
            'privacyContact' => $slug === 'privacy' ? $settings->privacyContact() : null,
        ]);
    }
}
