<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/account', 'account')->name('account');
});

Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('/', 'admin/dashboard')->name('dashboard');
    });
