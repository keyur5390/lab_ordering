<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Settings\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A customer's reports across all their orders, newest first, grouped by patient (client, 2026-10-31).
 * Reports are kept indefinitely.
 */
class ReportHistoryController extends Controller
{
    public function index(Request $request, Settings $settings): Response
    {
        $timezone = $settings->timezone();
        $patient = $request->validate(['patient' => ['nullable', 'string', 'max:255']])['patient'] ?? null;

        $reports = OrderItem::query()
            ->whereNotNull('report_path')
            ->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('order:id,number')
            ->latest('report_uploaded_at')
            ->get();

        return Inertia::render('reports/index', [
            // Patients as booked; a deleted profile keeps its reports.
            'patients' => $reports->pluck('patient_name')->unique()->sort()->values(),
            'patient' => $patient,
            'reports' => $reports
                ->when($patient, fn ($items) => $items->where('patient_name', $patient))
                ->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'patient_name' => $item->patient_name,
                    'name' => $item->name,
                    'test_names' => $item->test_names,
                    'order_number' => $item->order->number,
                    'uploaded_at' => $item->report_uploaded_at->setTimezone($timezone)->toDateString(),
                    'url' => route('orders.reports.show', [$item->order, $item]),
                ])->values(),
        ]);
    }
}
