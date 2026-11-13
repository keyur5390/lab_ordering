<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPhoneController;
use App\Http\Controllers\AccountPrivacyController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ClosureController;
use App\Http\Controllers\Admin\CollectorController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\HomeContentController;
use App\Http\Controllers\Admin\LabPackageController;
use App\Http\Controllers\Admin\LabTestController;
use App\Http\Controllers\Admin\NotificationLogController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\OrderCollectorController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OrderPaymentController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceAreaController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhatsAppTemplateController;
use App\Http\Controllers\Auth\PhoneLoginController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Collector\VisitController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Lab\OrderController as LabOrderController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportHistoryController;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'show'])->name('home');

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

// Name, email and password (B-23). Signed in but not necessarily verified, so someone who mistyped a
// new email can still correct it. The forms post to Fortify's routes.
Route::get('/{slug}', [PageController::class, 'show'])->whereIn('slug', Page::SLUGS)->name('pages.show');
Route::get('/faq', [HomeController::class, 'faq'])->name('faq');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('auth')->group(function () {
    Route::get('/account/privacy', [AccountPrivacyController::class, 'show'])->name('account.privacy');
    Route::get('/account/privacy/export', [AccountPrivacyController::class, 'export'])->name('account.privacy.export');
    Route::delete('/account', [AccountPrivacyController::class, 'destroy'])->name('account.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/account/settings', [AccountSettingsController::class, 'show'])->name('account.settings');
    Route::post('/account/phone', [AccountPhoneController::class, 'send'])->name('account.phone.send');
    Route::post('/account/phone/verify', [AccountPhoneController::class, 'verify'])->middleware('throttle:10,1')->name('account.phone.verify');
    Route::delete('/account/phone', [AccountPhoneController::class, 'destroy'])->name('account.phone.destroy');
});

// Phone sign-in (B-30), next to Fortify's email sign-in.
Route::middleware('guest')->group(function () {
    Route::post('/login/phone', [PhoneLoginController::class, 'send'])->name('login.phone.send');
    Route::post('/login/phone/verify', [PhoneLoginController::class, 'verify'])->middleware('throttle:10,1')->name('login.phone.verify');
    Route::post('/login/phone/reset', [PhoneLoginController::class, 'reset'])->name('login.phone.reset');
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
    Route::get('/account/reports', [ReportHistoryController::class, 'index'])->name('reports.index');
    Route::get('/account/orders/{order}/invoice', [InvoiceController::class, 'invoice'])->name('orders.invoice');
    Route::get('/account/orders/{order}/credit-notes/{creditNote}', [InvoiceController::class, 'creditNote'])->name('orders.credit-notes.show');

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
        Route::post('/orders/{order}/payment', [OrderPaymentController::class, 'store'])->name('orders.payment.store');

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
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('users', UserController::class)->only(['index', 'show', 'create', 'store']);
        Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
        Route::post('/users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
        Route::resource('coupons', CouponController::class)->except('show');
        Route::resource('branches', BranchController::class)->except('show');
        Route::get('/branches/{branch}/schedule', [BranchController::class, 'editSchedule'])->name('branches.schedule.edit');
        Route::put('/branches/{branch}/schedule', [BranchController::class, 'updateSchedule'])->name('branches.schedule.update');
        Route::get('/collectors', [CollectorController::class, 'index'])->name('collectors.index');
        Route::put('/collectors/{collector}', [CollectorController::class, 'update'])->name('collectors.update');

        Route::get('/notifications', [NotificationTemplateController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/history', [NotificationLogController::class, 'index'])->name('notifications.history');
        Route::post('/notifications/history/{log}/resend', [NotificationLogController::class, 'resend'])->name('notifications.resend');
        Route::get('/notifications/whatsapp', [WhatsAppTemplateController::class, 'edit'])->name('notifications.whatsapp.edit');
        Route::put('/notifications/whatsapp', [WhatsAppTemplateController::class, 'update'])->name('notifications.whatsapp.update');
        Route::get('/notifications/{template}/edit', [NotificationTemplateController::class, 'edit'])->name('notifications.edit');
        Route::put('/notifications/{template}', [NotificationTemplateController::class, 'update'])->name('notifications.update');

        Route::get('/pages', [AdminPageController::class, 'index'])->name('pages.index');
        Route::get('/pages/home', [HomeContentController::class, 'edit'])->name('pages.home.edit');
        Route::put('/pages/home', [HomeContentController::class, 'update'])->name('pages.home.update');
        Route::get('/pages/faqs', [FaqController::class, 'index'])->name('pages.faqs.index');
        Route::post('/pages/faqs', [FaqController::class, 'store'])->name('pages.faqs.store');
        Route::put('/pages/faqs/{faq}', [FaqController::class, 'update'])->name('pages.faqs.update');
        Route::post('/pages/faqs/{faq}/move', [FaqController::class, 'move'])->name('pages.faqs.move');
        Route::delete('/pages/faqs/{faq}', [FaqController::class, 'destroy'])->name('pages.faqs.destroy');
        Route::get('/pages/{page}/edit', [AdminPageController::class, 'edit'])->name('pages.edit');
        Route::put('/pages/{page}', [AdminPageController::class, 'update'])->name('pages.update');

        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

// Signed by the gateway and exempt from CSRF (bootstrap/app.php).
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)->name('webhooks.payments');
