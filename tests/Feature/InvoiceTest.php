<?php

use App\Enums\CollectionPaymentMethod;
use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test', 'services.stripe.webhook_secret' => 'whsec']);
    $this->settings = app(Settings::class);
    $this->settings->setCurrency(Currency::INR);
    $this->settings->setTimezone('Asia/Kolkata');
    $this->settings->setInvoiceSeller(['name' => 'Acme Labs Pvt Ltd', 'address' => "12 MG Road\nPune 411001", 'tax_id' => '27ABCDE1234F1Z5', 'footer' => 'Thank you.']);
    $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00', 'Asia/Kolkata'));

    $this->customer = User::factory()->create(['name' => 'Asha Rao', 'email' => 'asha@example.com']);
    $this->order = Order::factory()->for($this->customer)->create([
        'status' => OrderStatus::PendingPayment, 'payment_method' => PaymentMethod::Online, 'payment_expires_at' => now()->addMinutes(15),
        'subtotal_amount' => 100000, 'coupon_code' => 'SAVE10', 'discount_amount' => 10000, 'collection_fee_amount' => 5000,
        'tax_rate_basis_points' => 1800, 'tax_inclusive' => false, 'tax_amount' => 17100, 'total_amount' => 112100,
    ]);
    $this->order->items()->create([
        'patient_name' => 'Asha Rao', 'patient_date_of_birth' => '1990-01-01', 'patient_sex' => 'female',
        'item_type' => 'test', 'name' => 'Complete Blood Count', 'code' => 'CBC', 'price_amount' => 100000,
    ]);
    $this->payment = Payment::factory()->for($this->order)->create(['amount' => 112100]);
});

function payOnlineOrder(): Invoice
{
    app(PaymentService::class)->confirm(test()->payment, 'pi_1');

    return test()->order->fresh()->invoice;
}

describe('issuing', function () {
    it('invoices an online order when it is paid, keeping everything it shows', function () {
        expect($this->order->invoice)->toBeNull();

        $invoice = payOnlineOrder();

        expect($invoice->number)->toBe('INV-2026-00001')
            ->and($invoice->payment_id)->toBe($this->payment->id)
            ->and($invoice->seller['tax_id'])->toBe('27ABCDE1234F1Z5')
            ->and($invoice->buyer['email'])->toBe('asha@example.com')
            // toEqual: MySQL's JSON columns don't keep key order.
            ->and($invoice->lines)->toEqual([['description' => 'Complete Blood Count', 'patient' => 'Asha Rao', 'amount' => 100000]])
            ->and($invoice->totals['total'])->toBe(112100)
            ->and($invoice->payment_note)->toBe('Paid online');

        // Later changes don't touch an issued invoice.
        $this->settings->setInvoiceSeller(['name' => 'New Name']);
        expect($invoice->fresh()->seller['name'])->toBe('Acme Labs Pvt Ltd');
    });

    it('issues one invoice per order however often it is asked', function () {
        payOnlineOrder();
        app(InvoiceService::class)->issue($this->order);
        app(PaymentService::class)->confirm($this->payment, 'pi_1');

        expect(Invoice::count())->toBe(1);
    });

    it('numbers invoices per calendar year in the site timezone', function () {
        $issue = fn () => app(InvoiceService::class)->issue(Order::factory()->create())->number;

        expect($issue())->toBe('INV-2026-00001')->and($issue())->toBe('INV-2026-00002');

        // 31 Dec 2026, 23:30 UTC is already 2027 in Kolkata.
        $this->travelTo(CarbonImmutable::parse('2026-12-31 23:30', 'UTC'));
        expect($issue())->toBe('INV-2027-00001');
    });

    it('invoices pay-at-collection orders when the payment is recorded', function () {
        $order = Order::factory()->for($this->customer)->create();
        expect($order->invoice)->toBeNull();

        $this->actingAs(User::factory()->create(['role' => UserRole::LabStaff]))
            ->post(route('admin.orders.payment.store', $order), ['method' => 'card'])
            ->assertRedirect(route('admin.orders.show', $order));

        expect($order->fresh()->payment_collected_method)->toBe(CollectionPaymentMethod::Card)
            ->and($order->fresh()->invoice->payment_note)->toBe('Paid at collection (Card)');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.payment.store', $order), ['method' => 'cash'])->assertSessionHasErrors('method');
        $this->actingAs($this->customer)->post(route('admin.orders.payment.store', $order), ['method' => 'cash'])->assertForbidden();
    });

    it('has no payment to record for online orders', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.orders.payment.store', $this->order), ['method' => 'cash'])->assertSessionHasErrors('method');
    });
});

describe('credit notes', function () {
    it('issues a credit note per refund of the invoiced payment', function () {
        Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'])]);
        $invoice = payOnlineOrder();

        app(PaymentService::class)->refund($this->order, 20000, null, 'Test not done');
        app(PaymentService::class)->refund($this->order, 5000);

        expect($invoice->creditNotes->pluck('number')->all())->toBe(['CN-2026-00001', 'CN-2026-00002'])
            ->and($invoice->creditNotes->first()->only(['amount', 'reason']))->toBe(['amount' => 20000, 'reason' => 'Test not done'])
            ->and($invoice->fresh()->totals['total'])->toBe(112100);
    });

    it('issues none for failed refunds or refunds of payments never invoiced', function () {
        // The gateway rejects the first refund and accepts the next.
        Http::fake(['api.stripe.com/v1/refunds' => Http::sequence()->push([], 500)->push(['id' => 're_2', 'status' => 'succeeded'])]);
        payOnlineOrder();
        app(PaymentService::class)->refund($this->order, 20000);
        expect(CreditNote::count())->toBe(0);

        // A second, duplicate payment is refunded in full but was never invoiced.
        $duplicate = Payment::factory()->for($this->order)->create(['amount' => 112100, 'reference' => 'cs_dup']);
        app(PaymentService::class)->confirm($duplicate, 'pi_2');
        expect($duplicate->refunds()->sole()->status)->toBe(RefundStatus::Succeeded)->and(CreditNote::count())->toBe(0);
    });
});

describe('downloads', function () {
    it('shows the invoice and credit notes on the order page and serves the PDFs', function () {
        Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'])]);
        $invoice = payOnlineOrder();
        app(PaymentService::class)->refund($this->order, 20000);
        $note = $invoice->creditNotes()->sole();

        $this->actingAs($this->customer)->get(route('orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page->where('order.invoice.number', 'INV-2026-00001')
                ->where('order.invoice.url', route('orders.invoice', $this->order))
                ->where('order.invoice.credit_notes.0.amount', 20000));

        $pdf = $this->actingAs($this->customer)->get(route('orders.invoice', $this->order));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf')->assertDownload('INV-2026-00001.pdf');
        expect(substr($pdf->getContent(), 0, 4))->toBe('%PDF');

        $this->actingAs($this->customer)->get(route('orders.credit-notes.show', [$this->order, $note]))->assertOk()->assertDownload('CN-2026-00001.pdf');
        $this->actingAs(User::factory()->admin()->create())->get(route('orders.invoice', $this->order))->assertOk();
    });

    it('prints the breakdown, seller and buyer', function () {
        $invoice = payOnlineOrder();
        $html = view('pdf.invoice', ['invoice' => $invoice, 'timezone' => 'Asia/Kolkata'])->render();

        expect($html)->toContain('INV-2026-00001')->toContain('Acme Labs Pvt Ltd')->toContain('Tax ID: 27ABCDE1234F1Z5')
            ->toContain('Asha Rao')->toContain('₹1,000.00')->toContain('Code SAVE10')->toContain('−₹100.00')
            ->toContain('Home collection')->toContain('Tax (18%)')->toContain('₹171.00')->toContain('₹1,121.00')
            ->toContain('Paid online')->toContain('Thank you.');
    });

    it('keeps invoices private, and 404s when there is none', function () {
        $invoice = payOnlineOrder();
        $other = Order::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('orders.invoice', $this->order))->assertForbidden();
        $this->actingAs($other->user)->get(route('orders.invoice', $other))->assertNotFound();

        Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'])]);
        app(PaymentService::class)->refund($this->order, 1000);
        $note = $invoice->creditNotes()->sole();
        $this->actingAs($other->user)->get(route('orders.credit-notes.show', [$other, $note]))->assertNotFound();
    });
});

it('saves the seller details in settings', function () {
    $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), [
        'currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'invoice_name' => 'Lab Co', 'invoice_address' => '1 Main St',
        'invoice_tax_id' => 'GST123', 'invoice_footer' => '',
    ])->assertSessionHasNoErrors();

    expect($this->settings->invoiceSeller())->toBe(['name' => 'Lab Co', 'address' => '1 Main St', 'tax_id' => 'GST123', 'footer' => null]);
});
