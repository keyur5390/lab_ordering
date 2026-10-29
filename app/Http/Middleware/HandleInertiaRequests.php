<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use App\Settings\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn () => $request->user()
                    ? UserResource::make($request->user())->resolve($request)
                    : null,
            ],
            'status' => fn () => $request->session()->get('status'),
            // Site currency for formatting prices; null until an admin chooses one.
            'currency' => function () {
                $currency = app(Settings::class)->currency();

                return $currency ? ['code' => $currency->value, 'decimals' => $currency->decimals()] : null;
            },
        ];
    }
}
