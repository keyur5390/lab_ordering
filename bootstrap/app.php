<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectUsersTo(fn () => route('account'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render friendly error pages (DESIGN.md §7). Server errors keep Laravel's
        // debug page while APP_DEBUG is on; JSON requests keep JSON errors.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            $serverError = in_array($status, [500, 503], true);

            if ($request->expectsJson()
                || ! in_array($status, [403, 404, 419, 429, 500, 503], true)
                || ($serverError && config('app.debug'))) {
                return $response;
            }

            // Props are passed explicitly: shared props and the session may not exist (e.g. unknown URL).
            return Inertia::render('error', [
                'status' => $status,
                'name' => config('app.name'),
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
