<?php

namespace App\Http\Requests\Admin;

use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A lab branch (client, 2026-10-30): address with a map pin, contact details, directions and a photo.
 */
class SaveBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:20'],
            'country' => ['required', Rule::in(Countries::codes())],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9][0-9 \-]{6,18}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'directions' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Tap the map to place the branch.',
            'longitude.required' => 'Tap the map to place the branch.',
            'phone.regex' => 'Enter a phone number using digits, spaces, dashes and an optional leading +.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function branchAttributes(): array
    {
        return [
            ...$this->safe()->except(['photo', 'remove_photo', 'is_active']),
            'latitude' => (float) $this->validated('latitude'),
            'longitude' => (float) $this->validated('longitude'),
            'email' => $this->filled('email') ? strtolower($this->validated('email')) : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
