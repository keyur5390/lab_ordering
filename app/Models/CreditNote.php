<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money refunded against an invoice (client, 2026-10-31: one credit note per refund).
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $refund_id
 * @property string $number
 * @property Carbon $issued_at
 * @property int $amount
 * @property string|null $reason
 */
class CreditNote extends Model
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
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
