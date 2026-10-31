<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->where('name', config('app.name'))
        );
});
