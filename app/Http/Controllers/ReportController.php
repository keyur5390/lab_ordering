<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\LabService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports are health data (PROJECT.md §9): only the order's customer, lab staff and admins can
 * download them, through this authorised route.
 */
class ReportController extends Controller
{
    public function show(Order $order, OrderItem $item): StreamedResponse
    {
        Gate::authorize('view', $order);
        abort_unless($item->report_path, 404);

        return Storage::disk(LabService::REPORT_DISK)->download(
            $item->report_path,
            "{$item->patient_name} - {$item->name}.pdf",
        );
    }
}
