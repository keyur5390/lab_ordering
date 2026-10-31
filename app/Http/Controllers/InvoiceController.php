<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Invoice and credit note PDFs, for whoever may see the order (its customer, lab staff, admins).
 */
class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function invoice(Order $order): Response
    {
        Gate::authorize('view', $order);
        $invoice = $order->invoice ?? abort(404);

        return $this->invoices->invoicePdf($invoice);
    }

    public function creditNote(Order $order, CreditNote $creditNote): Response
    {
        Gate::authorize('view', $order);
        abort_unless($creditNote->invoice->order_id === $order->id, 404);

        return $this->invoices->creditNotePdf($creditNote);
    }
}
