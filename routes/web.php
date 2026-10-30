<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ClosureController;
use App\Http\Controllers\Admin\CollectorController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LabPackageController;
use App\Http\Controllers\Admin\LabTestController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\OrderCollectorController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceAreaController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Collector\VisitController;
use App\Http\Controllers\Lab\OrderController as LabOrderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ReportController;
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
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account/notifications', [AccountController::class, 'updateNotifications'])->name('account.notifications.update');
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
    Route::get('/account/orders/{order}/reports/{item}', [ReportController::class, 'show'])->scopeBindings()->name('orders.reports.show');

    Route::controller(PaymentController::class)->prefix('account/orders/{order}/payments')->name('orders.payments.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::get('/{payment}/return', 'return')->scopeBindings()->name('return');
    });
});

// Collectors: their visits for a day (client, 2026-10-30).
Route::middleware(['auth', 'verified', 'can:access-collector'])
    ->prefix('collector')
    ->name('collector.')
    ->controller(VisitController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/visits/{order}/claim', 'claim')->name('claim');
        Route::post('/visits/{order}/collected', 'collected')->name('collected');
        Route::post('/visits/{order}/failed', 'failed')->name('failed');
        Route::post('/visits/{order}/payment', 'payment')->name('payment');
    });

// Lab staff and admins: the lab area and read-only order pages (client, 2026-10-29).
Route::middleware(['auth', 'verified', 'can:access-lab'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');

        Route::controller(LabOrderController::class)->prefix('lab')->name('lab.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{order}', 'show')->name('show');
            Route::post('/{order}/collected', 'collect')->name('collect');
            Route::post('/{order}/received', 'receive')->name('receive');
            Route::post('/{order}/items/{item}/report', 'uploadReport')->scopeBindings()->name('reports.store');
        });
    });

Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('tests', LabTestController::class)->except('show');
        Route::resource('packages', LabPackageController::class)->except('show');
        Route::resource('service-areas', ServiceAreaController::class)->except('show');
        Route::get('/service-areas/{service_area}/schedule', [ScheduleController::class, 'edit'])->name('service-areas.schedule.edit');
        Route::put('/service-areas/{service_area}/schedule', [ScheduleController::class, 'update'])->name('service-areas.schedule.update');
        Route::resource('closures', ClosureController::class)->only(['index', 'store', 'destroy']);

        Route::controller(AdminOrderController::class)->prefix('orders')->name('orders.')->group(function () {
            Route::post('/{order}/cancel', 'cancel')->name('cancel');
            Route::get('/{order}/reschedule', 'editSlot')->name('reschedule.edit');
            Route::put('/{order}/reschedule', 'reschedule')->name('reschedule');
        });
        Route::post('/orders/{order}/refunds', [RefundController::class, 'store'])->name('orders.refunds.store');
        Route::put('/orders/{order}/collector', [OrderCollectorController::class, 'update'])->name('orders.collector.update');
        Route::resource('coupons', CouponController::class)->except('show');
        Route::resource('branches', BranchController::class)->except('show');
        Route::get('/branches/{branch}/schedule', [BranchController::class, 'editSchedule'])->name('branches.schedule.edit');
        Route::put('/branches/{branch}/schedule', [BranchController::class, 'updateSchedule'])->name('branches.schedule.update');
        Route::get('/collectors', [CollectorController::class, 'index'])->name('collectors.index');
        Route::put('/collectors/{collector}', [CollectorController::class, 'update'])->name('collectors.update');

        Route::get('/notifications', [NotificationTemplateController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{template}/edit', [NotificationTemplateController::class, 'edit'])->name('notifications.edit');
        Route::put('/notifications/{template}', [NotificationTemplateController::class, 'update'])->name('notifications.update');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

// Signed by the gateway and exempt from CSRF (bootstrap/app.php).
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)->name('webhooks.payments');
