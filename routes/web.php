<?php

use App\Http\Controllers\Admin\LabPackageController;
use App\Http\Controllers\Admin\LabTestController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogueController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');

Route::controller(CatalogueController::class)->name('catalogue.')->group(function () {
    Route::get('/tests', 'index')->name('index');
    Route::get('/tests/{test}', 'showTest')->name('tests.show');
    Route::get('/packages/{package}', 'showPackage')->name('packages.show');
});

Route::controller(CartController::class)->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/items', 'store')->name('items.store');
    Route::delete('/items/{item}', 'destroy')->name('items.destroy');
    Route::get('/login', 'login')->middleware('guest')->name('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/account', 'account')->name('account');
});

Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('/', 'admin/dashboard')->name('dashboard');

        Route::resource('tests', LabTestController::class)->except('show');
        Route::resource('packages', LabPackageController::class)->except('show');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
