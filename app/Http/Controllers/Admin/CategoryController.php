<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalogue categories (client, 2026-10-31). Deleting one only removes it from its tests and packages.
 */
class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/categories', [
            'categories' => Category::withCount(['tests', 'packages'])->orderBy('name')->get()
                ->map->only(['id', 'name', 'slug', 'tests_count', 'packages_count']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $this->validateName($request);
        Category::create(['name' => $name, 'slug' => Slug::unique(Category::class, $name)]);

        return to_route('admin.categories.index')->with('status', 'Category added.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        // The slug stays, so shared catalogue links keep working.
        $category->update(['name' => $this->validateName($request, $category)]);

        return to_route('admin.categories.index')->with('status', 'Category renamed.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return to_route('admin.categories.index')->with('status', 'Category deleted.');
    }

    private function validateName(Request $request, ?Category $category = null): string
    {
        return trim($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category)],
        ])['name']);
    }
}
