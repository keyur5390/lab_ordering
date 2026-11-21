<?php

namespace App\Http\Controllers;

use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\Page;
use Illuminate\Http\Response;

/**
 * Search engines (B-33): the sitemap of public pages, and robots.txt, which keeps private areas out
 * and points to the sitemap.
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('catalogue.index'), 'lastmod' => null],
            ['loc' => route('faq'), 'lastmod' => null],
        ])
            ->concat(Page::whereIn('slug', Page::SLUGS)->get()->map(fn (Page $page) => ['loc' => route('pages.show', $page->slug), 'lastmod' => $page->updated_at]))
            ->concat(LabPackage::active()->orderBy('name')->get()->map(fn (LabPackage $package) => ['loc' => route('catalogue.packages.show', $package->slug), 'lastmod' => $package->updated_at]))
            ->concat(LabTest::active()->orderBy('name')->get()->map(fn (LabTest $test) => ['loc' => route('catalogue.tests.show', $test->slug), 'lastmod' => $test->updated_at]));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>'.($url['lastmod'] ? '<lastmod>'.$url['lastmod']->toDateString().'</lastmod>' : '')."</url>\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $private = ['/admin', '/account', '/cart', '/checkout', '/collector', '/login', '/register', '/forgot-password', '/reset-password', '/email', '/user', '/webhooks'];
        $lines = ['User-agent: *', ...array_map(fn (string $path) => "Disallow: {$path}", $private), '', 'Sitemap: '.route('sitemap')];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
