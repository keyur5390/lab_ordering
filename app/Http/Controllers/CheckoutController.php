<?php

namespace App\Http\Controllers;

use App\Enums\CollectionType;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\AddressResource;
use App\Http\Resources\BranchResource;
use App\Http\Resources\PatientResource;
use App\Models\Address;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\ServiceArea;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\OrderPricingService;
use App\Services\OrderService;
use App\Services\Payments\PaymentGatewayException;
use App\Services\PaymentService;
use App\Services\ServiceAreaService;
use App\Services\SlotService;
use App\Settings\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checkout (PROJECT.md §5): patients per item, address, slot, prescription when needed, payment
 * method, then place. Online orders go straight on to the gateway's checkout page.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private OrderService $orders,
        private ServiceAreaService $areas,
        private SlotService $slots,
        private PaymentService $payments,
        private OrderPricingService $orderPricing,
        private CouponService $coupons,
        private Settings $settings,
    ) {}

    public function show(Request $request, Settings $settings): Response
    {
        $cart = $this->cart->current($request);
        $lines = collect($cart ? $this->cart->lines($cart) : [])->where('available', true)->values();

        if ($lines->isEmpty()) {
            return to_route('cart.show')->with('status', 'Add a test or package to check out.');
        }

        $user = $request->user();
        $addresses = $user->addresses()->get();
        $addressId = $request->integer('address_id') ?: $addresses->first(fn (Address $address) => $this->areas->covers($address))?->id;
        $address = $addresses->firstWhere('id', $addressId);
        $area = $address ? $this->areas->coveringArea($address) : null;

        // Lab visits (client, 2026-10-30): branches that take bookings, and the chosen one's slots.
        $branches = Branch::where('is_active', true)->orderBy('name')->get()->filter->hasSchedule()->values();
        $labVisit = $request->input('collection_type') === CollectionType::LabVisit->value && $branches->isNotEmpty();
        $branch = $labVisit ? ($branches->firstWhere('id', $request->integer('branch_id')) ?? $branches->first()) : null;

        // Worked out at most once per request, only when the page or a partial reload needs it.
        $cached = null;
        $pricing = function () use (&$cached, $request, $lines, $area, $labVisit) {
            // Lab visits have no collection fee.
            return $cached ??= $this->pricing($request, $lines->all(), $labVisit ? null : $area);
        };

        return Inertia::render('checkout', [
            'lines' => $lines,
            'totalAmount' => $this->cart->total($lines->all()),
            'patients' => PatientResource::collection($user->patients()->get()),
            'addresses' => AddressResource::collection($addresses),
            'addressId' => $address?->id,
            // Reloaded on its own when the address changes.
            'slots' => fn () => ($schedule = $labVisit ? $branch : $area) ? $this->slots->days($schedule) : [],
            'collectionType' => $labVisit ? CollectionType::LabVisit->value : CollectionType::Home->value,
            'branches' => BranchResource::collection($branches),
            'branchId' => $branch?->id,
            'prescriptionRequired' => $lines->contains('requires_prescription', true),
            // Price breakdown (fee, coupon, tax), reloaded as patients, address and coupon change.
            'quote' => fn () => $pricing()['quote'],
            'coupon' => fn () => $pricing()['coupon'],
            'onlinePayment' => $this->payments->onlineAvailable(),
            'paymentHoldMinutes' => $settings->paymentHoldMinutes(),
        ])->toResponse($request);
    }

    /**
     * The checkout summary's numbers, from OrderPricingService. Patient counts per cart item come from
     * the page (`counts`); on first load each item is for the customer when their profile is complete.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{quote: array<string, mixed>, coupon: array<string, mixed>|null}
     */
    private function pricing(Request $request, array $lines, ?ServiceArea $area): array
    {
        $user = $request->user();
        $counts = $request->input('counts');
        $selfComplete = (bool) $user->patients()->get()->first(fn ($patient) => $patient->isSelf())?->isComplete();
        $patientCount = $user->patients()->count();
        $subtotal = collect($lines)->sum(fn (array $line) => $line['price_amount'] * (is_array($counts)
            ? min(max((int) ($counts[$line['id']] ?? 0), 0), $patientCount)
            : (int) $selfComplete));

        $code = trim((string) $request->input('coupon'));
        $coupon = $code !== '' ? $this->coupons->find($code) : null;
        $problem = $code === '' ? null : ($coupon ? $this->coupons->problem($coupon, $user, $subtotal) : 'We don\'t recognise this code.');

        return [
            'quote' => [
                ...$this->orderPricing->quote($subtotal, $area, $problem ? null : $coupon),
                'minimum' => $this->settings->minimumOrderAmount(),
                'free_collection_above' => $this->settings->freeCollectionAbove(),
            ],
            'coupon' => $code === '' ? null : [
                'code' => Coupon::normalise($code),
                'description' => $problem ? null : $coupon?->description,
                'error' => $problem,
            ],
        ];
    }

    public function store(PlaceOrderRequest $request): Response
    {
        $cart = $this->cart->currentOrCreate($request);
        $place = $request->validated('collection_type') === CollectionType::LabVisit->value
            ? Branch::findOrFail($request->validated('branch_id'))
            : $request->user()->addresses()->findOrFail($request->validated('address_id'));

        $order = $this->orders->place(
            $request->user(),
            $cart,
            $request->patientIdsByCartItem(),
            $place,
            $request->slotStartsAt(),
            $request->file('prescription'),
            $request->paymentMethod(),
            $request->validated('coupon_code'),
        );

        if (! $request->paymentMethod()->isOnline()) {
            return to_route('orders.show', $order)->with('status', 'Thank you! Your order is confirmed.');
        }

        try {
            return Inertia::location($this->payments->start($order));
        } catch (PaymentGatewayException) {
            return to_route('orders.show', $order)
                ->withErrors(['payment' => 'We couldn\'t open the payment page. Please try again.']);
        }
    }
}
