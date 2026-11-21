<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\ServiceArea;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Observers\HomepageDataObserver;
use App\Services\AuditLogger;
use App\Services\NotificationLogger;
use App\Settings\Settings;
use Illuminate\Auth\Events\Failed as AuthenticationFailed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($trustedProxies = config('app.trusted_proxies')) {
            TrustProxies::at($trustedProxies);
        }

        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
        // Lab area, and read-only access to every order (client, 2026-10-29).
        Gate::define('access-lab', fn (User $user): bool => $user->isLabStaff());
        // The collector's visits screen (client, 2026-10-30).
        Gate::define('access-collector', fn (User $user): bool => $user->isCollector());

        Branch::observe(HomepageDataObserver::class);
        LabPackage::observe(HomepageDataObserver::class);
        LabTest::observe(HomepageDataObserver::class);
        ServiceArea::observe(HomepageDataObserver::class);

        // Inertia props get the resource itself, not {data: ...}. Paginated collections keep data/links/meta.
        JsonResource::withoutWrapping();

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User && AuditLogger::isStaff($event->user)) {
                app(AuditLogger::class)->auth('auth.login', $event->user, request());
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User && AuditLogger::isStaff($event->user)) {
                app(AuditLogger::class)->auth('auth.logout', $event->user, request());
            }
        });

        Event::listen(AuthenticationFailed::class, function (AuthenticationFailed $event): void {
            $user = $event->user instanceof User
                ? $event->user
                : User::where('email', Str::lower((string) ($event->credentials['email'] ?? '')))->first();

            if ($user && AuditLogger::isStaff($user)) {
                app(AuditLogger::class)->auth('auth.failed', $user, request(), $event->credentials['email'] ?? null);
            }
        });

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->notification instanceof OrderNotification) {
                app(NotificationLogger::class)->sent($event->notifiable, $event->notification, $event->channel);
            }
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if (! $event->notification instanceof OrderNotification) {
                return;
            }

            $exception = $event->data['exception'] ?? null;
            if (! $exception instanceof Throwable) {
                throw new RuntimeException('A failed notification event must include its exception.');
            }

            app(NotificationLogger::class)->failed($event->notifiable, $event->notification, $event->channel, $exception);
        });
    }
}
