<?php

use App\Models\LabTest;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->where('name', config('app.name'))
        );
});

it('invalidates cached homepage data when a catalogue test changes', function () {
    Cache::put('homepage-data', ['cached' => true], 3600);
    $test = LabTest::factory()->create();
    expect(Cache::has('homepage-data'))->toBeFalse();

    Cache::put('homepage-data', ['cached' => true], 3600);
    $test->update(['name' => 'Updated test name']);
    expect(Cache::has('homepage-data'))->toBeFalse();

    Cache::put('homepage-data', ['cached' => true], 3600);
    $test->delete();
    expect(Cache::has('homepage-data'))->toBeFalse();
});
