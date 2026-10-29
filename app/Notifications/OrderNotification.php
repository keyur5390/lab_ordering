<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\LabService;
use App\Services\NotificationTemplateService;
use App\Settings\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * One order event, on every channel that is switched on, has active wording and can reach the
 * recipient. Admin alerts go by email only. Queued, and sent only after the order is saved.
 */
class OrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Reports are attached to the "reports ready" email only up to this total size; bigger sets are
     * downloaded from the account (the link is always included).
     * TODO(client): confirm the limit; many mail providers reject messages over 10–25 MB.
     */
    public const REPORT_ATTACHMENT_LIMIT_BYTES = 10 * 1024 * 1024;

    public function __construct(
        public NotificationEvent $event,
        public Order $order,
        /** Payment or refund amount, in minor units. */
        public ?int $amount = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $settings = app(Settings::class);
        $channels = [];

        if (($this->event->forAdmins() || $settings->notifyByEmail()) && $this->message(NotificationChannel::Mail)) {
            $channels[] = 'mail';
        }

        if ($notifiable instanceof User
            && in_array(NotificationChannel::Sms, $this->event->channels(), true)
            && $settings->smsDriver()
            && ! $notifiable->sms_opt_out
            && $notifiable->routeNotificationForSms()
            && $this->message(NotificationChannel::Sms)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message(NotificationChannel::Mail);
        $mail = (new MailMessage)->subject($message['subject']);

        // Paragraphs are separated by blank lines; lines are escaped by the mail template.
        foreach (preg_split('/\R{2,}/', trim($message['body'])) as $paragraph) {
            $mail->line($paragraph);
        }

        if ($this->event === NotificationEvent::ReportReady) {
            $this->attachReports($mail);
        }

        return $mail->action('View order', $notifiable instanceof AnonymousNotifiable
            ? route('admin.orders.show', $this->order)
            : route('orders.show', $this->order));
    }

    public function toSms(object $notifiable): ?string
    {
        return $this->message(NotificationChannel::Sms)['body'] ?? null;
    }

    /** Attaches every report (client, 2026-10-29: by email and in the account), within the size limit. */
    private function attachReports(MailMessage $mail): void
    {
        $disk = Storage::disk(LabService::REPORT_DISK);
        $reports = $this->order->items()->whereNotNull('report_path')->get()
            ->filter(fn (OrderItem $item) => $disk->exists($item->report_path));

        if ($reports->sum(fn (OrderItem $item) => $disk->size($item->report_path)) > self::REPORT_ATTACHMENT_LIMIT_BYTES) {
            return;
        }

        foreach ($reports as $item) {
            $mail->attach($disk->path($item->report_path), [
                'as' => "{$item->patient_name} - {$item->name}.pdf",
                'mime' => 'application/pdf',
            ]);
        }
    }

    /**
     * @return array{subject: string, body: string}|null
     */
    private function message(NotificationChannel $channel): ?array
    {
        return app(NotificationTemplateService::class)->render($this->event, $channel, $this->order, $this->amount);
    }
}
