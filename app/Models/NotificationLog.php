<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One order email or SMS, sent or failed (B-24). Written by App\Services\NotificationLogger.
 *
 * @property int $id
 * @property int|null $order_id
 * @property NotificationEvent $event
 * @property NotificationChannel $channel
 * @property string|null $recipient
 * @property string $status sent | failed
 * @property string|null $error
 * @property int|null $amount
 */
class NotificationLog extends Model
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

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
            'event' => NotificationEvent::class,
            'channel' => NotificationChannel::class,
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
