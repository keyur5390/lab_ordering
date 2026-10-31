<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\PackagePricingMode;
use App\Models\Category;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Settings\Settings;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or update a package and its pricing.
 * Fixed: `price` is required. Discounted: `discount_type` and `discount_value` are required.
 */
class SaveLabPackageRequest extends FormRequest
{
    /** TODO(client): confirm the minimum number of tests in a package. */
    public const MIN_TESTS = 2;

    public function authorize(): bool
    {
        $package = $this->route('package');

        return $package instanceof LabPackage
            ? $this->user()->can('update', $package)
            : $this->user()->can('create', LabPackage::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $money = 'regex:'.Money::decimalPattern($this->currency());
        $fixed = $this->input('pricing_mode') === PackagePricingMode::Fixed->value;
        $discountType = $this->input('discount_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50', 'alpha_dash:ascii',
                Rule::unique(LabPackage::class)->ignore($this->route('package')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            // Categories (client, 2026-10-31): any number.
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists(Category::class, 'id')],
            'test_ids' => ['required', 'array', 'min:'.self::MIN_TESTS],
            'test_ids.*' => ['integer', 'distinct', Rule::exists(LabTest::class, 'id')],
            'pricing_mode' => ['required', Rule::enum(PackagePricingMode::class)],
            'price' => $fixed ? ['required', 'string', $money] : ['nullable'],
            'discount_type' => $fixed ? ['nullable'] : ['required', Rule::enum(DiscountType::class)],
            'discount_value' => match (true) {
                $fixed => ['nullable'],
                $discountType === DiscountType::Percent->value => ['required', 'integer', 'min:1', 'max:100'],
                default => ['required', 'string', $money],
            },
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->pricingMode() !== PackagePricingMode::Discounted) {
                    return;
                }

                if ($this->discountType() === DiscountType::Amount
                    && $this->discountAmount() >= LabTest::whereKey($this->validated('test_ids'))->sum('price_amount')) {
                    $validator->errors()->add('discount_value', "The discount must be less than the tests' total.");
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
            'price.regex' => 'Enter a price like 999 or 999.50.',
            'discount_value.regex' => 'Enter an amount like 100 or 99.50.',
            'test_ids.min' => 'Choose at least :min tests.',
            'test_ids.required' => 'Choose at least '.self::MIN_TESTS.' tests.',
        ];
    }

    /**
     * Validated attributes ready to save on the model (tests are synced separately).
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $fixed = $this->pricingMode() === PackagePricingMode::Fixed;

        return [
            ...$this->safe()->only(['name', 'code', 'description', 'is_active']),
            'pricing_mode' => $this->pricingMode(),
            'price_amount' => $fixed ? Money::toMinor($this->validated('price'), $this->currency()) : null,
            'discount_type' => $fixed ? null : $this->discountType(),
            'discount_value' => $fixed ? null : match ($this->discountType()) {
                DiscountType::Percent => (int) $this->validated('discount_value'),
                default => $this->discountAmount(),
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function testIds(): array
    {
        return array_map('intval', $this->validated('test_ids'));
    }

    private function pricingMode(): PackagePricingMode
    {
        return PackagePricingMode::from($this->validated('pricing_mode'));
    }

    private function discountType(): ?DiscountType
    {
        return DiscountType::tryFrom((string) $this->validated('discount_type'));
    }

    private function discountAmount(): int
    {
        return Money::toMinor((string) $this->validated('discount_value'), $this->currency());
    }

    private function currency(): ?Currency
    {
        return app(Settings::class)->currency();
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return array_map('intval', $this->validated('category_ids') ?? []);
    }
}
