<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Lab work on an order (PROJECT.md §8, §9): sample collected → received at lab → reports uploaded.
 * Done by lab staff and admins (client, 2026-10-29). Reports are one PDF per booked item per patient.
 */
class LabService
{
    /** Private disk for reports (CONVENTIONS: health data is never public). */
    public const REPORT_DISK = 'local';

    public function __construct(private OrderNotifier $notifier) {}

    public function canMarkCollected(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Confirmed, OrderStatus::CollectorAssigned], true);
    }

    public function canMarkReceived(Order $order): bool
    {
        return $order->status === OrderStatus::SampleCollected;
    }

    /** Reports can be uploaded once the sample is at the lab, and replaced afterwards (also once completed). */
    public function canUploadReports(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::ReceivedAtLab, OrderStatus::ReportReady, OrderStatus::Completed], true);
    }

    /**
     * An order is completed once all its reports are in and it is paid (client, 2026-10-31). Called
     * when the last report arrives and when a pay-at-collection payment is recorded.
     */
    public function completeIfDone(Order $order): void
    {
        $paid = $order->payment_method === PaymentMethod::Online
            ? $order->payments()->where('status', PaymentStatus::Paid)->exists()
            : $order->payment_collected_at !== null;

        if ($order->status === OrderStatus::ReportReady && $paid) {
            $order->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function markCollected(Order $order): void
    {
        $this->transition($order, fn (Order $order) => $this->canMarkCollected($order), [
            'status' => OrderStatus::SampleCollected,
            'collected_at' => now(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function markReceived(Order $order): void
    {
        $this->transition($order, fn (Order $order) => $this->canMarkReceived($order), [
            'status' => OrderStatus::ReceivedAtLab,
            'received_at_lab_at' => now(),
        ]);
    }

    /**
     * Stores (or replaces) the report for one booked item. When every item has a report, the order
     * becomes "report ready" and the customer is told once.
     *
     * @throws ValidationException
     */
    public function uploadReport(OrderItem $item, UploadedFile $file, User $by): void
    {
        $ready = DB::transaction(function () use ($item, $file, $by) {
            // Locked so two uploads finishing together mark the order ready only once.
            $order = Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();
            if (! $this->canUploadReports($order)) {
                throw ValidationException::withMessages(['report' => 'Reports can be uploaded once the sample is at the lab.']);
            }

            $previous = $item->report_path;
            $item->update([
                'report_path' => $file->store("reports/{$order->id}", self::REPORT_DISK),
                'report_name' => $file->getClientOriginalName(),
                'report_uploaded_at' => now(),
                'report_uploaded_by' => $by->id,
            ]);
            if ($previous) {
                Storage::disk(self::REPORT_DISK)->delete($previous);
            }

            if ($order->status !== OrderStatus::ReceivedAtLab || $order->items()->whereNull('report_path')->exists()) {
                return null;
            }

            $order->update(['status' => OrderStatus::ReportReady, 'report_ready_at' => now()]);

            return $order;
        });

        if ($ready) {
            $this->notifier->reportReady($ready);
            $this->completeIfDone($ready);
        }
    }

    /**
     * @param  callable(Order): bool  $allowed
     * @param  array<string, mixed>  $changes
     *
     * @throws ValidationException
     */
    private function transition(Order $order, callable $allowed, array $changes): void
    {
        DB::transaction(function () use ($order, $allowed, $changes) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $allowed($locked)) {
                throw ValidationException::withMessages(['status' => "This order is {$locked->status->label()}, so it can't move to that step."]);
            }

            $locked->update($changes);
            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
