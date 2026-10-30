<?php

namespace App\Services;

use App\Enums\CartItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\Order;
use App\Models\Patient;
use App\Models\ServiceArea;
use App\Models\User;
use App\Settings\Settings;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Places, cancels and reschedules home-collection orders (PROJECT.md §5, §8).
 */
class OrderService
{
    /** Private disk for prescriptions (CONVENTIONS: health data is never public). */
    public const PRESCRIPTION_DISK = 'local';

    public function __construct(
        private CartService $cart,
        private PackagePricingService $pricing,
        private ServiceAreaService $areas,
        private SlotService $slots,
        private PaymentService $payments,
        private OrderNotifier $notifier,
        private OrderPricingService $orderPricing,
        private CouponService $coupons,
        private Settings $settings,
    ) {}

    /**
     * Places an order from the customer's cart. Each available cart item is booked for the
     * chosen patients; price is per patient. The cart is emptied afterwards. Pay-at-collection
     * orders are confirmed at once; online orders wait for payment, holding their slot for the
     * admin-set hold time (client, 2026-10-29).
     *
     * @param  array<int, list<int>>  $patientIdsByCartItem  cart item id => patient ids
     *
     * @throws ValidationException
     */
    public function place(User $user, Cart $cart, array $patientIdsByCartItem, Address $address, CarbonImmutable $slotStartsAt, ?UploadedFile $prescription, PaymentMethod $paymentMethod = PaymentMethod::PayAtCollection, ?string $couponCode = null): Order
    {
        $currency = $this->settings->currency()
            ?? throw ValidationException::withMessages(['order' => 'Ordering is not open yet. Please try again later.']);
        $online = $paymentMethod === PaymentMethod::Online;
        if ($online && ! $this->payments->onlineAvailable()) {
            throw ValidationException::withMessages(['payment_method' => 'Online payment is not available. Please choose pay at collection.']);
        }

        $cartItems = $cart->items()->with(['test', 'package.tests'])->get()
            ->filter(fn (CartItem $item) => ($item->test ?? $item->package)?->is_active);
        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Your cart has nothing that can be booked.']);
        }

        $patients = $user->patients()->get()->keyBy('id');
        $errors = [];
        foreach ($cartItems as $item) {
            $ids = $patientIdsByCartItem[$item->id] ?? [];
            if ($ids === []) {
                $errors["items.{$item->id}"] = 'Choose who this is for.';
            } elseif (collect($ids)->contains(fn (int $id) => ! $patients->get($id)?->isComplete())) {
                $errors["items.{$item->id}"] = 'Choose patients from your list whose details are complete.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $area = $this->areas->coveringArea($address);
        if (! $area?->hasSchedule()) {
            throw ValidationException::withMessages(['address_id' => 'We do not collect at this address yet.']);
        }

        if ($this->prescriptionRequired($cartItems->all()) && ! $prescription) {
            throw ValidationException::withMessages(['prescription' => 'A prescription is needed for one or more tests.']);
        }

        $lines = [];
        foreach ($cartItems as $item) {
            foreach ($patientIdsByCartItem[$item->id] as $patientId) {
                $lines[] = $this->orderLine($item, $patients->get($patientId));
            }
        }
        $subtotal = array_sum(array_column($lines, 'price_amount'));

        $minimum = $this->settings->minimumOrderAmount();
        if ($minimum !== null && $subtotal < $minimum) {
            throw ValidationException::withMessages(['order' => 'The minimum order is '.Money::format($minimum, $currency->value).'.']);
        }

        $order = DB::transaction(function () use ($user, $cart, $lines, $subtotal, $address, $area, $slotStartsAt, $prescription, $currency, $paymentMethod, $online, $couponCode) {
            $this->lockAndCheckSlot($area, $slotStartsAt);
            $coupon = $couponCode ? $this->lockAndCheckCoupon($couponCode, $user, $subtotal) : null;
            $price = $this->orderPricing->quote($subtotal, $area, $coupon);

            $order = $user->orders()->create([
                'status' => $online ? OrderStatus::PendingPayment : OrderStatus::Confirmed,
                'payment_method' => $paymentMethod,
                'payment_expires_at' => $online ? now()->addMinutes($this->settings->paymentHoldMinutes()) : null,
                'service_area_id' => $area->id,
                'slot_starts_at' => $slotStartsAt->utc(),
                'slot_ends_at' => $slotStartsAt->addMinutes($area->slot_minutes)->utc(),
                'address_id' => $address->id,
                'address' => $address->only(['label', 'line1', 'line2', 'city', 'region', 'postcode', 'country', 'latitude', 'longitude']),
                'currency' => $currency->value,
                'subtotal_amount' => $subtotal,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'discount_amount' => $price['discount'],
                'collection_fee_amount' => $price['collection_fee'],
                'tax_rate_basis_points' => $price['tax_rate_basis_points'],
                'tax_inclusive' => $price['tax_inclusive'],
                'tax_amount' => $price['tax'],
                'total_amount' => $price['total'],
            ]);
            $order->update(['number' => sprintf('ORD-%06d', $order->id)]);
            $order->items()->createMany($lines);

            if ($prescription) {
                $order->update([
                    'prescription_path' => $prescription->store("prescriptions/{$order->id}", self::PRESCRIPTION_DISK),
                    'prescription_name' => $prescription->getClientOriginalName(),
                ]);
            }

            $cart->items()->delete();

            return $order;
        });

        $this->notifier->placed($order);

        return $order;
    }

    /**
     * @param  list<CartItem>  $cartItems
     */
    public function prescriptionRequired(array $cartItems): bool
    {
        return collect($cartItems)->contains(fn (CartItem $item) => $item->test
            ? $item->test->requires_prescription
            : (bool) $item->package?->tests->contains('requires_prescription', true));
    }

    /**
     * Cancels the order and refunds online payments in full, at once (client, 2026-10-29).
     * TODO(client): admin cancellations also refund in full; confirm.
     *
     * @return bool|null whether the refund went through; null when nothing was paid online
     */
    public function cancel(Order $order, ?User $by = null): ?bool
    {
        $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
        $this->notifier->cancelled($order);

        return $this->payments->refundRemaining($order, $by, 'Order cancelled');
    }

    /**
     * @throws ValidationException
     */
    public function reschedule(Order $order, CarbonImmutable $slotStartsAt): void
    {
        $area = $order->serviceArea;
        if (! $area) {
            throw ValidationException::withMessages(['slot_starts_at' => 'This order can no longer be rescheduled online.']);
        }
        if ($slotStartsAt->equalTo($order->slot_starts_at)) {
            throw ValidationException::withMessages(['slot_starts_at' => 'Choose a different slot.']);
        }

        DB::transaction(function () use ($order, $area, $slotStartsAt) {
            $this->lockAndCheckSlot($area, $slotStartsAt);

            $order->update([
                'slot_starts_at' => $slotStartsAt->utc(),
                'slot_ends_at' => $slotStartsAt->addMinutes($area->slot_minutes)->utc(),
                // The reminder is sent again for the new slot, and a failed visit is behind us.
                'reminder_sent_at' => null,
                'visit_failed_at' => null,
                'visit_failure_reason' => null,
                'visit_failure_note' => null,
            ]);
        });

        $this->notifier->rescheduled($order);
    }

    /**
     * Whether the customer may still cancel or reschedule: before collection, and at least the
     * admin-set cutoff before the slot. Until the cutoff is set, only admins can.
     */
    public function customerCanChange(Order $order, ?CarbonImmutable $now = null): bool
    {
        $cutoff = $this->settings->cancellationCutoffHours();

        return $order->status->isBeforeCollection()
            && $cutoff !== null
            && ($now ?? CarbonImmutable::now())->addHours($cutoff)->lte($order->slot_starts_at);
    }

    /**
     * Locks the area so two checkouts can't both take the last place in a slot, then re-checks it.
     *
     * @throws ValidationException
     */
    private function lockAndCheckSlot(ServiceArea $area, CarbonImmutable $slotStartsAt): void
    {
        ServiceArea::whereKey($area->id)->lockForUpdate()->first();

        if (! $this->slots->isBookable($area, $slotStartsAt)) {
            throw ValidationException::withMessages(['slot_starts_at' => 'That slot is no longer available. Please choose another.']);
        }
    }

    /**
     * Locks the coupon so two orders can't both take its last use, then checks it.
     *
     * @throws ValidationException
     */
    private function lockAndCheckCoupon(string $code, User $user, int $subtotal): Coupon
    {
        $coupon = Coupon::where('code', Coupon::normalise($code))->lockForUpdate()->first();
        $problem = $coupon ? $this->coupons->problem($coupon, $user, $subtotal) : 'We don\'t recognise this code.';

        if ($problem) {
            throw ValidationException::withMessages(['coupon_code' => $problem]);
        }

        return $coupon;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderLine(CartItem $item, Patient $patient): array
    {
        $isPackage = $item->package !== null;
        /** @var LabTest|LabPackage $model */
        $model = $item->package ?? $item->test;

        return [
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'patient_date_of_birth' => $patient->date_of_birth,
            'patient_sex' => $patient->sex->value,
            'item_type' => $isPackage ? CartItemType::Package : CartItemType::Test,
            'lab_test_id' => $item->lab_test_id,
            'lab_package_id' => $item->lab_package_id,
            'name' => $model->name,
            'code' => $model->code,
            'test_names' => $isPackage ? $item->package->tests->pluck('name')->all() : null,
            'price_amount' => $isPackage ? $this->pricing->price($item->package) : $item->test->price_amount,
        ];
    }
}
