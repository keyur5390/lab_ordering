<?php

use App\Enums\Currency;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\User;
use App\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->setCurrency(Currency::INR);
    $this->admin = User::factory()->admin()->create();
});

function validTest(array $overrides = []): array
{
    return [
        'name' => 'Complete Blood Count',
        'code' => 'cbc',
        'sample_type' => 'Blood',
        'turnaround_hours' => 24,
        'price' => '499.50',
        'description' => 'Checks your blood cells.',
        'preparation' => '',
        'is_active' => true,
        ...$overrides,
    ];
}

it('lists tests for admins', function () {
    LabTest::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.tests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tests/index')
            ->has('tests.data', 3)
            ->has('tests.meta')
        );
});

it('creates a test, storing the price in minor units', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.tests.store'), validTest())
        ->assertRedirect(route('admin.tests.index'))
        ->assertSessionHas('status', 'Test created.');

    $test = LabTest::sole();
    expect($test->code)->toBe('CBC')
        ->and($test->slug)->toBe('complete-blood-count')
        ->and($test->price_amount)->toBe(49950)
        ->and($test->preparation)->toBeNull()
        ->and($test->is_active)->toBeTrue();
});

it('gives tests with the same name unique slugs', function () {
    LabTest::factory()->create(['slug' => 'complete-blood-count']);

    $this->actingAs($this->admin)->post(route('admin.tests.store'), validTest());

    expect(LabTest::latest('id')->first()->slug)->toBe('complete-blood-count-2');
});

it('rejects invalid test data', function (array $data, string $field) {
    $this->actingAs($this->admin)
        ->post(route('admin.tests.store'), validTest($data))
        ->assertSessionHasErrors($field);

    expect(LabTest::count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'bad code' => [['code' => 'has spaces'], 'code'],
    'missing sample type' => [['sample_type' => ''], 'sample_type'],
    'zero turnaround' => [['turnaround_hours' => 0], 'turnaround_hours'],
    'negative price' => [['price' => '-5'], 'price'],
    'too many decimals' => [['price' => '4.999'], 'price'],
    'price with comma' => [['price' => '1,000'], 'price'],
]);

it('rejects a duplicate code, regardless of case', function () {
    LabTest::factory()->create(['code' => 'CBC']);

    $this->actingAs($this->admin)
        ->post(route('admin.tests.store'), validTest(['code' => 'cbc']))
        ->assertSessionHasErrors('code');
});

it('shows the edit form with the price as a decimal', function () {
    $test = LabTest::factory()->create(['price_amount' => 49950]);

    $this->actingAs($this->admin)
        ->get(route('admin.tests.edit', $test))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tests/form')
            ->where('test.id', $test->id)
            ->where('price', '499.50')
        );
});

it('updates a test and keeps its slug and code', function () {
    $test = LabTest::factory()->create(['code' => 'CBC', 'slug' => 'cbc']);

    $this->actingAs($this->admin)
        ->put(route('admin.tests.update', $test), validTest(['name' => 'Renamed', 'code' => 'CBC', 'price' => '10', 'is_active' => false]))
        ->assertRedirect(route('admin.tests.index'));

    $test->refresh();
    expect($test->name)->toBe('Renamed')
        ->and($test->slug)->toBe('cbc')
        ->and($test->price_amount)->toBe(1000)
        ->and($test->is_active)->toBeFalse();
});

it('deletes a test', function () {
    $test = LabTest::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.tests.destroy', $test))
        ->assertRedirect(route('admin.tests.index'));

    $this->assertModelMissing($test);
});

it('refuses to delete a test that is part of a package', function () {
    $test = LabTest::factory()->create();
    LabPackage::factory()->create()->tests()->attach($test);

    $this->actingAs($this->admin)
        ->from(route('admin.tests.index'))
        ->delete(route('admin.tests.destroy', $test))
        ->assertRedirect(route('admin.tests.index'))
        ->assertSessionHasErrors('delete');

    $this->assertModelExists($test);
});

it('forbids customers from managing tests', function (string $method, Closure $url) {
    $test = LabTest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->{$method}($url($test), validTest())
        ->assertForbidden();

    $this->assertModelExists($test);
})->with([
    'index' => ['get', fn () => route('admin.tests.index')],
    'create' => ['get', fn () => route('admin.tests.create')],
    'store' => ['post', fn () => route('admin.tests.store')],
    'edit' => ['get', fn (LabTest $test) => route('admin.tests.edit', $test)],
    'update' => ['put', fn (LabTest $test) => route('admin.tests.update', $test)],
    'destroy' => ['delete', fn (LabTest $test) => route('admin.tests.destroy', $test)],
]);
