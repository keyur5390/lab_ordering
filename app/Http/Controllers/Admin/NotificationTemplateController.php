<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateNotificationTemplateRequest;
use App\Models\NotificationTemplate;
use App\Services\NotificationTemplateService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-edited notification wording, per event and channel (client, 2026-10-29).
 */
class NotificationTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = NotificationTemplate::all()->groupBy(fn (NotificationTemplate $template) => $template->event->value);

        return Inertia::render('admin/notifications/index', [
            'events' => array_map(fn (NotificationEvent $event) => [
                'value' => $event->value,
                'label' => $event->label(),
                'for_admins' => $event->forAdmins(),
                'templates' => $templates->get($event->value, collect())
                    ->sortBy(fn (NotificationTemplate $template) => $template->channel->value)
                    ->map(fn (NotificationTemplate $template) => $this->summary($template))
                    ->values(),
            ], NotificationEvent::cases()),
        ]);
    }

    public function edit(NotificationTemplate $template, NotificationTemplateService $templates): Response
    {
        return Inertia::render('admin/notifications/edit', [
            'template' => [
                ...$this->summary($template),
                'event_label' => $template->event->label(),
                'for_admins' => $template->event->forAdmins(),
                'subject' => $template->subject,
                'body' => $template->body,
            ],
            'placeholders' => collect($templates->examples())->only($template->event->placeholders()),
        ]);
    }

    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $template): RedirectResponse
    {
        $template->update([
            'subject' => $request->validated('subject'),
            'body' => $request->validated('body'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return to_route('admin.notifications.index')->with('status', 'Notification saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(NotificationTemplate $template): array
    {
        return [
            'id' => $template->id,
            'channel' => $template->channel->value,
            'channel_label' => $template->channel->label(),
            'is_active' => $template->is_active,
        ];
    }
}
