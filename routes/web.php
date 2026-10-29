<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\ClosureController;
use App\Http\Controllers\Admin\LabPackageController;
use App\Http\Controllers\Admin\LabTestController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceAreaController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
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
    Route::resource('account/patients', PatientController::class)->except('show')->names('patients');
    Route::resource('account/addresses', AddressController::class)->except('show')->names('addresses');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::controller(OrderController::class)->prefix('account/orders')->name('orders.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{order}', 'show')->name('show');
        Route::post('/{order}/cancel', 'cancel')->name('cancel');
        Route::get('/{order}/reschedule', 'editSlot')->name('reschedule.edit');
        Route::put('/{order}/reschedule', 'reschedule')->name('reschedule');
        Route::get('/{order}/prescription', 'prescription')->name('prescription');
    });

    Route::controller(PaymentController::class)->prefix('account/orders/{order}/payments')->name('orders.payments.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::get('/{payment}/return', 'return')->scopeBindings()->name('return');
    });
});

Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('/', 'admin/dashboard')->name('dashboard');

        Route::resource('tests', LabTestController::class)->except('show');
        Route::resource('packages', LabPackageController::class)->except('show');
        Route::resource('service-areas', ServiceAreaController::class)->except('show');
        Route::get('/service-areas/{service_area}/schedule', [ScheduleController::class, 'edit'])->name('service-areas.schedule.edit');
        Route::put('/service-areas/{service_area}/schedule', [ScheduleController::class, 'update'])->name('service-areas.schedule.update');
        Route::resource('closures', ClosureController::class)->only(['index', 'store', 'destroy']);

        Route::controller(AdminOrderController::class)->prefix('orders')->name('orders.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{order}', 'show')->name('show');
            Route::post('/{order}/cancel', 'cancel')->name('cancel');
            Route::get('/{order}/reschedule', 'editSlot')->name('reschedule.edit');
            Route::put('/{order}/reschedule', 'reschedule')->name('reschedule');
        });
        Route::post('/orders/{order}/refunds', [RefundController::class, 'store'])->name('orders.refunds.store');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

// Signed by the gateway and exempt from CSRF (bootstrap/app.php).
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)->name('webhooks.payments');
