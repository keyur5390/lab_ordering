<?php

namespace App\Http\Requests;

use App\Enums\PatientRelationship;
use App\Enums\Sex;
use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add or edit one of the customer's patient profiles. Only the sign-up "Self" profile has the
 * Self relationship, and its relationship can't be changed.
 */
class SavePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');

        // Creating needs only a signed-in, verified customer (route middleware).
        return ! $patient instanceof Patient || $this->user()->can('update', $patient);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'relationship' => $this->editingSelf()
                ? ['prohibited']
                : ['required', Rule::enum(PatientRelationship::class)->except([PatientRelationship::Self])],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'sex' => ['required', Rule::enum(Sex::class)],
            // TODO(client): phone format depends on the launch country (PROJECT.md §1).
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9][0-9 \-]{6,18}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'relationship.enum' => 'Choose how this person is related to you.',
            'date_of_birth.before_or_equal' => 'The date of birth cannot be in the future.',
            'phone.regex' => 'Enter a phone number using digits, spaces, dashes and an optional leading +.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['date_of_birth' => 'date of birth'];
    }

    private function editingSelf(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof Patient && $patient->isSelf();
    }
}
