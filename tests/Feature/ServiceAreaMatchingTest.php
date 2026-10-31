<?php

use App\Models\Address;
use App\Models\ServiceArea;
use App\Services\ServiceAreaService;

beforeEach(fn () => $this->areas = app(ServiceAreaService::class));

// A square zone roughly 2 km across, in central London.
const LONDON_ZONE = [[51.52, -0.14], [51.52, -0.11], [51.50, -0.11], [51.50, -0.14]];

it('matches postcodes ignoring case, spaces and dashes', function (string $addressPostcode) {
    $area = ServiceArea::factory()->create(['country' => 'GB']);
    $this->areas->syncPostcodes($area, ['SW1A 1AA', 'sw1a-2aa']);

    $address = Address::factory()->create(['postcode' => $addressPostcode, 'country' => 'GB']);

    expect($this->areas->coveringArea($address)?->id)->toBe($area->id);
})->with(['SW1A 1AA', 'sw1a1aa', 'SW1A-2AA', ' sw1a 2aa ']);

it('does not match a postcode from another country or not in the list', function (string $postcode, string $country) {
    $area = ServiceArea::factory()->create(['country' => 'GB']);
    $this->areas->syncPostcodes($area, ['SW1A 1AA']);

    expect($this->areas->covers(Address::factory()->create(['postcode' => $postcode, 'country' => $country])))->toBeFalse();
})->with([
    'other country' => ['SW1A 1AA', 'IE'],
    'not listed' => ['SW1A 9ZZ', 'GB'],
]);

it('matches addresses within a radius of the centre', function () {
    // ~1.1 km and ~3.3 km from Charing Cross respectively.
    ServiceArea::factory()->radius(51.5080, -0.1281, 2_000)->create();

    expect($this->areas->covers(Address::factory()->at(51.5180, -0.1281)->create()))->toBeTrue()
        ->and($this->areas->covers(Address::factory()->at(51.5380, -0.1281)->create()))->toBeFalse();
});

it('measures great-circle distance accurately', function () {
    // London to Paris is about 343.5 km.
    $meters = $this->areas->distanceMeters(51.5074, -0.1278, 48.8566, 2.3522);

    expect($meters)->toBeGreaterThan(343_000)->toBeLessThan(344_000);
});

it('matches addresses inside a drawn zone', function () {
    ServiceArea::factory()->polygon(LONDON_ZONE)->create();

    expect($this->areas->covers(Address::factory()->at(51.51, -0.125)->create()))->toBeTrue()
        ->and($this->areas->covers(Address::factory()->at(51.53, -0.125)->create()))->toBeFalse()
        ->and($this->areas->covers(Address::factory()->at(51.51, -0.10)->create()))->toBeFalse();
});

it('handles concave zones', function () {
    // An L shape: the notch at the top right is outside.
    $lShape = [[0, 0], [0, 2], [1, 2], [1, 1], [2, 1], [2, 0]];

    expect($this->areas->inPolygon($lShape, 0.5, 1.5))->toBeTrue()
        ->and($this->areas->inPolygon($lShape, 1.5, 0.5))->toBeTrue()
        ->and($this->areas->inPolygon($lShape, 1.5, 1.5))->toBeFalse();
});

it('ignores radius and zone areas for addresses without a map pin', function () {
    ServiceArea::factory()->radius(51.5080, -0.1281, 50_000)->create();
    ServiceArea::factory()->polygon(LONDON_ZONE)->create();

    expect($this->areas->covers(Address::factory()->create()))->toBeFalse();
});

it('ignores hidden areas', function () {
    $area = ServiceArea::factory()->inactive()->create(['country' => 'GB']);
    $this->areas->syncPostcodes($area, ['SW1A 1AA']);
    ServiceArea::factory()->radius(51.5080, -0.1281, 50_000)->inactive()->create();

    expect($this->areas->covers(Address::factory()->at(51.5080, -0.1281)->create(['postcode' => 'SW1A 1AA'])))->toBeFalse();
});

it('replaces postcodes, normalising and removing duplicates', function () {
    $area = ServiceArea::factory()->create();
    $this->areas->syncPostcodes($area, ['AB1 2CD', 'old']);
    $this->areas->syncPostcodes($area, ['ab1 2cd', 'AB1-2CD', 'EF3 4GH', '  ']);

    expect($area->postcodes()->pluck('postcode')->all())->toBe(['AB12CD', 'EF34GH']);
});
