<?php

namespace App\Http\Controllers\Lab;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\UploadReportRequest;
use App\Http\Resources\LabOrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\LabService;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The lab's view of orders (client, 2026-10-29): patients and tests only, no address or payment.
 * Lab staff and admins mark samples collected and received, and upload reports.
 */
class OrderController extends Controller
{
    /** Statuses the lab works with, in order. */
    private const STATUSES = [
        OrderStatus::Confirmed,
        OrderStatus::CollectorAssigned,
        OrderStatus::SampleCollected,
        OrderStatus::ReceivedAtLab,
        OrderStatus::ReportReady,
    ];

    public function __construct(private LabService $lab) {}

    public function index(Request $request, Settings $settings): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_map(fn (OrderStatus $status) => $status->value, self::STATUSES))],
            'q' => ['nullable', 'string', 'max:30'],
        ]);
        $timezone = $settings->timezone();

        $orders = Order::query()
            ->whereIn('status', isset($filters['status']) ? [$filters['status']] : self::STATUSES)
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where('number', strtoupper(trim($q))))
            ->withCount(['items', 'items as reports_count' => fn ($query) => $query->whereNotNull('report_path')])
            ->orderBy('slot_starts_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('lab/index', [
            'orders' => LabOrderResource::collection($orders),
            'filters' => ['status' => $filters['status'] ?? null, 'q' => $filters['q'] ?? ''],
            'statuses' => array_map(fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()], self::STATUSES),
            'timezone' => $timezone,
        ]);
    }

    public function show(Order $order, Settings $settings): Response
    {
        abort_unless(in_array($order->status, self::STATUSES, true), 404);
        $timezone = $settings->timezone();
        $local = fn ($time) => $time?->setTimezone($timezone)->toIso8601String();

        return Inertia::render('lab/show', [
            'order' => [
                'number' => $order->number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'slot_starts_at' => $local($order->slot_starts_at),
                'slot_ends_at' => $local($order->slot_ends_at),
                'collected_at' => $local($order->collected_at),
                'received_at_lab_at' => $local($order->received_at_lab_at),
                'report_ready_at' => $local($order->report_ready_at),
                'timezone' => $timezone,
                'has_prescription' => $order->prescription_path !== null,
                'prescription_name' => $order->prescription_name,
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'patient_name' => $item->patient_name,
                    'patient_date_of_birth' => $item->patient_date_of_birth->toDateString(),
                    'patient_sex' => $item->patient_sex,
                    'name' => $item->name,
                    'code' => $item->code,
                    'test_names' => $item->test_names,
                    'report_name' => $item->report_name,
                    'report_uploaded_at' => $local($item->report_uploaded_at),
                ]),
                'can_mark_collected' => $this->lab->canMarkCollected($order),
                'can_mark_received' => $this->lab->canMarkReceived($order),
                'can_upload_reports' => $this->lab->canUploadReports($order),
            ],
        ]);
    }

    public function collect(Order $order): RedirectResponse
    {
        $this->lab->markCollected($order);

        return to_route('admin.lab.show', $order)->with('status', 'Marked as sample collected.');
    }

    public function receive(Order $order): RedirectResponse
    {
        $this->lab->markReceived($order);

        return to_route('admin.lab.show', $order)->with('status', 'Marked as received at the lab.');
    }

    public function uploadReport(UploadReportRequest $request, Order $order, OrderItem $item): RedirectResponse
    {
        $this->lab->uploadReport($item, $request->file('report'), $request->user());

        return to_route('admin.lab.show', $order)->with('status', $order->fresh()->status === OrderStatus::ReportReady
            ? 'Report uploaded. All reports are in, so the customer has been told.'
            : 'Report uploaded.');
    }
}
