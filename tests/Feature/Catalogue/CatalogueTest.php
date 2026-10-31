<?php

use App\Enums\DiscountType;
use App\Models\LabPackage;
use App\Models\LabTest;
use Inertia\Testing\AssertableInertia as Assert;

it('lists published packages and tests for guests', function () {
    LabTest::factory()->create(['name' => 'Visible test']);
    LabTest::factory()->inactive()->create(['name' => 'Hidden test']);
    $tests = LabTest::factory()->count(2)->create();
    LabPackage::factory()->create(['name' => 'Visible package'])->tests()->attach($tests->pluck('id'));
    LabPackage::factory()->inactive()->create(['name' => 'Hidden package']);

    $this->get(route('catalogue.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalogue/index')
            ->has('tests', 3)
            ->where('tests', fn ($tests) => collect($tests)->doesntContain('name', 'Hidden test'))
            ->has('packages', 1)
            ->where('packages.0.name', 'Visible package')
            ->where('packages.0.tests_count', 2)
        );
});

it('shows a published test', function () {
    $test = LabTest::factory()->create(['preparation' => 'Fast for 10 hours.']);

    $this->get(route('catalogue.tests.show', $test))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalogue/test')
            ->where('test.id', $test->id)
            ->where('test.preparation', 'Fast for 10 hours.')
        );
});

it('hides unpublished tests and packages', function () {
    $this->get(route('catalogue.tests.show', LabTest::factory()->inactive()->create()))->assertNotFound();
    $this->get(route('catalogue.packages.show', LabPackage::factory()->inactive()->create()))->assertNotFound();
});

it('shows a package with its tests and computed price', function () {
    $package = LabPackage::factory()->discounted(DiscountType::Percent, 10)->create();
    $package->tests()->attach(LabTest::factory()->count(2)->sequence(
        ['price_amount' => 30000, 'turnaround_hours' => 24],
        ['price_amount' => 20000, 'turnaround_hours' => 48],
    )->create()->pluck('id'));

    $this->get(route('catalogue.packages.show', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalogue/package')
            ->has('package.tests', 2)
            ->where('package.tests_total_amount', 50000)
            ->where('package.price_amount', 45000)
            ->where('package.turnaround_hours', 48)
        );
});

it('returns 404 for an unknown slug', function () {
    $this->get('/tests/does-not-exist')->assertNotFound();
});
