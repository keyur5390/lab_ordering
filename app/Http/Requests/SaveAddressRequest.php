<?php

namespace App\Http\Requests;

use App\Models\Address;
use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add or edit a customer's address (generic international format, client 2026-10-29).
 * The map pin is optional, but both coordinates are needed if either is given.
 */
class SaveAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $address = $this->route('address');

        return ! $address instanceof Address || $this->user()->can('update', $address);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => strtoupper(trim((string) $this->input('country'))),
            'latitude' => $this->filled('latitude') ? $this->input('latitude') : null,
            'longitude' => $this->filled('longitude') ? $this->input('longitude') : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postcode' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]*$/'],
            'country' => ['required', Rule::in(Countries::codes())],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postcode.regex' => 'Use letters, numbers, spaces and dashes only.',
            'country.in' => 'Choose a country from the list.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['line1' => 'address line 1', 'line2' => 'address line 2'];
    }
}
