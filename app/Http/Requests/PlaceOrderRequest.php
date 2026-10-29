<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout. Shape is checked here; ownership, completeness, coverage, slot availability and the
 * prescription rule are checked by OrderService::place().
 */
class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // cart item id => list of patient ids
            'items' => ['required', 'array'],
            'items.*' => ['array'],
            // Not `distinct`: that compares across all items, and one patient can have several items.
            'items.*.*' => ['integer'],
            'address_id' => ['required', 'integer', Rule::exists('addresses', 'id')->where('user_id', $this->user()->id)],
            'slot_starts_at' => ['required', 'date'],
            // TODO(client): confirm accepted file types and size.
            'prescription' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address_id.required' => 'Choose an address for the collection.',
            'address_id.exists' => 'Choose one of your addresses.',
            'slot_starts_at.required' => 'Choose a collection slot.',
            'prescription.mimes' => 'Upload the prescription as a PDF, JPG or PNG.',
            'prescription.max' => 'The prescription must be 10 MB or smaller.',
        ];
    }

    /**
     * @return array<int, list<int>>
     */
    public function patientIdsByCartItem(): array
    {
        return collect($this->validated('items'))
            ->map(fn (array $ids) => array_values(array_unique(array_map('intval', $ids))))
            ->all();
    }

    public function slotStartsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('slot_starts_at'));
    }
}
