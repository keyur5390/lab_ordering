<?php

namespace App\Http\Controllers;

use App\Http\Resources\LabPackageResource;
use App\Http\Resources\LabTestResource;
use App\Models\LabPackage;
use App\Models\LabTest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public catalogue: published packages and tests only.
 * TODO(client): categories, search and filters are OPEN in PROJECT.md §3.
 */
class CatalogueController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('catalogue/index', [
            'packages' => LabPackageResource::collection(LabPackage::active()->with('tests')->orderBy('name')->get()),
            'tests' => LabTestResource::collection(LabTest::active()->orderBy('name')->get()),
        ]);
    }

    public function showTest(LabTest $test): Response
    {
        abort_unless($test->is_active, 404);

        return Inertia::render('catalogue/test', [
            'test' => LabTestResource::make($test),
        ]);
    }

    public function showPackage(LabPackage $package): Response
    {
        abort_unless($package->is_active, 404);

        return Inertia::render('catalogue/package', [
            'package' => LabPackageResource::make($package->load('tests')),
        ]);
    }
}
