<?php

namespace App\Models;

use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * A Twilio WhatsApp Content Template for one customer event (B-31).
 *
 * @property NotificationEvent $event
 * @property string $content_sid
 * @property list<string> $variables
 * @property bool $is_active
 */
class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = ['event', 'content_sid', 'variables', 'is_active'];

    protected function casts(): array
    {
        return ['event' => NotificationEvent::class, 'variables' => 'array', 'is_active' => 'boolean'];
    }
}
