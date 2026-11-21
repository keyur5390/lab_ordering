<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\OrderNotifier;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notification history (B-24): every order email and SMS, with failures and a resend.
 */
class NotificationLogController extends Controller
{
    public function index(Request $request, Settings $settings): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([NotificationLog::SENT, NotificationLog::FAILED])],
            'channel' => ['nullable', Rule::enum(NotificationChannel::class)],
            'event' => ['nullable', Rule::enum(NotificationEvent::class)],
            'order' => ['nullable', 'string', 'max:30'],
        ]);
        $timezone = $settings->timezone();

        return Inertia::render('admin/notifications/history', [
            'logs' => JsonResource::collection(NotificationLog::query()
                ->with('order:id,number')
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['channel'] ?? null, fn ($query, $channel) => $query->where('channel', $channel))
                ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
                ->when($filters['order'] ?? null, fn ($query, $number) => $query->whereHas('order', fn ($q) => $q->where('number', strtoupper(trim($number)))))
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (NotificationLog $log) => self::row($log, $timezone))),
            'filters' => [
                'status' => $filters['status'] ?? null,
                'channel' => $filters['channel'] ?? null,
                'event' => $filters['event'] ?? null,
                'order' => $filters['order'] ?? '',
            ],
            'timezone' => $timezone,
            'events' => array_map(fn (NotificationEvent $event) => ['value' => $event->value, 'label' => $event->label()], NotificationEvent::cases()),
        ]);
    }

    public function resend(NotificationLog $log, OrderNotifier $notifier): RedirectResponse
    {
        abort_unless($log->order && $log->recipient, 404);
        $notifier->resend($log);

        return back()->with('status', "{$log->channel->label()} resent to {$log->recipient}.");
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(NotificationLog $log, string $timezone): array
    {
        return [
            'id' => $log->id,
            'order_number' => $log->order?->number,
            'event' => $log->event->value,
            'event_label' => ($log->event->forAdmins() ? 'Admin: ' : '').$log->event->label(),
            'channel' => $log->channel->value,
            'channel_label' => $log->channel->label(),
            'recipient' => $log->recipient,
            'status' => $log->status,
            'error' => $log->error,
            'at' => $log->created_at->setTimezone($timezone)->toIso8601String(),
            'can_resend' => $log->order_id !== null && $log->recipient !== null,
        ];
    }
}
