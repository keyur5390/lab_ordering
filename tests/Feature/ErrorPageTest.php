<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Throwaway routes that fail with a given status, registered with the normal web stack.
    Route::middleware('web')->get('/_test/abort/{status}', fn (int $status) => abort($status));
    Route::middleware('web')->get('/_test/exception', fn () => throw new RuntimeException('Boom'));
});

it('renders the error page for an unknown URL', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404)
            ->where('name', config('app.name'))
        );
});

it('renders the error page when a customer opens the admin area', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
});

it('renders the error page for client errors, even with debug on', function (int $status) {
    config(['app.debug' => true]);

    $this->get("/_test/abort/{$status}")
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', $status));
})->with([403, 404, 419, 429]);

it('renders the error page for server errors when debug is off', function (string $uri, int $status) {
    config(['app.debug' => false]);

    $this->get($uri)
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', $status));
})->with([
    'unhandled exception' => ['/_test/exception', 500],
    'service unavailable' => ['/_test/abort/503', 503],
]);

it("keeps Laravel's debug page for server errors when debug is on", function () {
    config(['app.debug' => true]);

    $this->get('/_test/exception')
        ->assertStatus(500)
        ->assertSee('Boom')
        ->assertDontSee('<div id="app" data-page=', false);
});

it('responds to Inertia visits with the error page component', function () {
    $this->get('/this-page-does-not-exist', [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertNotFound()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'error')
        ->assertJsonPath('props.status', 404);
});

it('keeps JSON errors for JSON requests', function () {
    $this->getJson('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertHeaderMissing('X-Inertia')
        ->assertJsonStructure(['message']);
});
