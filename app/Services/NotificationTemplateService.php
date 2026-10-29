<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Settings\Settings;
use App\Support\Money;

/**
 * Fills admin-edited wording (notification_templates) with an order's details.
 */
class NotificationTemplateService
{
    public function __construct(private Settings $settings) {}

    /**
     * The message for this event and channel, or null when there's no active wording.
     *
     * @return array{subject: string, body: string}|null
     */
    public function render(NotificationEvent $event, NotificationChannel $channel, Order $order, ?int $amount = null): ?array
    {
        $template = NotificationTemplate::where('event', $event)->where('channel', $channel)->where('is_active', true)->first();
        if (! $template) {
            return null;
        }

        $values = $this->values($event, $order, $amount);
        $fill = fn (string $text) => preg_replace_callback(
            '/\{([a-z_]+)\}/',
            fn (array $match) => $values[$match[1]] ?? $match[0],
            $text,
        );

        return ['subject' => $fill((string) $template->subject), 'body' => $fill($template->body)];
    }

    /**
     * Example values for every placeholder, shown to admins while editing.
     *
     * @return array<string, string>
     */
    public function examples(): array
    {
        return [
            'site_name' => (string) config('app.name'),
            'customer_name' => 'Asha Rao',
            'order_number' => 'ORD-000123',
            'slot' => 'Fri 30 Oct, 09:00–10:00',
            'address' => '4 FC Road, Pune, 411004',
            'total' => Money::format(149900, $this->settings->currency()->value ?? 'INR'),
            'order_url' => route('orders.show', 'ORD-000123'),
            'amount' => Money::format(49900, $this->settings->currency()->value ?? 'INR'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function values(NotificationEvent $event, Order $order, ?int $amount): array
    {
        $timezone = $this->settings->timezone();
        $start = $order->slot_starts_at->setTimezone($timezone);

        return [
            'site_name' => (string) config('app.name'),
            'customer_name' => $order->user->name,
            'order_number' => (string) $order->number,
            'slot' => $start->format('D j M, H:i').'–'.$order->slot_ends_at->setTimezone($timezone)->format('H:i'),
            'address' => collect([$order->address['line1'] ?? null, $order->address['city'] ?? null, $order->address['postcode'] ?? null])
                ->filter()->implode(', '),
            'total' => Money::format($order->total_amount, $order->currency),
            'order_url' => $event->forAdmins() ? route('admin.orders.show', $order) : route('orders.show', $order),
            'amount' => Money::format($amount ?? $order->total_amount, $order->currency),
        ];
    }
}
