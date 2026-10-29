<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/account', 'account')->name('account');
});
