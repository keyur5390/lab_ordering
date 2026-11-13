<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\AuditLogger;
use App\Services\NotificationLogger;
use App\Settings\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
        // Lab area, and read-only access to every order (client, 2026-10-29).
        Gate::define('access-lab', fn (User $user): bool => $user->isLabStaff());
        // The collector's visits screen (client, 2026-10-30).
        Gate::define('access-collector', fn (User $user): bool => $user->isCollector());

        // Inertia props get the resource itself, not {data: ...}. Paginated collections keep data/links/meta.
        JsonResource::withoutWrapping();

        // Behind a proxy that ends HTTPS, trust its forwarded headers so URLs and cookies are secure (B-33).
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Audit log (B-26): staff sign-ins.
        Event::listen(fn (Login $event) => app(AuditLogger::class)->auth('auth.login', $event->user, request()));
        Event::listen(fn (Logout $event) => $event->user && app(AuditLogger::class)->auth('auth.logout', $event->user, request()));
        Event::listen(function (Failed $event) {
            // Only for accounts that exist and are staff: no record of guesses at customer emails.
            // Our sign-in check (FortifyServiceProvider) doesn't pass the user along, so look it up.
            $email = $event->credentials['email'] ?? null;
            $user = $event->user ?? (is_string($email) ? User::where('email', Str::lower($email))->first() : null);
            if ($user instanceof User) {
                app(AuditLogger::class)->auth('auth.failed', $user, request());
            }
        });

        // Notification history (B-24): sent messages, and queued ones that finally failed.
        Event::listen(function (NotificationSent $event) {
            if ($event->notification instanceof OrderNotification) {
                app(NotificationLogger::class)->sent($event->notifiable, $event->notification, $event->channel);
            }
        });
        Queue::failing(function (JobFailed $event) {
            $command = $event->job->payload()['data']['command'] ?? null;
            $job = is_string($command) ? rescue(fn () => unserialize($command), null, false) : null;
            if ($job instanceof SendQueuedNotifications && $job->notification instanceof OrderNotification) {
                foreach ($job->notifiables as $notifiable) {
                    foreach ($job->channels ?? [] as $channel) {
                        app(NotificationLogger::class)->failed($notifiable, $job->notification, $channel, $event->exception);
                    }
                }
            }
        });
    }
}
