<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An order's invoice, issued when it is paid (client, 2026-10-31). Written once by
 * App\Services\InvoiceService and never changed; refunds get credit notes.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $payment_id
 * @property string $number
 * @property Carbon $issued_at
 * @property string $currency
 * @property array<string, mixed> $seller
 * @property array<string, mixed> $buyer
 * @property list<array<string, mixed>> $lines
 * @property array<string, mixed> $totals
 * @property string $payment_note
 */
class Invoice extends Model
{
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
            'issued_at' => 'datetime',
            'seller' => 'array',
            'buyer' => 'array',
            'lines' => 'array',
            'totals' => 'array',
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
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('id');
    }
}
