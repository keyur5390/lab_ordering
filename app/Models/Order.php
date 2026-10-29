<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
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
 * @property int|null $service_area_id
 * @property Carbon $slot_starts_at UTC
 * @property Carbon $slot_ends_at UTC
 * @property int|null $address_id
 * @property array<string, mixed> $address
 * @property string $currency
 * @property int $subtotal_amount
 * @property int $total_amount
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
            'payment_expires_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'slot_starts_at' => 'datetime',
            'slot_ends_at' => 'datetime',
            'address' => 'array',
            'subtotal_amount' => 'integer',
            'total_amount' => 'integer',
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
