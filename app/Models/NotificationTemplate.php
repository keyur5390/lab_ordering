<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-edited wording for one event on one channel. Placeholders like {order_number} are
 * filled in by App\Services\NotificationTemplateService.
 *
 * @property int $id
 * @property NotificationEvent $event
 * @property NotificationChannel $channel
 * @property string|null $subject
 * @property string $body
 * @property bool $is_active
 */
class NotificationTemplate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['subject', 'body', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'channel' => NotificationChannel::class,
            'is_active' => 'boolean',
        ];
    }
}
