<?php

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\PackagePricingMode;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\User;
use App\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->setCurrency(Currency::INR);
    $this->admin = User::factory()->admin()->create();
    $this->tests = LabTest::factory()->count(3)->sequence(
        ['price_amount' => 40000],
        ['price_amount' => 60000],
        ['price_amount' => 15000],
    )->create();
});

function validPackage(array $testIds, array $overrides = []): array
{
    return [
        'name' => 'Basic Health Check',
        'code' => 'basic',
        'description' => '',
        'is_active' => true,
        'test_ids' => $testIds,
        'pricing_mode' => 'fixed',
        'price' => '999',
        ...$overrides,
    ];
}

it('lists packages with their computed prices', function () {
    LabPackage::factory()->discounted(DiscountType::Percent, 10)->create()->tests()->attach($this->tests->pluck('id'));

    $this->actingAs($this->admin)
        ->get(route('admin.packages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/packages/index')
            ->has('packages.data', 1)
            ->where('packages.data.0.tests_total_amount', 115000)
            ->where('packages.data.0.price_amount', 103500)
        );
});

it('creates a fixed-price package', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.packages.store'), validPackage($this->tests->pluck('id')->all()))
        ->assertRedirect(route('admin.packages.index'))
        ->assertSessionHas('status', 'Package created.');

    $package = LabPackage::sole();
    expect($package->code)->toBe('BASIC')
        ->and($package->slug)->toBe('basic-health-check')
        ->and($package->pricing_mode)->toBe(PackagePricingMode::Fixed)
        ->and($package->price_amount)->toBe(99900)
        ->and($package->discount_type)->toBeNull()
        ->and($package->tests()->pluck('lab_tests.id')->sort()->values()->all())
        ->toBe($this->tests->pluck('id')->sort()->values()->all());
});

it('creates a package with a percentage discount', function () {
    $this->actingAs($this->admin)->post(route('admin.packages.store'), validPackage(
        $this->tests->pluck('id')->all(),
        ['pricing_mode' => 'discounted', 'price' => '', 'discount_type' => 'percent', 'discount_value' => '20'],
    ))->assertSessionHasNoErrors();

    $package = LabPackage::sole();
    expect($package->pricing_mode)->toBe(PackagePricingMode::Discounted)
        ->and($package->price_amount)->toBeNull()
        ->and($package->discount_type)->toBe(DiscountType::Percent)
        ->and($package->discount_value)->toBe(20);
});

it('creates a package with an amount discount, stored in minor units', function () {
    $this->actingAs($this->admin)->post(route('admin.packages.store'), validPackage(
        $this->tests->pluck('id')->all(),
        ['pricing_mode' => 'discounted', 'discount_type' => 'amount', 'discount_value' => '150.50'],
    ))->assertSessionHasNoErrors();

    expect(LabPackage::sole()->discount_value)->toBe(15050);
});

it('rejects invalid package data', function (Closure $data, string $field) {
    $this->actingAs($this->admin)
        ->post(route('admin.packages.store'), $data($this->tests->pluck('id')->all()))
        ->assertSessionHasErrors($field);

    expect(LabPackage::count())->toBe(0);
})->with([
    'too few tests' => [fn (array $ids) => validPackage([$ids[0]]), 'test_ids'],
    'no tests' => [fn (array $ids) => validPackage([]), 'test_ids'],
    'unknown test' => [fn (array $ids) => validPackage([$ids[0], 999999]), 'test_ids.1'],
    'duplicate test' => [fn (array $ids) => validPackage([$ids[0], $ids[0]]), 'test_ids.0'],
    'missing fixed price' => [fn (array $ids) => validPackage($ids, ['price' => '']), 'price'],
    'bad pricing mode' => [fn (array $ids) => validPackage($ids, ['pricing_mode' => 'free']), 'pricing_mode'],
    'missing discount type' => [fn (array $ids) => validPackage($ids, ['pricing_mode' => 'discounted', 'discount_value' => '10']), 'discount_type'],
    'percent over 100' => [fn (array $ids) => validPackage($ids, ['pricing_mode' => 'discounted', 'discount_type' => 'percent', 'discount_value' => '101']), 'discount_value'],
    'fractional percent' => [fn (array $ids) => validPackage($ids, ['pricing_mode' => 'discounted', 'discount_type' => 'percent', 'discount_value' => '12.5']), 'discount_value'],
    // Tests total 1150.00
    'amount not below total' => [fn (array $ids) => validPackage($ids, ['pricing_mode' => 'discounted', 'discount_type' => 'amount', 'discount_value' => '1150']), 'discount_value'],
]);

it('shows the edit form with money as decimals', function () {
    $package = LabPackage::factory()->discounted(DiscountType::Amount, 15050)->create();
    $package->tests()->attach($this->tests->pluck('id'));

    $this->actingAs($this->admin)
        ->get(route('admin.packages.edit', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/packages/form')
            ->where('package.id', $package->id)
            ->has('package.tests', 3)
            ->where('form.discount_value', '150.50')
            ->has('tests', 3)
            ->where('minTests', 2)
        );
});

it('updates a package, switching pricing mode and tests', function () {
    $package = LabPackage::factory()->create(['code' => 'BASIC', 'price_amount' => 50000]);
    $package->tests()->attach($this->tests->pluck('id'));

    $this->actingAs($this->admin)->put(route('admin.packages.update', $package), validPackage(
        $this->tests->take(2)->pluck('id')->all(),
        ['pricing_mode' => 'discounted', 'discount_type' => 'percent', 'discount_value' => '5'],
    ))->assertRedirect(route('admin.packages.index'));

    $package->refresh();
    expect($package->pricing_mode)->toBe(PackagePricingMode::Discounted)
        ->and($package->price_amount)->toBeNull()
        ->and($package->discount_value)->toBe(5)
        ->and($package->tests()->count())->toBe(2);
});

it('deletes a package but keeps its tests', function () {
    $package = LabPackage::factory()->create();
    $package->tests()->attach($this->tests->pluck('id'));

    $this->actingAs($this->admin)
        ->delete(route('admin.packages.destroy', $package))
        ->assertRedirect(route('admin.packages.index'));

    $this->assertModelMissing($package);
    expect(LabTest::count())->toBe(3);
    $this->assertDatabaseCount('lab_package_lab_test', 0);
});

it('forbids customers from managing packages', function (string $method, Closure $url) {
    $package = LabPackage::factory()->create();

    $this->actingAs(User::factory()->create())
        ->{$method}($url($package), validPackage($this->tests->pluck('id')->all()))
        ->assertForbidden();

    $this->assertModelExists($package);
})->with([
    'index' => ['get', fn () => route('admin.packages.index')],
    'create' => ['get', fn () => route('admin.packages.create')],
    'store' => ['post', fn () => route('admin.packages.store')],
    'edit' => ['get', fn (LabPackage $package) => route('admin.packages.edit', $package)],
    'update' => ['put', fn (LabPackage $package) => route('admin.packages.update', $package)],
    'destroy' => ['delete', fn (LabPackage $package) => route('admin.packages.destroy', $package)],
]);
