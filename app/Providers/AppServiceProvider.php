<?php

namespace App\Providers;

use App\Models\User;
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

        // Inertia props get the resource itself, not {data: ...}. Paginated collections keep data/links/meta.
        JsonResource::withoutWrapping();
    }
}
