<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabPackage;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Pages → Home page (B-28): hero copy and featured packages.
 */
class HomeContentController extends Controller
{
    public function edit(Settings $settings): Response
    {
        return Inertia::render('admin/pages/home', [
            'home' => $settings->home(),
            'packages' => LabPackage::orderBy('name')->get(['id', 'name', 'is_active'])
                ->map->only(['id', 'name', 'is_active']),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'hero_title' => ['required', 'string', 'max:120'],
            'hero_text' => ['nullable', 'string', 'max:300'],
            'hero_button' => ['required', 'string', 'max:40'],
            'featured_package_ids' => ['present', 'array'],
            'featured_package_ids.*' => ['integer', 'distinct', Rule::exists('lab_packages', 'id')],
        ]);

        $settings->setHome([
            'hero_title' => $data['hero_title'],
            'hero_text' => $data['hero_text'] ?? null,
            'hero_button' => $data['hero_button'],
            'featured_package_ids' => array_values(array_map('intval', $data['featured_package_ids'])),
        ]);

        return to_route('admin.pages.home.edit')->with('status', 'Home page saved.');
    }
}
