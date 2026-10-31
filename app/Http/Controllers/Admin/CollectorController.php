<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Collectors and the service areas they work in (client, 2026-10-30). People become collectors
 * with `php artisan user:role {email} collector`.
 */
class CollectorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/collectors/index', [
            'collectors' => User::where('role', UserRole::Collector)->with('collectorAreas:id')->orderBy('name')->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'area_ids' => $user->collectorAreas->pluck('id'),
                ]),
            'areas' => ServiceArea::orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function update(Request $request, User $collector): RedirectResponse
    {
        abort_unless($collector->isCollector(), 404);
        $validated = $request->validate([
            'area_ids' => ['present', 'array'],
            'area_ids.*' => ['integer', Rule::exists('service_areas', 'id')],
        ]);

        $collector->collectorAreas()->sync($validated['area_ids']);

        return to_route('admin.collectors.index')->with('status', "{$collector->name}'s areas saved.");
    }
}
