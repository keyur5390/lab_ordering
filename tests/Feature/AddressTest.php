<?php

use App\Models\Address;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\ServiceAreaService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->user = User::factory()->create());

function validAddress(array $overrides = []): array
{
    return [
        'label' => 'Home',
        'line1' => '10 Downing Street',
        'line2' => '',
        'city' => 'London',
        'region' => '',
        'postcode' => 'sw1a 2aa',
        'country' => 'gb',
        'latitude' => '',
        'longitude' => '',
        ...$overrides,
    ];
}

it('lists only the customer\'s own addresses, with whether we collect there', function () {
    $area = ServiceArea::factory()->create(['country' => 'GB']);
    app(ServiceAreaService::class)->syncPostcodes($area, ['SW1A 2AA']);
    Address::factory()->for($this->user)->create(['postcode' => 'SW1A 2AA', 'country' => 'GB']);
    Address::factory()->for($this->user)->create(['postcode' => 'EH1 1AA', 'country' => 'GB']);
    Address::factory()->create();

    $this->actingAs($this->user)
        ->get(route('addresses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('addresses/index')
            ->has('addresses', 2)
            ->where('addresses.0.serviceable', true)
            ->where('addresses.0.country_name', 'United Kingdom')
            ->where('addresses.1.serviceable', false)
        );
});

it('adds an address, normalising postcode and country', function () {
    $this->actingAs($this->user)
        ->post(route('addresses.store'), validAddress(['latitude' => '51.5034', 'longitude' => '-0.1276']))
        ->assertRedirect(route('addresses.index'))
        ->assertSessionHas('status', 'Address added.');

    $address = $this->user->addresses()->sole();
    expect($address->postcode)->toBe('SW1A 2AA')
        ->and($address->postcode_normalized)->toBe('SW1A2AA')
        ->and($address->country)->toBe('GB')
        ->and($address->latitude)->toBe(51.5034)
        ->and($address->line2)->toBeNull();
});

it('rejects invalid addresses', function (array $data, string $field) {
    $this->actingAs($this->user)
        ->post(route('addresses.store'), validAddress($data))
        ->assertSessionHasErrors($field);

    expect($this->user->addresses()->count())->toBe(0);
})->with([
    'missing line 1' => [['line1' => ''], 'line1'],
    'missing city' => [['city' => ''], 'city'],
    'missing postcode' => [['postcode' => ''], 'postcode'],
    'postcode symbols' => [['postcode' => 'SW1A#2AA'], 'postcode'],
    'unknown country' => [['country' => 'XX'], 'country'],
    'not a country' => [['country' => 'EU'], 'country'],
    'latitude without longitude' => [['latitude' => '51.5'], 'longitude'],
    'latitude out of range' => [['latitude' => '91', 'longitude' => '0'], 'latitude'],
    'longitude out of range' => [['latitude' => '0', 'longitude' => '181'], 'longitude'],
]);

it('edits an address and can remove its pin', function () {
    $address = Address::factory()->for($this->user)->at(51.5, -0.1)->create();

    $this->actingAs($this->user)
        ->get(route('addresses.edit', $address))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('addresses/form')
            ->where('address.latitude', 51.5)
            ->has('countries')
            ->has('map.tile_url')
        );

    $this->actingAs($this->user)
        ->put(route('addresses.update', $address), validAddress(['city' => 'Westminster']))
        ->assertRedirect(route('addresses.index'));

    $address->refresh();
    expect($address->city)->toBe('Westminster')
        ->and($address->hasLocation())->toBeFalse();
});

it('removes an address', function () {
    $address = Address::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->delete(route('addresses.destroy', $address))
        ->assertRedirect(route('addresses.index'));

    $this->assertModelMissing($address);
});

it("does not let customers see or change someone else's addresses", function (string $method, string $route) {
    $other = Address::factory()->create(['city' => 'Elsewhere']);

    $this->actingAs($this->user)
        ->{$method}(route($route, $other), validAddress(['city' => 'Hijacked']))
        ->assertForbidden();

    $this->assertModelExists($other);
    expect($other->fresh()->city)->toBe('Elsewhere');
})->with([
    'edit' => ['get', 'addresses.edit'],
    'update' => ['put', 'addresses.update'],
    'destroy' => ['delete', 'addresses.destroy'],
]);

it('requires a signed-in, verified customer', function () {
    $this->get(route('addresses.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('addresses.index'))
        ->assertRedirect(route('verification.notice'));
});
