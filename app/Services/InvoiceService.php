<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Settings\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Invoices and credit notes (client, 2026-10-31). An invoice is issued once, when the order is paid,
 * numbered INV-{year}-{n} per calendar year; it keeps everything it shows and never changes. Each
 * refund of the invoiced payment gets a credit note, CN-{year}-{n}. PDFs are drawn on demand from
 * what was kept.
 */
class InvoiceService
{
    public function __construct(private Settings $settings) {}

    /** Issues the order's invoice if it has none yet. Safe to call more than once. */
    public function issue(Order $order, ?Payment $payment = null): Invoice
    {
        return DB::transaction(function () use ($order, $payment) {
            // Locked so two paths (return and webhook) can't both issue one.
            $order = Order::whereKey($order->id)->lockForUpdate()->with(['items', 'user'])->firstOrFail();
            if ($existing = $order->invoice()->first()) {
                return $existing;
            }

            $issuedAt = CarbonImmutable::now();

            return $order->invoice()->create([
                'payment_id' => $payment?->id,
                'number' => $this->nextNumber('invoice', 'INV', $issuedAt),
                'issued_at' => $issuedAt,
                'currency' => $order->currency,
                'seller' => $this->settings->invoiceSeller(),
                'buyer' => [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'address' => $order->address,
                ],
                'lines' => $order->items->map(fn (OrderItem $item) => [
                    'description' => $item->name,
                    'patient' => $item->patient_name,
                    'amount' => $item->price_amount,
                ])->values()->all(),
                'totals' => [
                    'subtotal' => $order->subtotal_amount,
                    'coupon_code' => $order->coupon_code,
                    'discount' => $order->discount_amount,
                    'collection_fee' => $order->collection_fee_amount,
                    'tax_rate_basis_points' => $order->tax_rate_basis_points,
                    'tax_inclusive' => $order->tax_inclusive,
                    'tax' => $order->tax_amount,
                    'total' => $order->total_amount,
                ],
                'payment_note' => $order->payment_method === PaymentMethod::Online
                    ? 'Paid online'
                    : 'Paid at collection'.($order->payment_collected_method ? " ({$order->payment_collected_method->label()})" : ''),
            ]);
        });
    }

    /**
     * A credit note for a refund of the invoiced payment. Refunds of other payments (a duplicate, or a
     * payment that arrived after cancellation) were never invoiced, so they get none.
     */
    public function creditNoteFor(Refund $refund): ?CreditNote
    {
        if (! $refund->status->counts()) {
            return null;
        }

        return DB::transaction(function () use ($refund) {
            $invoice = Invoice::where('payment_id', $refund->payment_id)->lockForUpdate()->first();
            if (! $invoice || CreditNote::where('refund_id', $refund->id)->exists()) {
                return null;
            }

            $issuedAt = CarbonImmutable::now();

            return $invoice->creditNotes()->create([
                'refund_id' => $refund->id,
                'number' => $this->nextNumber('credit_note', 'CN', $issuedAt),
                'issued_at' => $issuedAt,
                'amount' => $refund->amount,
                'reason' => $refund->reason,
            ]);
        });
    }

    public function invoicePdf(Invoice $invoice): Response
    {
        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'timezone' => $this->settings->timezone(),
        ])->download("{$invoice->number}.pdf");
    }

    public function creditNotePdf(CreditNote $note): Response
    {
        return Pdf::loadView('pdf.credit-note', [
            'note' => $note->loadMissing('invoice'),
            'timezone' => $this->settings->timezone(),
        ])->download("{$note->number}.pdf");
    }

    /**
     * Next number in the series for the issue date's calendar year (site timezone), e.g. INV-2026-00001.
     * The counter row is locked, so numbers are never repeated or skipped.
     */
    private function nextNumber(string $type, string $prefix, CarbonImmutable $issuedAt): string
    {
        $year = (int) $issuedAt->setTimezone($this->settings->timezone())->format('Y');

        DB::table('document_sequences')->insertOrIgnore(['type' => $type, 'year' => $year, 'last_number' => 0]);
        $row = DB::table('document_sequences')->where('type', $type)->where('year', $year)->lockForUpdate()->first();
        $next = $row->last_number + 1;
        DB::table('document_sequences')->where('id', $row->id)->update(['last_number' => $next]);

        return sprintf('%s-%d-%05d', $prefix, $year, $next);
    }
}
