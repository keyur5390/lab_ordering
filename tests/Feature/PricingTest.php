<?php

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\LabTest;
use App\Models\Order;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\CouponService;
use App\Services\OrderPricingService;
use App\Services\ServiceAreaService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->settings = app(Settings::class);
    $this->settings->setCurrency(Currency::INR);
    $this->settings->setTimezone('Asia/Kolkata');
    // Thursday 29 Oct 2026, 06:00 in Kolkata.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 06:00', 'Asia/Kolkata'));

    $this->user = User::factory()->create();
    $this->self = $this->user->patients()->sole();
    $this->self->update(['date_of_birth' => '1990-01-01', 'sex' => 'female']);

    // ₹50 home collection fee in this area.
    $this->area = ServiceArea::factory()->scheduled(capacity: 5)->create(['country' => 'IN', 'collection_fee_amount' => 5000]);
    app(ServiceAreaService::class)->syncPostcodes($this->area, ['400001']);
    $this->address = Address::factory()->for($this->user)->create(['postcode' => '400001', 'country' => 'IN']);

    // ₹1,000 test.
    $this->test = LabTest::factory()->create(['price_amount' => 100000]);
    $this->item = Cart::create(['user_id' => $this->user->id])->items()->create(['lab_test_id' => $this->test->id]);
});

function quote(int $subtotal, ?Coupon $coupon = null, ?ServiceArea $area = null): array
{
    return app(OrderPricingService::class)->quote($subtotal, $area ?? test()->area, $coupon);
}

function placeWith(array $overrides = [])
{
    return test()->actingAs(test()->user)->post(route('checkout.store'), [
        'items' => [test()->item->id => [test()->self->id]],
        'address_id' => test()->address->id,
        'slot_starts_at' => '2026-10-30T09:00:00+05:30',
        'payment_method' => 'pay_at_collection',
        ...$overrides,
    ]);
}

describe('working out the price', function () {
    it('adds the area fee, and waives it from the free-collection value', function () {
        expect(quote(100000))->toMatchArray(['collection_fee' => 5000, 'collection_fee_waived' => false, 'tax' => 0, 'total' => 105000]);

        $this->settings->setFreeCollectionAbove(100000);
        expect(quote(100000))->toMatchArray(['collection_fee' => 0, 'collection_fee_waived' => true, 'total' => 100000])
            ->and(quote(99999))->toMatchArray(['collection_fee' => 5000, 'total' => 104999]);

        // Free areas stay free, and are not "waived".
        expect(quote(1000, area: ServiceArea::factory()->create()))->toMatchArray(['collection_fee' => 0, 'collection_fee_waived' => false]);
    });

    it('compares the free-collection value with the total after the coupon', function () {
        $this->settings->setFreeCollectionAbove(100000);
        $coupon = Coupon::factory()->amount(10000)->create();

        expect(quote(100000, $coupon))->toMatchArray(['discount' => 10000, 'collection_fee' => 5000, 'total' => 95000]);
    });

    it('adds tax on top, or shows the tax inside the price', function () {
        $this->settings->setTaxRateBasisPoints(1800);

        $this->settings->setTaxInclusive(false);
        // (₹1,000 + ₹50 fee) × 18% = ₹189.
        expect(quote(100000))->toMatchArray(['tax_rate_basis_points' => 1800, 'tax_inclusive' => false, 'tax' => 18900, 'total' => 123900]);

        $this->settings->setTaxInclusive(true);
        // ₹1,050 includes ₹160.17 tax: 105000 − round(105000 / 1.18).
        expect(quote(100000))->toMatchArray(['tax_inclusive' => true, 'tax' => 16017, 'total' => 105000])
            ->and(quote(100, area: ServiceArea::factory()->create()))->toMatchArray(['tax' => 15, 'total' => 100]);
    });

    it('takes percent coupons off with a cap, and fixed ones up to the total', function () {
        $percent = Coupon::factory()->create(['discount_value' => 15]);
        $capped = Coupon::factory()->create(['discount_value' => 50, 'max_discount_amount' => 20000]);
        $fixed = Coupon::factory()->amount(150000)->create();

        expect(quote(100000, $percent)['discount'])->toBe(15000)
            ->and(quote(100000, $capped)['discount'])->toBe(20000)
            ->and(quote(100000, $fixed))->toMatchArray(['discount' => 100000, 'total' => 5000]);
    });
});

describe('coupon rules', function () {
    it('explains why a coupon can\'t be used', function (array $attributes, ?string $problem) {
        $coupon = Coupon::factory()->create($attributes);

        expect(app(CouponService::class)->problem($coupon, $this->user, 100000))->toBe($problem);
    })->with([
        'fine' => [[], null],
        'switched off' => [['is_active' => false], 'This code has expired.'],
        'ended yesterday' => [['ends_on' => '2026-10-28'], 'This code has expired.'],
        'ends today' => [['ends_on' => '2026-10-29'], null],
        'starts tomorrow' => [['starts_on' => '2026-10-30'], "This code isn't valid yet."],
        'minimum order' => [['min_order_amount' => 150000], 'Spend at least ₹1,500.00 to use this code.'],
    ]);

    it('counts uses overall and per customer, and gives back the use of cancelled orders', function () {
        $coupon = Coupon::factory()->create(['usage_limit' => 2, 'per_customer_limit' => 1]);
        $coupons = app(CouponService::class);
        $mine = Order::factory()->for($this->user)->create(['coupon_id' => $coupon->id]);

        expect($coupons->problem($coupon, $this->user, 100000))->toBe("You've already used this code.")
            ->and($coupons->problem($coupon, User::factory()->create(), 100000))->toBeNull();

        Order::factory()->create(['coupon_id' => $coupon->id]);
        expect($coupons->problem($coupon, User::factory()->create(), 100000))->toBe('This code has been fully used.');

        $mine->update(['status' => OrderStatus::Cancelled]);
        expect($coupons->problem($coupon, $this->user, 100000))->toBeNull();
    });

    it('finds codes whatever their case or spacing', function () {
        Coupon::factory()->create(['code' => 'WELCOME10']);

        expect(app(CouponService::class)->find('  welcome10 ')?->code)->toBe('WELCOME10');
    });
});

describe('checkout', function () {
    it('shows the price breakdown and re-prices for patients and coupons', function () {
        Coupon::factory()->create(['code' => 'SAVE10', 'description' => '10% off your first order']);
        $this->user->patients()->create(['name' => 'Parent', 'relationship' => 'parent', 'date_of_birth' => '1960-01-01', 'sex' => 'male']);
        $this->settings->setTaxRateBasisPoints(500);
        $this->settings->setTaxInclusive(false);

        $this->actingAs($this->user)->get(route('checkout.show'))
            ->assertInertia(fn (Assert $page) => $page->where('quote.subtotal', 100000)->where('quote.collection_fee', 5000)
                ->where('quote.tax', 5250)->where('quote.total', 110250)->where('coupon', null));

        $this->actingAs($this->user)->get(route('checkout.show', ['counts' => [$this->item->id => 2], 'coupon' => 'save10']))
            ->assertInertia(fn (Assert $page) => $page->where('quote.subtotal', 200000)->where('quote.discount', 20000)
                ->where('coupon.code', 'SAVE10')->where('coupon.description', '10% off your first order')->where('coupon.error', null));

        // Counts are capped at the number of the customer's patients.
        $this->actingAs($this->user)->get(route('checkout.show', ['counts' => [$this->item->id => 50]]))
            ->assertInertia(fn (Assert $page) => $page->where('quote.subtotal', 200000));

        $this->actingAs($this->user)->get(route('checkout.show', ['coupon' => 'NOPE']))
            ->assertInertia(fn (Assert $page) => $page->where('coupon.error', "We don't recognise this code.")->where('quote.discount', 0));
    });

    it('charges and records the fee, coupon and tax on the order', function () {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10']);
        $this->settings->setTaxRateBasisPoints(1800);
        $this->settings->setTaxInclusive(false);

        placeWith(['coupon_code' => 'save10'])->assertSessionHasNoErrors();

        // ₹1,000 − ₹100 + ₹50 = ₹950, + 18% = ₹1,121.
        expect(Order::sole()->only(['subtotal_amount', 'coupon_id', 'coupon_code', 'discount_amount', 'collection_fee_amount', 'tax_rate_basis_points', 'tax_inclusive', 'tax_amount', 'total_amount']))
            ->toBe([
                'subtotal_amount' => 100000, 'coupon_id' => $coupon->id, 'coupon_code' => 'SAVE10', 'discount_amount' => 10000,
                'collection_fee_amount' => 5000, 'tax_rate_basis_points' => 1800, 'tax_inclusive' => false, 'tax_amount' => 17100, 'total_amount' => 112100,
            ]);

        $this->actingAs($this->user)->get(route('orders.show', Order::sole()))
            ->assertInertia(fn (Assert $page) => $page->where('order.discount_amount', 10000)->where('order.collection_fee_amount', 5000)
                ->where('order.tax_rate_percent', 18)->where('order.tax_amount', 17100));
    });

    it('refuses codes that can\'t be used, and orders below the minimum', function () {
        Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 1]);
        Order::factory()->create(['coupon_id' => Coupon::first()->id]);

        placeWith(['coupon_code' => 'ONCE'])->assertSessionHasErrors(['coupon_code' => 'This code has been fully used.']);
        placeWith(['coupon_code' => 'MISSING'])->assertSessionHasErrors('coupon_code');

        $this->settings->setMinimumOrderAmount(150000);
        placeWith()->assertSessionHasErrors(['order' => 'The minimum order is ₹1,500.00.']);
        $this->actingAs($this->user)->get(route('checkout.show'))->assertInertia(fn (Assert $page) => $page->where('quote.minimum', 150000));

        expect(Order::count())->toBe(1);
    });
});

describe('admin', function () {
    beforeEach(fn () => $this->admin = User::factory()->admin()->create());

    it('creates, edits and lists coupons', function () {
        $this->actingAs($this->admin)->post(route('admin.coupons.store'), [
            'code' => 'welcome10', 'discount_type' => 'percent', 'discount_value' => '10', 'max_discount' => '250',
            'min_order' => '499.50', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30', 'usage_limit' => '100',
            'per_customer_limit' => '1', 'is_active' => true,
        ])->assertRedirect(route('admin.coupons.index'));

        $coupon = Coupon::sole();
        expect($coupon->only(['code', 'discount_value', 'max_discount_amount', 'min_order_amount', 'usage_limit', 'per_customer_limit']))
            ->toBe(['code' => 'WELCOME10', 'discount_value' => 10, 'max_discount_amount' => 25000, 'min_order_amount' => 49950, 'usage_limit' => 100, 'per_customer_limit' => 1]);

        $this->actingAs($this->admin)->put(route('admin.coupons.update', $coupon), ['code' => 'WELCOME10', 'discount_type' => 'amount', 'discount_value' => '75.50', 'is_active' => false])
            ->assertSessionHasNoErrors();
        expect($coupon->fresh()->only(['discount_type', 'discount_value', 'max_discount_amount', 'is_active']))
            ->toBe(['discount_type' => DiscountType::Amount, 'discount_value' => 7550, 'max_discount_amount' => null, 'is_active' => false]);

        $this->actingAs($this->admin)->get(route('admin.coupons.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/coupons/index')->where('coupons.0.discount_label', '₹75.50 off')->where('coupons.0.uses', 0));
    });

    it('validates coupons', function (array $data, array $errors) {
        Coupon::factory()->create(['code' => 'TAKEN']);

        $this->actingAs($this->admin)->post(route('admin.coupons.store'), ['code' => 'NEW', 'discount_type' => 'percent', 'discount_value' => '10', ...$data])
            ->assertSessionHasErrors($errors);
    })->with([
        'code in use, any case' => [['code' => 'taken'], ['code']],
        'code with spaces' => [['code' => 'TWO WORDS'], ['code']],
        'percent over 100' => [['discount_value' => '101'], ['discount_value']],
        'zero amount' => [['discount_type' => 'amount', 'discount_value' => '0'], ['discount_value']],
        'cap on a fixed amount' => [['discount_type' => 'amount', 'discount_value' => '50', 'max_discount' => '10'], ['max_discount']],
        'ends before it starts' => [['starts_on' => '2026-11-10', 'ends_on' => '2026-11-01'], ['ends_on']],
    ]);

    it('deletes unused coupons only, and keeps coupons admin-only', function () {
        $used = Coupon::factory()->create();
        Order::factory()->create(['coupon_id' => $used->id]);
        $unused = Coupon::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.coupons.destroy', $used))->assertSessionHasErrors('delete');
        $this->actingAs($this->admin)->delete(route('admin.coupons.destroy', $unused))->assertRedirect(route('admin.coupons.index'));
        expect(Coupon::pluck('id')->all())->toBe([$used->id]);

        $this->actingAs($this->user)->get(route('admin.coupons.index'))->assertForbidden();
    });

    it('saves the pricing settings and the area fee', function () {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), [
            'currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'free_collection_above' => '999', 'minimum_order' => '199.50',
            'tax_rate' => '5.5', 'tax_inclusive' => false,
        ])->assertSessionHasNoErrors();

        expect($this->settings->freeCollectionAbove())->toBe(99900)
            ->and($this->settings->minimumOrderAmount())->toBe(19950)
            ->and($this->settings->taxRateBasisPoints())->toBe(550)
            ->and($this->settings->taxInclusive())->toBeFalse();
        $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertInertia(fn (Assert $page) => $page->where('taxRate', '5.5')->where('freeCollectionAbove', '999.00'));

        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'tax_rate' => '101'])
            ->assertSessionHasErrors('tax_rate');

        $this->actingAs($this->admin)->put(route('admin.service-areas.update', $this->area), [
            'name' => 'Mumbai', 'type' => 'postcodes', 'country' => 'IN', 'postcodes' => '400001', 'postcode_list' => ['400001'],
            'is_active' => true, 'collection_fee' => '75.25',
        ])->assertSessionHasNoErrors();
        expect($this->area->fresh()->collection_fee_amount)->toBe(7525);
    });
});
