<?php

namespace App\Http\Requests\Admin;

use App\Models\ServiceArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A service area's collection schedule: slot settings plus weekly opening ranges.
 * The bounds are sanity limits, not business rules; the values themselves are the admin's.
 */
class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $area = $this->route('service_area');

        return $area instanceof ServiceArea && $this->user()->can('update', $area);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slot_minutes' => ['required', 'integer', 'min:5', 'max:720'],
            'capacity_per_slot' => ['required', 'integer', 'min:1', 'max:1000'],
            'lead_time_hours' => ['required', 'numeric', 'min:0', 'max:720'],
            'booking_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'hours' => ['array', 'max:70'],
            'hours.*.weekday' => ['required', 'integer', 'between:1,7'],
            'hours.*.opens_at' => ['required', 'date_format:H:i'],
            'hours.*.closes_at' => ['required', 'date_format:H:i', 'after:hours.*.opens_at'],
        ];
    }

    /**
     * Ranges on the same weekday must not overlap.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ranges = collect($this->validated('hours', []))
                    ->map(fn (array $range, int $index) => [...$range, 'index' => $index])
                    ->groupBy('weekday');

                foreach ($ranges as $dayRanges) {
                    $sorted = $dayRanges->sortBy('opens_at')->values();
                    for ($i = 1; $i < $sorted->count(); $i++) {
                        if ($sorted[$i]['opens_at'] < $sorted[$i - 1]['closes_at']) {
                            $validator->errors()->add("hours.{$sorted[$i]['index']}.opens_at", 'This overlaps another range on the same day.');
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hours.*.closes_at.after' => 'Closing time must be after opening time.',
            'hours.*.opens_at.date_format' => 'Use a time like 07:30.',
            'hours.*.closes_at.date_format' => 'Use a time like 18:00.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slot_minutes' => 'slot length',
            'capacity_per_slot' => 'capacity',
            'lead_time_hours' => 'lead time',
            'booking_window_days' => 'booking window',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function scheduleAttributes(): array
    {
        return [
            'slot_minutes' => (int) $this->validated('slot_minutes'),
            'capacity_per_slot' => (int) $this->validated('capacity_per_slot'),
            'lead_time_minutes' => (int) round((float) $this->validated('lead_time_hours') * 60),
            'booking_window_days' => (int) $this->validated('booking_window_days'),
        ];
    }
}
