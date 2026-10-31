<?php

namespace App\Http\Requests;

use App\Enums\CartItemType;
use App\Models\LabPackage;
use App\Models\LabTest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anyone, guest or customer, may add a published test or package to their own cart.
 */
class AddCartItemRequest extends FormRequest
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
        $table = $this->input('type') === CartItemType::Package->value ? LabPackage::class : LabTest::class;

        return [
            'type' => ['required', Rule::enum(CartItemType::class)],
            'id' => ['required', 'integer', Rule::exists($table, 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'This item is no longer available.',
        ];
    }

    public function itemType(): CartItemType
    {
        return CartItemType::from($this->validated('type'));
    }

    public function itemId(): int
    {
        return (int) $this->validated('id');
    }
}
