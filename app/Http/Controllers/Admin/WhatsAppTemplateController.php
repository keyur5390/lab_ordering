<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppTemplate;
use App\Services\NotificationTemplateService;
use App\Services\WhatsApp\TwilioWhatsAppSender;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Notifications → WhatsApp (B-31): for each customer event, the approved Twilio Content
 * Template (SID HX…) and which placeholders fill its variables {{1}}, {{2}}, … in order.
 */
class WhatsAppTemplateController extends Controller
{
    public function edit(Settings $settings, NotificationTemplateService $templates): Response
    {
        $saved = WhatsAppTemplate::all()->keyBy(fn (WhatsAppTemplate $template) => $template->event->value);

        return Inertia::render('admin/notifications/whatsapp', [
            'enabled' => $settings->whatsAppEnabled(),
            'configured' => TwilioWhatsAppSender::isConfigured(),
            'events' => collect($this->events())->map(fn (NotificationEvent $event) => [
                'value' => $event->value,
                'label' => $event->label(),
                'placeholders' => $event->placeholders(),
                'content_sid' => $saved->get($event->value)?->content_sid ?? '',
                'variables' => implode(', ', $saved->get($event->value)?->variables ?? []),
                'is_active' => $saved->get($event->value)?->is_active ?? true,
            ])->values(),
            'examples' => $templates->examples(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'templates' => ['present', 'array'],
            'templates.*.content_sid' => ['nullable', 'string', 'regex:/^HX[0-9a-fA-F]{32}$/'],
            'templates.*.variables' => ['nullable', 'string', 'max:500'],
            'templates.*.is_active' => ['boolean'],
        ], ['templates.*.content_sid.regex' => 'A template SID starts with HX followed by 32 letters and digits.']);

        $rows = [];
        foreach ($this->events() as $event) {
            $input = $request->input("templates.{$event->value}", []);
            $sid = trim((string) ($input['content_sid'] ?? ''));
            $variables = array_values(array_filter(array_map('trim', explode(',', (string) ($input['variables'] ?? '')))));
            $unknown = array_diff($variables, $event->placeholders());
            if ($unknown) {
                throw ValidationException::withMessages(["templates.{$event->value}.variables" => 'Unknown: '.implode(', ', $unknown).'. Use the placeholder names listed.']);
            }
            $rows[$event->value] = ['sid' => $sid, 'variables' => $variables, 'is_active' => (bool) ($input['is_active'] ?? true)];
        }

        foreach ($rows as $event => $row) {
            $row['sid'] === ''
                ? WhatsAppTemplate::where('event', $event)->delete()
                : WhatsAppTemplate::updateOrCreate(['event' => $event], [
                    'content_sid' => $row['sid'],
                    'variables' => $row['variables'],
                    'is_active' => $row['is_active'],
                ]);
        }

        return to_route('admin.notifications.whatsapp.edit')->with('status', 'WhatsApp templates saved.');
    }

    /**
     * Customer events: admin alerts go by email only.
     *
     * @return list<NotificationEvent>
     */
    private function events(): array
    {
        return array_values(array_filter(NotificationEvent::cases(), fn (NotificationEvent $event) => ! $event->forAdmins()));
    }
}
