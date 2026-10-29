<?php

use App\Enums\Currency;
use App\Models\User;
use App\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;

it('has no currency until an admin chooses one', function () {
    expect(app(Settings::class)->currency())->toBeNull();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('currency', null));
});

it('stores the currency and clears the cache', function () {
    $settings = app(Settings::class);
    expect($settings->currency())->toBeNull(); // warms the cache

    $settings->setCurrency(Currency::GBP);
    expect($settings->currency())->toBe(Currency::GBP);

    $settings->setCurrency(Currency::EUR);
    expect($settings->currency())->toBe(Currency::EUR);
    $this->assertDatabaseCount('settings', 1);
});

it('shares the currency with every page', function () {
    app(Settings::class)->setCurrency(Currency::INR);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('currency', ['code' => 'INR', 'decimals' => 2])
    );
});

it('shows the settings page to admins', function () {
    app(Settings::class)->setCurrency(Currency::USD);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings')
            ->where('currency', 'USD')
            ->has('currencies', count(Currency::cases()))
        );
});

it('lets admins change the currency', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.update'), ['currency' => 'AED', 'timezone' => 'Asia/Dubai'])
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('status');

    expect(app(Settings::class)->currency())->toBe(Currency::AED)
        ->and(app(Settings::class)->timezone())->toBe('Asia/Dubai');
});

it('falls back to the app timezone until one is chosen', function () {
    expect(app(Settings::class)->timezone())->toBe(config('app.timezone'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('timezone', config('app.timezone'))->has('timezones'));
});

it('rejects unknown timezones', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.update'), ['currency' => 'USD', 'timezone' => 'Mars/Olympus'])
        ->assertSessionHasErrors('timezone');
});

it('rejects unsupported currencies', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.update'), ['currency' => 'XYZ', 'timezone' => 'UTC'])
        ->assertSessionHasErrors('currency');

    expect(app(Settings::class)->currency())->toBeNull();
});

it('forbids customers from changing settings', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('admin.settings.update'), ['currency' => 'USD', 'timezone' => 'UTC'])
        ->assertForbidden();

    expect(app(Settings::class)->currency())->toBeNull();
});
