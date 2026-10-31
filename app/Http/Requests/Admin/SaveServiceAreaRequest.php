<?php

namespace App\Http\Requests\Admin;

use App\Enums\ServiceAreaType;
use App\Models\ServiceArea;
use App\Settings\Settings;
use App\Support\Countries;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update a service area. Which fields are required depends on its type:
 * postcodes (country + list), radius (centre + km) or polygon (3+ points).
 */
class SaveServiceAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $area = $this->route('service_area');

        return $area instanceof ServiceArea
            ? $this->user()->can('update', $area)
            : $this->user()->can('create', ServiceArea::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'country' => $this->filled('country') ? strtoupper(trim((string) $this->input('country'))) : null,
            // Postcodes are typed one per line or comma-separated.
            'postcode_list' => collect(preg_split('/[\r\n,]+/', (string) $this->input('postcodes')))
                ->map(fn (string $postcode) => trim($postcode))
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = ServiceAreaType::tryFrom((string) $this->input('type'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ServiceAreaType::class)],
            'is_active' => ['boolean'],
            // Empty means free (client, 2026-10-30: per-area fee).
            'collection_fee' => ['nullable', 'string', 'regex:'.Money::decimalPattern(app(Settings::class)->currency())],
            ...match ($type) {
                ServiceAreaType::Postcodes => [
                    'country' => ['required', Rule::in(Countries::codes())],
                    'postcode_list' => ['required', 'array', 'min:1', 'max:5000'],
                    'postcode_list.*' => ['string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]*$/'],
                ],
                ServiceAreaType::Radius => [
                    'center_latitude' => ['required', 'numeric', 'between:-90,90'],
                    'center_longitude' => ['required', 'numeric', 'between:-180,180'],
                    'radius_km' => ['required', 'numeric', 'min:0.1', 'max:1000'],
                ],
                ServiceAreaType::Polygon => [
                    'polygon' => ['required', 'array', 'min:3', 'max:500'],
                    'polygon.*' => ['required', 'array', 'size:2'],
                    'polygon.*.0' => ['required', 'numeric', 'between:-90,90'],
                    'polygon.*.1' => ['required', 'numeric', 'between:-180,180'],
                ],
                null => [],
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postcode_list.required' => 'Enter at least one postcode.',
            'collection_fee.regex' => 'Enter an amount like 99 or 99.50.',
            'postcode_list.*.regex' => 'Postcodes use letters, numbers, spaces and dashes only.',
            'center_latitude.required' => 'Tap the map to set the centre.',
            'center_longitude.required' => 'Tap the map to set the centre.',
            'polygon.required' => 'Tap the map to draw the zone.',
            'polygon.min' => 'A zone needs at least :min points.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['radius_km' => 'radius', 'postcode_list' => 'postcodes'];
    }

    public function type(): ServiceAreaType
    {
        return ServiceAreaType::from($this->validated('type'));
    }

    /**
     * Attributes for the model; fields that don't belong to the type are cleared.
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $type = $this->type();

        return [
            'name' => $this->validated('name'),
            'type' => $type,
            'is_active' => $this->validated('is_active'),
            'collection_fee_amount' => $this->filled('collection_fee')
                ? Money::toMinor($this->validated('collection_fee'), app(Settings::class)->currency())
                : 0,
            'country' => $type === ServiceAreaType::Postcodes ? $this->validated('country') : null,
            'center_latitude' => $type === ServiceAreaType::Radius ? (float) $this->validated('center_latitude') : null,
            'center_longitude' => $type === ServiceAreaType::Radius ? (float) $this->validated('center_longitude') : null,
            'radius_meters' => $type === ServiceAreaType::Radius ? (int) round((float) $this->validated('radius_km') * 1000) : null,
            'polygon' => $type === ServiceAreaType::Polygon
                ? array_map(fn (array $point) => [(float) $point[0], (float) $point[1]], $this->validated('polygon'))
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    public function postcodes(): array
    {
        return $this->type() === ServiceAreaType::Postcodes ? $this->validated('postcode_list') : [];
    }
}
