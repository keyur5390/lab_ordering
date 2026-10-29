<?php

namespace App\Http\Requests\Admin;

use App\Models\ServiceArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A closure is whole days (a single date or a range) or part of a single day (from–to time).
 */
class StoreClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'service_area_id' => $this->filled('service_area_id') ? $this->input('service_area_id') : null,
            'ends_on' => $this->filled('ends_on') ? $this->input('ends_on') : $this->input('starts_on'),
            'whole_day' => $this->boolean('whole_day'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $partial = ! $this->boolean('whole_day');

        return [
            'service_area_id' => ['nullable', 'integer', Rule::exists(ServiceArea::class, 'id')],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', ...($partial ? ['same:starts_on'] : [])],
            'whole_day' => ['boolean'],
            'starts_at' => $partial ? ['required', 'date_format:H:i'] : ['nullable'],
            'ends_at' => $partial ? ['required', 'date_format:H:i', 'after:starts_at'] : ['nullable'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_on.same' => 'A part-day closure covers a single date.',
            'ends_at.after' => 'The end time must be after the start time.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'end date', 'starts_at' => 'from', 'ends_at' => 'to'];
    }

    /**
     * @return array<string, mixed>
     */
    public function closureAttributes(): array
    {
        $wholeDay = $this->validated('whole_day');

        return [
            'service_area_id' => $this->validated('service_area_id'),
            'starts_on' => $this->validated('starts_on'),
            'ends_on' => $this->validated('ends_on'),
            'starts_at' => $wholeDay ? null : $this->validated('starts_at'),
            'ends_at' => $wholeDay ? null : $this->validated('ends_at'),
            'reason' => $this->validated('reason'),
        ];
    }
}
