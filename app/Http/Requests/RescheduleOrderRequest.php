<?php

namespace App\Http\Requests;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $this->user()->can('reschedule', $order);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slot_starts_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slot_starts_at.required' => 'Choose a new slot.'];
    }

    public function slotStartsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('slot_starts_at'));
    }
}
