<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveLabTestRequest;
use App\Http\Resources\LabTestResource;
use App\Models\Category;
use App\Models\LabTest;
use App\Settings\Settings;
use App\Support\Money;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LabTestController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', LabTest::class);

        return Inertia::render('admin/tests/index', [
            'tests' => LabTestResource::collection(LabTest::orderBy('name')->paginate(25)),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', LabTest::class);

        return Inertia::render('admin/tests/form', ['test' => null, 'price' => '', 'categories' => $this->categories()]);
    }

    public function store(SaveLabTestRequest $request): RedirectResponse
    {
        $test = LabTest::create([
            ...$request->modelAttributes(),
            'slug' => Slug::unique(LabTest::class, $request->validated('name')),
        ]);
        $test->categories()->sync($request->categoryIds());

        return to_route('admin.tests.index')->with('status', 'Test created.');
    }

    public function edit(LabTest $test, Settings $settings): Response
    {
        Gate::authorize('update', $test);

        return Inertia::render('admin/tests/form', [
            'test' => LabTestResource::make($test->load('categories')),
            'price' => Money::toDecimal($test->price_amount, $settings->currency()),
            'categories' => $this->categories(),
        ]);
    }

    public function update(SaveLabTestRequest $request, LabTest $test): RedirectResponse
    {
        $test->update($request->modelAttributes());
        $test->categories()->sync($request->categoryIds());

        return to_route('admin.tests.index')->with('status', 'Test saved.');
    }

    public function destroy(LabTest $test): RedirectResponse
    {
        Gate::authorize('delete', $test);

        if ($test->packages()->exists()) {
            return back()->withErrors([
                'delete' => "\"{$test->name}\" is part of a package. Remove it from its packages first, or unpublish it instead.",
            ]);
        }

        $test->delete();

        return to_route('admin.tests.index')->with('status', 'Test deleted.');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function categories(): array
    {
        return Category::orderBy('name')->get(['id', 'name'])->map->only(['id', 'name'])->all();
    }
}
