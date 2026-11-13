<?php

use App\Models\LabPackage;
use App\Models\LabTest;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

describe('security headers', function () {
    it('sends them on every page', function () {
        $this->get(route('home'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    });

    it('asks browsers to stay on HTTPS in production', function () {
        app()->detectEnvironment(fn () => 'production');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    });
});

describe('search engines', function () {
    it('lists public pages and published tests and packages in the sitemap', function () {
        $test = LabTest::factory()->create(['slug' => 'cbc']);
        LabTest::factory()->inactive()->create(['slug' => 'hidden-test']);
        $package = LabPackage::factory()->create(['slug' => 'full-body']);

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.route('home').'</loc>', false)
            ->assertSee('<loc>'.route('pages.show', 'privacy').'</loc>', false)
            ->assertSee('<loc>'.route('catalogue.tests.show', $test->slug).'</loc><lastmod>'.$test->updated_at->toDateString().'</lastmod>', false)
            ->assertSee(route('catalogue.packages.show', $package->slug), false)
            ->assertDontSee('hidden-test');
    });

    it('keeps private areas out and points to the sitemap', function () {
        $this->get('/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("Disallow: /admin\n", false)
            ->assertSee("Disallow: /account\n", false)
            ->assertSee('Sitemap: '.route('sitemap'), false);
    });

    it('gives pages a plain-text summary for their description', function () {
        $this->get(route('pages.show', 'privacy'))
            ->assertSee('inertia="description"', false)
            ->assertInertia(fn (Assert $page) => $page->where('page.summary', fn (string $summary) => str_starts_with($summary, 'This page is a placeholder.') && ! str_contains($summary, '<')));
    });
});

it('trusts the proxies set in TRUSTED_PROXIES', function () {
    config(['app.trusted_proxies' => '10.0.0.1, 10.0.0.2']);
    (new AppServiceProvider(app()))->boot();
    Route::get('/_scheme', fn () => request()->isSecure() ? 'https' : 'http')->middleware(TrustProxies::class);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->withHeader('X-Forwarded-Proto', 'https')->get('/_scheme')->assertSee('https');
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->withHeader('X-Forwarded-Proto', 'https')->get('/_scheme')->assertSee('http');
})->after(fn () => TrustProxies::flushState());
