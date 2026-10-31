<?php

use App\Enums\DiscountType;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Services\PackagePricingService;

function packageWithTests(LabPackage $package, array $prices, array $turnarounds = []): LabPackage
{
    foreach ($prices as $i => $price) {
        $package->tests()->attach(LabTest::factory()->create([
            'price_amount' => $price,
            'turnaround_hours' => $turnarounds[$i] ?? 24,
        ]));
    }

    return $package->load('tests');
}

beforeEach(fn () => $this->pricing = new PackagePricingService);

it('uses the fixed price for fixed-price packages', function () {
    $package = packageWithTests(LabPackage::factory()->create(['price_amount' => 99900]), [40000, 60000, 15000]);

    expect($this->pricing->price($package))->toBe(99900)
        ->and($this->pricing->testsTotal($package))->toBe(115000);
});

it('takes a percentage off the tests total', function () {
    $package = packageWithTests(LabPackage::factory()->discounted(DiscountType::Percent, 20)->create(), [40000, 60000]);

    expect($this->pricing->price($package))->toBe(80000);
});

it('rounds percentage discounts half-up to the minor unit', function (int $total, int $percent, int $expected) {
    $package = packageWithTests(LabPackage::factory()->discounted(DiscountType::Percent, $percent)->create(), [$total]);

    expect($this->pricing->price($package))->toBe($expected);
})->with([
    // 15% of 1003 = 150.45 → 150 off
    [1003, 15, 853],
    // 15% of 1010 = 151.5 → 152 off (half rounds up)
    [1010, 15, 858],
    // 100% off is free
    [5000, 100, 0],
]);

it('takes a fixed amount off the tests total', function () {
    $package = packageWithTests(LabPackage::factory()->discounted(DiscountType::Amount, 25000)->create(), [40000, 60000]);

    expect($this->pricing->price($package))->toBe(75000);
});

it('never prices a package below zero', function () {
    $package = packageWithTests(LabPackage::factory()->discounted(DiscountType::Amount, 99999)->create(), [1000, 2000]);

    expect($this->pricing->price($package))->toBe(0);
});

it('follows test price changes for discounted packages', function () {
    $package = packageWithTests(LabPackage::factory()->discounted(DiscountType::Percent, 10)->create(), [10000, 10000]);
    $package->tests->first()->update(['price_amount' => 30000]);

    expect($this->pricing->price($package->fresh('tests')))->toBe(36000);
});

it("uses the slowest test's turnaround", function () {
    $package = packageWithTests(LabPackage::factory()->create(), [100, 100, 100], [12, 72, 24]);

    expect($this->pricing->turnaroundHours($package))->toBe(72);
});
