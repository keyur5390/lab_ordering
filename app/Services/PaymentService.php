<?php

namespace App\Services;

use App\Enums\CollectionPaymentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentGatewayName;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\InvalidWebhookSignature;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\RazorpayGateway;
use App\Services\Payments\StripeGateway;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Online payments and refunds (PROJECT.md §7). An online order waits in pending_payment, holding
 * its slot for the admin-set hold time, until the gateway reports it paid (on return or by webhook).
 */
class PaymentService
{
    public function __construct(
        private Settings $settings,
        private SlotService $slots,
        private OrderNotifier $notifier,
        private InvoiceService $invoices,
        private LabService $lab,
    ) {}

    /** Whether customers can choose to pay online: a gateway is chosen and its keys are set. */
    public function onlineAvailable(): bool
    {
        return $this->settings->paymentGateway() !== null;
    }

    public function gateway(PaymentGatewayName $name): PaymentGateway
    {
        return match ($name) {
            PaymentGatewayName::Stripe => app(StripeGateway::class),
            PaymentGatewayName::Razorpay => app(RazorpayGateway::class),
        };
    }

    /** Whether the order can be paid now: online, unpaid and still within its hold. */
    public function canPay(Order $order, ?CarbonImmutable $now = null): bool
    {
        return $order->status === OrderStatus::PendingPayment
            && $order->payment_method === PaymentMethod::Online
            && $order->payment_expires_at?->isAfter($now ?? CarbonImmutable::now())
            && $this->onlineAvailable();
    }

    /**
     * Creates a hosted checkout for the order and returns the URL to send the customer to.
     *
     * @throws PaymentGatewayException
     */
    public function start(Order $order): string
    {
        $name = $this->settings->paymentGateway() ?? throw new PaymentGatewayException('Online payment is not set up.');

        $payment = $order->payments()->create([
            'gateway' => $name,
            'status' => PaymentStatus::Pending,
            'currency' => $order->currency,
            'amount' => $order->total_amount,
        ]);

        try {
            $session = $this->gateway($name)->createCheckout(
                $payment,
                $order,
                route('orders.payments.return', [$order, $payment]),
                route('orders.show', $order),
            );
        } catch (PaymentGatewayException $e) {
            $payment->update(['status' => PaymentStatus::Failed]);

            throw $e;
        }

        $payment->update(['reference' => $session->reference]);

        return $session->url;
    }

    /**
     * The customer is back from the gateway. Returns whether the payment is confirmed.
     */
    public function handleReturn(Payment $payment, Request $request): bool
    {
        if ($payment->status === PaymentStatus::Paid) {
            return true;
        }

        try {
            $gatewayPaymentId = $this->gateway($payment->gateway)->paidOnReturn($payment, $request);
        } catch (PaymentGatewayException) {
            // The webhook will still confirm it.
            return false;
        }

        if ($gatewayPaymentId === null) {
            return false;
        }

        $this->confirm($payment, $gatewayPaymentId);

        return true;
    }

    /**
     * @throws InvalidWebhookSignature
     */
    public function handleWebhook(PaymentGatewayName $name, Request $request): void
    {
        $paid = $this->gateway($name)->paidFromWebhook($request);
        if (! $paid) {
            return;
        }

        $payment = Payment::where('gateway', $name)->where('reference', $paid->reference)->first();
        if (! $payment) {
            Log::warning('Payment webhook for an unknown checkout', ['gateway' => $name->value, 'reference' => $paid->reference]);

            return;
        }

        $this->confirm($payment, $paid->gatewayPaymentId);
    }

    /**
     * Records a payment as paid and confirms its order. Safe to call more than once (return and
     * webhook both call it). Money that can't be used is refunded in full: a second payment for the
     * same order, or a payment after the order was cancelled or its hold expired and the slot was taken.
     */
    public function confirm(Payment $payment, string $gatewayPaymentId): void
    {
        $refund = DB::transaction(function () use ($payment, $gatewayPaymentId) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Paid) {
                return null;
            }

            $payment->update(['status' => PaymentStatus::Paid, 'gateway_payment_id' => $gatewayPaymentId, 'paid_at' => now()]);

            if ($order->status !== OrderStatus::PendingPayment) {
                return [$payment, $order->status === OrderStatus::Confirmed
                    ? 'Paid twice for the same order'
                    : 'Paid after the order was cancelled'];
            }

            if ($order->payment_expires_at?->isFuture() || $this->slotStillFree($order)) {
                $order->update(['status' => OrderStatus::Confirmed, 'payment_expires_at' => null]);
                // Paid, so invoiced (client, 2026-10-31).
                $this->invoices->issue($order, $payment);
                // Sent once the transaction commits.
                $this->notifier->paymentReceived($order, $payment->amount);

                return null;
            }

            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

            return [$payment, 'Paid after the hold expired and the slot was taken'];
        });

        if ($refund) {
            [$payment, $reason] = $refund;
            $this->refund($payment->order, $payment->amount, null, $reason, $payment);
        }
    }

    /**
     * Cancels unpaid online orders whose hold has passed, freeing their slots. Returns how many.
     */
    public function expireUnpaid(?CarbonImmutable $now = null): int
    {
        $expired = Order::with('user')
            ->where('status', OrderStatus::PendingPayment)
            ->where('payment_expires_at', '<=', ($now ?? CarbonImmutable::now())->utc())
            ->get();

        $count = 0;
        foreach ($expired as $order) {
            // Only if a payment hasn't confirmed it in the meantime.
            $updated = Order::whereKey($order->id)->where('status', OrderStatus::PendingPayment)
                ->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now(), 'updated_at' => now()]);
            if ($updated) {
                $count++;
                $this->notifier->paymentExpired($order->refresh());
            }
        }

        return $count;
    }

    /**
     * Paid, refunded (pending or succeeded refunds) and still refundable amounts for an order.
     *
     * @return array{paid: int, refunded: int, refundable: int}
     */
    public function amounts(Order $order): array
    {
        $payments = $order->relationLoaded('payments')
            ? $order->payments->loadMissing('refunds')
            : $order->payments()->with('refunds')->get();
        $paid = $payments->where('status', PaymentStatus::Paid);

        $paidAmount = (int) $paid->sum('amount');
        $refunded = (int) $paid->flatMap->refunds->filter(fn ($refund) => $refund->status->counts())->sum('amount');

        return ['paid' => $paidAmount, 'refunded' => $refunded, 'refundable' => $paidAmount - $refunded];
    }

    /**
     * Refunds up to the amount still refundable, through each paid payment's gateway. A refund the
     * gateway rejects is recorded as failed so an admin can try again. Returns false if any part failed.
     * A cancelled order with nothing left to refund becomes "refunded".
     *
     * @param  Payment|null  $from  refund only this payment (e.g. a duplicate)
     *
     * @throws ValidationException when the amount is not between 1 and what can still be refunded
     */
    public function refund(Order $order, int $amount, ?User $by = null, ?string $reason = null, ?Payment $from = null): bool
    {
        return DB::transaction(function () use ($order, $amount, $by, $reason, $from) {
            // Locked so two refunds can't both take the same money.
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $refundable = $this->amounts($order)['refundable'];

            if ($amount < 1 || $amount > $refundable) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount up to what can still be refunded.']);
            }

            $succeeded = true;
            $payments = $order->payments()->with('refunds')->where('status', PaymentStatus::Paid)
                ->when($from, fn ($query) => $query->whereKey($from->id))
                ->get();
            foreach ($payments as $payment) {
                $left = $payment->amount - $payment->refunds->filter(fn ($refund) => $refund->status->counts())->sum('amount');
                $part = min($left, $amount);
                if ($part <= 0) {
                    continue;
                }

                try {
                    $result = $this->gateway($payment->gateway)->refund($payment, $part);
                    $status = $result->status;
                    $refundId = $result->id;
                } catch (PaymentGatewayException) {
                    $status = RefundStatus::Failed;
                    $refundId = null;
                }

                $refund = $payment->refunds()->create([
                    'user_id' => $by?->id,
                    'status' => $status,
                    'amount' => $part,
                    'reason' => $reason,
                    'gateway_refund_id' => $refundId,
                ]);
                $this->notifier->refunded($order, $refund);
                $this->invoices->creditNoteFor($refund);

                $succeeded = $succeeded && $status->counts();
                $amount -= $part;
                if ($amount === 0) {
                    break;
                }
            }

            if ($order->status === OrderStatus::Cancelled && $this->amounts($order->unsetRelation('payments'))['refundable'] === 0) {
                $order->update(['status' => OrderStatus::Refunded]);
            }

            return $succeeded;
        });
    }

    /**
     * Pay-at-collection orders: the collector (or, for lab visits, staff) took the full amount. The
     * order is invoiced then (client, 2026-10-31).
     *
     * @throws ValidationException
     */
    public function recordCollectedPayment(Order $order, CollectionPaymentMethod $method): void
    {
        if ($order->payment_method !== PaymentMethod::PayAtCollection || $order->payment_collected_at !== null
            || ! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::CollectorAssigned, OrderStatus::SampleCollected, OrderStatus::ReceivedAtLab, OrderStatus::ReportReady], true)) {
            throw ValidationException::withMessages(['method' => 'There is no payment to collect for this order.']);
        }

        $order->update(['payment_collected_at' => now(), 'payment_collected_method' => $method]);
        $this->invoices->issue($order);
        $this->lab->completeIfDone($order);
    }

    /** Refunds everything still refundable. Returns null when nothing was paid online. */
    public function refundRemaining(Order $order, ?User $by = null, ?string $reason = null): ?bool
    {
        $refundable = $this->amounts($order)['refundable'];

        return $refundable > 0 ? $this->refund($order, $refundable, $by, $reason) : null;
    }

    /** For a late payment: whether the order's slot can still be booked. */
    private function slotStillFree(Order $order): bool
    {
        $area = $order->schedule();
        if (! $area) {
            return false;
        }

        $area->newQuery()->whereKey($area->id)->lockForUpdate()->first();

        return $this->slots->isBookable($area, CarbonImmutable::parse($order->slot_starts_at));
    }
}
