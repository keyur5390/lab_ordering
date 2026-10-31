<?php

namespace App\Http\Controllers;

use App\Enums\PatientRelationship;
use App\Enums\Sex;
use App\Http\Requests\SavePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's patient profiles (PROJECT.md §2): themselves and family members.
 */
class PatientController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('patients/index', [
            'patients' => PatientResource::collection($request->user()->patients()->get()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('patients/form', ['patient' => null, ...$this->options()]);
    }

    public function store(SavePatientRequest $request): RedirectResponse
    {
        $request->user()->patients()->create($request->validated());

        return to_route('patients.index')->with('status', 'Patient added.');
    }

    public function edit(Patient $patient): Response
    {
        Gate::authorize('update', $patient);

        return Inertia::render('patients/form', ['patient' => PatientResource::make($patient), ...$this->options()]);
    }

    public function update(SavePatientRequest $request, Patient $patient): RedirectResponse
    {
        // The "Self" profile keeps its relationship whatever is submitted.
        $patient->update($request->safe()->except($patient->isSelf() ? ['relationship'] : []));

        return to_route('patients.index')->with('status', 'Patient saved.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        Gate::authorize('delete', $patient);

        $patient->delete();

        return to_route('patients.index')->with('status', 'Patient removed.');
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function options(): array
    {
        $option = fn (PatientRelationship|Sex $case) => ['value' => $case->value, 'label' => $case->label()];

        return [
            'relationships' => array_map($option, array_values(array_filter(
                PatientRelationship::cases(),
                fn (PatientRelationship $relationship) => $relationship !== PatientRelationship::Self,
            ))),
            'sexes' => array_map($option, Sex::cases()),
        ];
    }
}
