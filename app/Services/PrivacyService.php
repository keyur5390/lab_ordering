<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PhoneCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Privacy and data (B-27, client: DPDP and GDPR): a customer can download everything we hold about
 * them and delete their account. Deleting anonymises: orders, invoices and reports stay for the
 * lab's and the tax records, without the customer's name, contact details or addresses.
 */
class PrivacyService
{
    /** Orders in these states are over; any other order blocks deleting the account. */
    private const FINISHED = [OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Refunded];

    public const REMOVED = 'Removed';

    /**
     * Why the account can't be deleted yet, or null when it can.
     * TODO(client): confirm blocking deletion while an order is in progress, and that staff accounts
     * are disabled by an admin instead.
     */
    public function cannotDeleteReason(User $user): ?string
    {
        if ($user->anonymised_at) {
            return __('This account has already been deleted.');
        }
        if (AuditLogger::isStaff($user)) {
            return __('Staff accounts are closed by an admin. Please ask an admin to disable yours.');
        }
        if ($user->orders()->whereNotIn('status', self::FINISHED)->exists()) {
            return __('You have an order in progress. You can delete your account once it is completed or cancelled.');
        }

        return null;
    }

    /**
     * Everything we hold about the customer, as one document.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $orders = $user->orders()->with(['items', 'payments.refunds', 'invoice.creditNotes'])->get();

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'phone' => $user->phone,
                'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'privacy_accepted_at' => $user->privacy_accepted_at?->toIso8601String(),
                'text_messages' => ! $user->sms_opt_out,
                'whatsapp_messages' => $user->whatsapp_opt_in,
                'language' => $user->locale,
            ],
            'patients' => $user->patients->map(fn (Patient $patient) => $patient->only([
                'name', 'relationship', 'date_of_birth', 'sex', 'phone', 'email',
            ]))->all(),
            'addresses' => $user->addresses->map(fn (Address $address) => $address->only([
                'label', 'line1', 'line2', 'city', 'region', 'postcode', 'country', 'latitude', 'longitude',
            ]))->all(),
            'orders' => $orders->map(fn (Order $order) => [
                'number' => $order->number,
                'status' => $order->status->label(),
                'placed_at' => $order->created_at?->toIso8601String(),
                'collection' => $order->collection_type?->value,
                'slot_starts_at' => $order->slot_starts_at?->toIso8601String(),
                'address' => $order->address,
                'currency' => $order->currency,
                'total' => $order->total_amount,
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'name' => $item->name,
                    'patient' => $item->patient_name,
                    'patient_date_of_birth' => $item->patient_date_of_birth?->toDateString(),
                    'patient_sex' => $item->patient_sex,
                    'price' => $item->price_amount,
                    'report' => $item->report_name,
                ])->all(),
                'payments' => $order->payments->map(fn (Payment $payment) => [
                    'method' => $payment->gateway,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'refunds' => $payment->refunds->map->only(['status', 'amount', 'reason', 'created_at'])->all(),
                ])->all(),
                'invoice' => $order->invoice?->number,
            ])->all(),
            'messages' => NotificationLog::query()->whereIn('order_id', $orders->modelKeys())->orderBy('id')->get()
                ->map->only(['event', 'channel', 'recipient', 'status', 'created_at'])->all(),
        ];
    }

    /**
     * Deletes the customer's personal data and closes the account. Lab results stay with their
     * orders, which keep the patient's name replaced and the year of birth (reference ranges
     * depend on age). TODO(client): confirm invoices may lose the buyer's name under the tax rules
     * that apply, and that report PDFs (which name the patient) are kept as medical records.
     */
    public function anonymise(User $user): void
    {
        $prescriptions = [];

        DB::transaction(function () use ($user, &$prescriptions) {
            $orders = $user->orders()->get();
            $orderIds = $orders->modelKeys();

            foreach ($orders as $order) {
                if ($order->prescription_path) {
                    $prescriptions[] = $order->prescription_path;
                }
                $order->forceFill([
                    'address' => $this->anonymousAddress($order->address),
                    'prescription_path' => null,
                    'prescription_name' => null,
                    'visit_failure_note' => null,
                ])->save();
            }

            OrderItem::query()->whereIn('order_id', $orderIds)->get()->each(function (OrderItem $item) {
                $item->forceFill([
                    'patient_name' => self::REMOVED,
                    'patient_date_of_birth' => $item->patient_date_of_birth?->startOfYear(),
                ])->save();
            });

            Invoice::query()->whereIn('order_id', $orderIds)->get()->each(function (Invoice $invoice) {
                $invoice->forceFill([
                    'buyer' => ['name' => self::REMOVED, 'email' => null, 'address' => $this->anonymousAddress($invoice->buyer['address'] ?? [])],
                    'lines' => collect($invoice->lines)->map(fn (array $line) => [...$line, 'patient' => self::REMOVED])->all(),
                ])->save();
            });

            NotificationLog::query()->whereIn('order_id', $orderIds)->update(['recipient' => null]);

            // Plain queries: the relations are ordered, which a DELETE can't carry on every database.
            Patient::query()->where('user_id', $user->id)->delete();
            Address::query()->where('user_id', $user->id)->delete();
            Cart::query()->where('user_id', $user->id)->delete();
            PhoneCode::query()->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->forceFill([
                'name' => 'Deleted customer',
                'email' => "deleted-{$user->id}@deleted.invalid",
                'email_verified_at' => null,
                'phone' => null,
                'phone_verified_at' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'sms_opt_out' => true,
                'whatsapp_opt_in' => false,
                'locale' => null,
                'disabled_at' => now(),
                'anonymised_at' => now(),
            ])->save();
        });

        // Files go only once the database change is safe.
        foreach ($prescriptions as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Keeps only the area (city, region, country), which reports and statistics may still need.
     *
     * @param  array<string, mixed>|null  $address
     * @return array<string, mixed>
     */
    private function anonymousAddress(?array $address): array
    {
        return [
            'label' => null,
            'line1' => self::REMOVED,
            'line2' => null,
            'city' => $address['city'] ?? '',
            'region' => $address['region'] ?? null,
            'postcode' => '',
            'country' => $address['country'] ?? '',
        ];
    }
}
