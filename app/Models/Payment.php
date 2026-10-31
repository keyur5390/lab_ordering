<?php

namespace App\Models;

use App\Enums\PaymentGatewayName;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One attempt to pay an order online. Written only by App\Services\PaymentService.
 *
 * @property int $id
 * @property int $order_id
 * @property PaymentGatewayName $gateway
 * @property string|null $reference
 * @property string|null $gateway_payment_id
 * @property PaymentStatus $status
 * @property string $currency
 * @property int $amount
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGatewayName::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('id');
    }
}
