<?php

namespace App\Models;

use App\Enums\CollectionPaymentMethod;
use App\Enums\CollectionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\VisitFailureReason;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A booked home collection. Placed and changed through App\Services\OrderService.
 *
 * @property int $id
 * @property string|null $number
 * @property int $user_id
 * @property OrderStatus $status
 * @property PaymentMethod $payment_method
 * @property Carbon|null $payment_expires_at UTC; while pending payment, the slot is held until then
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $collected_at
 * @property Carbon|null $received_at_lab_at
 * @property Carbon|null $report_ready_at
 * @property int|null $collector_id
 * @property CollectionType $collection_type
 * @property int|null $branch_id
 * @property Carbon|null $collector_assigned_at
 * @property Carbon|null $visit_failed_at
 * @property VisitFailureReason|null $visit_failure_reason
 * @property string|null $visit_failure_note
 * @property Carbon|null $payment_collected_at
 * @property CollectionPaymentMethod|null $payment_collected_method
 * @property int|null $service_area_id
 * @property Carbon $slot_starts_at UTC
 * @property Carbon $slot_ends_at UTC
 * @property int|null $address_id
 * @property array<string, mixed> $address
 * @property string $currency
 * @property int $subtotal_amount
 * @property int $total_amount
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property int $discount_amount
 * @property int $collection_fee_amount
 * @property int|null $tax_rate_basis_points
 * @property bool $tax_inclusive
 * @property int $tax_amount
 * @property string|null $prescription_path
 * @property string|null $prescription_name
 * @property Carbon|null $cancelled_at
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Orders are only written by OrderService, so everything but the id is fillable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'collection_type' => CollectionType::class,
            'payment_expires_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'collected_at' => 'datetime',
            'received_at_lab_at' => 'datetime',
            'report_ready_at' => 'datetime',
            'collector_assigned_at' => 'datetime',
            'visit_failed_at' => 'datetime',
            'visit_failure_reason' => VisitFailureReason::class,
            'payment_collected_at' => 'datetime',
            'payment_collected_method' => CollectionPaymentMethod::class,
            'slot_starts_at' => 'datetime',
            'slot_ends_at' => 'datetime',
            'address' => 'array',
            'subtotal_amount' => 'integer',
            'total_amount' => 'integer',
            'discount_amount' => 'integer',
            'collection_fee_amount' => 'integer',
            'tax_rate_basis_points' => 'integer',
            'tax_inclusive' => 'boolean',
            'tax_amount' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Where the slot comes from: the branch for lab visits, otherwise the service area. */
    public function schedule(): ?Contracts\HasSlotSchedule
    {
        return $this->collection_type === CollectionType::LabVisit ? $this->branch : $this->serviceArea;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /**
     * @return BelongsTo<ServiceArea, $this>
     */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }
}
