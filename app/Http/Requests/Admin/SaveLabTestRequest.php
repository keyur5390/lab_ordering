<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Models\LabTest;
use App\Settings\Settings;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update a lab test. The price is entered as a decimal and stored in minor units.
 */
class SaveLabTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $test = $this->route('test');

        return $test instanceof LabTest
            ? $this->user()->can('update', $test)
            : $this->user()->can('create', LabTest::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
            'requires_prescription' => $this->boolean('requires_prescription'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50', 'alpha_dash:ascii',
                Rule::unique(LabTest::class)->ignore($this->route('test')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'sample_type' => ['required', 'string', 'max:100'],
            'preparation' => ['nullable', 'string', 'max:2000'],
            'turnaround_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'price' => ['required', 'string', 'regex:'.Money::decimalPattern(app(Settings::class)->currency())],
            'is_active' => ['boolean'],
            // Categories (client, 2026-10-31): any number.
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists(Category::class, 'id')],
            'requires_prescription' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.regex' => 'Enter a price like 499 or 499.50.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'turnaround_hours' => 'turnaround',
        ];
    }

    /**
     * Validated attributes ready to save on the model.
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $data = $this->safe()->except('price');
        $data['price_amount'] = Money::toMinor($this->validated('price'), app(Settings::class)->currency());

        return $data;
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_map('intval', $this->validated('category_ids') ?? []);
    }
}
