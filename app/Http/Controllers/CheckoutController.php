<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\AddressResource;
use App\Http\Resources\PatientResource;
use App\Models\Address;
use App\Services\CartService;
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

        return Inertia::render('checkout', [
            'lines' => $lines,
            'totalAmount' => $this->cart->total($lines->all()),
            'patients' => PatientResource::collection($user->patients()->get()),
            'addresses' => AddressResource::collection($addresses),
            'addressId' => $address?->id,
            // Reloaded on its own when the address changes.
            'slots' => fn () => $area ? $this->slots->days($area) : [],
            'prescriptionRequired' => $lines->contains('requires_prescription', true),
            'onlinePayment' => $this->payments->onlineAvailable(),
            'paymentHoldMinutes' => $settings->paymentHoldMinutes(),
        ])->toResponse($request);
    }

    public function store(PlaceOrderRequest $request): Response
    {
        $cart = $this->cart->currentOrCreate($request);
        $address = $request->user()->addresses()->findOrFail($request->validated('address_id'));

        $order = $this->orders->place(
            $request->user(),
            $cart,
            $request->patientIdsByCartItem(),
            $address,
            $request->slotStartsAt(),
            $request->file('prescription'),
            $request->paymentMethod(),
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
