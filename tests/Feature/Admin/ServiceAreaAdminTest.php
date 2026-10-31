<?php

use App\Enums\ServiceAreaType;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\ServiceAreaService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->admin = User::factory()->admin()->create());

function areaPayload(string $type, array $overrides = []): array
{
    return [
        'name' => 'Central London',
        'type' => $type,
        'is_active' => true,
        ...match ($type) {
            'postcodes' => ['country' => 'gb', 'postcodes' => "SW1A 1AA\nsw1a-2aa, EC1A 1BB\n\n"],
            'radius' => ['center_latitude' => '51.508', 'center_longitude' => '-0.1281', 'radius_km' => '2.5'],
            'polygon' => ['polygon' => [[51.52, -0.14], [51.52, -0.11], [51.50, -0.11]]],
        },
        ...$overrides,
    ];
}

it('lists service areas for admins', function () {
    $area = ServiceArea::factory()->create();
    app(ServiceAreaService::class)->syncPostcodes($area, ['A1', 'B2']);

    $this->actingAs($this->admin)
        ->get(route('admin.service-areas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/service-areas/index')
            ->has('areas', 1)
            ->where('areas.0.postcodes_count', 2)
        );
});

it('creates a postcode area', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.service-areas.store'), areaPayload('postcodes'))
        ->assertRedirect(route('admin.service-areas.index'));

    $area = ServiceArea::sole();
    expect($area->type)->toBe(ServiceAreaType::Postcodes)
        ->and($area->country)->toBe('GB')
        ->and($area->postcodes()->pluck('postcode')->all())->toBe(['EC1A1BB', 'SW1A1AA', 'SW1A2AA']);
});

it('creates a radius area, storing the radius in metres', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.service-areas.store'), areaPayload('radius'))
        ->assertSessionHasNoErrors();

    $area = ServiceArea::sole();
    expect($area->type)->toBe(ServiceAreaType::Radius)
        ->and($area->center_latitude)->toBe(51.508)
        ->and($area->radius_meters)->toBe(2500)
        ->and($area->country)->toBeNull();
});

it('creates a drawn zone', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.service-areas.store'), areaPayload('polygon'))
        ->assertSessionHasNoErrors();

    expect(ServiceArea::sole()->polygon)->toBe([[51.52, -0.14], [51.52, -0.11], [51.5, -0.11]]);
});

it('rejects invalid areas', function (string $type, array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->post(route('admin.service-areas.store'), areaPayload($type, $overrides))
        ->assertSessionHasErrors($field);

    expect(ServiceArea::count())->toBe(0);
})->with([
    'missing name' => ['postcodes', ['name' => ''], 'name'],
    'unknown type' => ['postcodes', ['type' => 'circle'], 'type'],
    'postcodes: no country' => ['postcodes', ['country' => ''], 'country'],
    'postcodes: empty list' => ['postcodes', ['postcodes' => " \n , "], 'postcode_list'],
    'postcodes: bad postcode' => ['postcodes', ['postcodes' => "SW1A 1AA\nNOT#VALID"], 'postcode_list.1'],
    'radius: no centre' => ['radius', ['center_latitude' => '', 'center_longitude' => ''], 'center_latitude'],
    'radius: too small' => ['radius', ['radius_km' => '0'], 'radius_km'],
    'radius: bad latitude' => ['radius', ['center_latitude' => '95'], 'center_latitude'],
    'polygon: two points' => ['polygon', ['polygon' => [[1, 1], [2, 2]]], 'polygon'],
    'polygon: bad point' => ['polygon', ['polygon' => [[1, 1], [2, 2], [3]]], 'polygon.2'],
]);

it('switches an area from postcodes to radius, clearing the old fields', function () {
    $area = ServiceArea::factory()->create();
    app(ServiceAreaService::class)->syncPostcodes($area, ['A1']);

    $this->actingAs($this->admin)
        ->get(route('admin.service-areas.edit', $area))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/service-areas/form')
            ->where('area.postcodes', ['A1'])
            ->has('types', 3)
            ->has('map.center')
        );

    $this->actingAs($this->admin)
        ->put(route('admin.service-areas.update', $area), areaPayload('radius', ['is_active' => false]))
        ->assertRedirect(route('admin.service-areas.index'));

    $area->refresh();
    expect($area->type)->toBe(ServiceAreaType::Radius)
        ->and($area->country)->toBeNull()
        ->and($area->postcodes()->count())->toBe(0)
        ->and($area->is_active)->toBeFalse();
});

it('deletes an area with its postcodes', function () {
    $area = ServiceArea::factory()->create();
    app(ServiceAreaService::class)->syncPostcodes($area, ['A1']);

    $this->actingAs($this->admin)
        ->delete(route('admin.service-areas.destroy', $area))
        ->assertRedirect(route('admin.service-areas.index'));

    $this->assertModelMissing($area);
    $this->assertDatabaseCount('service_area_postcodes', 0);
});

it('forbids customers from managing service areas', function (string $method, Closure $url) {
    $area = ServiceArea::factory()->create();

    $this->actingAs(User::factory()->create())
        ->{$method}($url($area), areaPayload('postcodes'))
        ->assertForbidden();

    $this->assertModelExists($area);
})->with([
    'index' => ['get', fn () => route('admin.service-areas.index')],
    'create' => ['get', fn () => route('admin.service-areas.create')],
    'store' => ['post', fn () => route('admin.service-areas.store')],
    'edit' => ['get', fn (ServiceArea $area) => route('admin.service-areas.edit', $area)],
    'update' => ['put', fn (ServiceArea $area) => route('admin.service-areas.update', $area)],
    'destroy' => ['delete', fn (ServiceArea $area) => route('admin.service-areas.destroy', $area)],
]);
