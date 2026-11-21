<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\ServiceArea;
use App\Models\User;
use App\Observers\HomepageDataObserver;
use App\Notifications\OrderNotification;
use App\Services\AuditLogger;
use App\Services\NotificationLogger;
use App\Settings\Settings;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

        Branch::observe(HomepageDataObserver::class);
        LabPackage::observe(HomepageDataObserver::class);
        LabTest::observe(HomepageDataObserver::class);
        ServiceArea::observe(HomepageDataObserver::class);

        // Inertia props get the resource itself, not {data: ...}. Paginated collections keep data/links/meta.
        JsonResource::withoutWrapping();
    }
}
