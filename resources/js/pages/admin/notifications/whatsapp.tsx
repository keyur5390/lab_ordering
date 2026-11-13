import { FormField } from '@/components/form-field';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface EventTemplate {
    value: string;
    label: string;
    placeholders: string[];
    content_sid: string;
    /** Placeholder names, comma-separated, for {{1}}, {{2}}, … */
    variables: string;
    is_active: boolean;
}

interface WhatsAppProps {
    enabled: boolean;
    configured: boolean;
    events: EventTemplate[];
    examples: Record<string, string>;
}

type Row = Pick<EventTemplate, 'content_sid' | 'variables' | 'is_active'>;

/** What Twilio receives for the variables, with example values. */
function preview(variables: string, examples: Record<string, string>): string {
    return variables
        .split(',')
        .map((name) => name.trim())
        .filter(Boolean)
        .map((name, index) => `{{${index + 1}}} = ${examples[name] ?? '?'}`)
        .join(' · ');
}

export default function WhatsApp({ enabled, configured, events, examples }: WhatsAppProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm<{ templates: Record<string, Row> }>({
        templates: Object.fromEntries(
            events.map((event) => [
                event.value,
                { content_sid: event.content_sid, variables: event.variables, is_active: event.is_active },
            ]),
        ),
    });
    const fieldErrors = errors as Record<string, string | undefined>;
    const update = (event: string, row: Partial<Row>) =>
        setData('templates', { ...data.templates, [event]: { ...data.templates[event]!, ...row } });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.notifications.whatsapp.update'), { preserveScroll: true });
    };

    return (
        <AdminLayout title="WhatsApp messages">
            <TextLink href={route('admin.notifications.index')} className="text-sm">
                ← Notifications
            </TextLink>

            <div className="mt-4 max-w-3xl space-y-4 text-sm text-muted">
                <p>
                    WhatsApp only allows messages from templates approved by Meta. Create a template for each message in
                    Twilio (Content Template Builder), then paste its SID (HX…) here and list the placeholders that fill
                    its variables, in order. Leave the SID empty to not send that message on WhatsApp.
                </p>
                {!configured ? (
                    <Alert tone="warning">
                        To send WhatsApp messages, add TWILIO_SID, TWILIO_TOKEN and TWILIO_WHATSAPP_FROM to the server's
                        .env file, then switch WhatsApp on in Settings.
                    </Alert>
                ) : (
                    !enabled && (
                        <Alert tone="warning">
                            WhatsApp is switched off. Switch it on in{' '}
                            <TextLink href={route('admin.settings.edit')}>Settings</TextLink>.
                        </Alert>
                    )
                )}
            </div>

            <form onSubmit={submit} noValidate className="mt-4 max-w-3xl space-y-4">
                {events.map((event) => {
                    const row = data.templates[event.value]!;

                    return (
                        <section
                            key={event.value}
                            aria-labelledby={`${event.value}-heading`}
                            className="space-y-3 rounded-card border border-border bg-surface p-4 md:p-6"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 id={`${event.value}-heading`} className="font-semibold">
                                    {event.label}
                                </h2>
                                <label className="flex min-h-11 items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={row.is_active}
                                        onChange={(e) => update(event.value, { is_active: e.target.checked })}
                                    />
                                    Send
                                </label>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <FormField
                                    id={`${event.value}-sid`}
                                    label="Template SID"
                                    placeholder="HX…"
                                    className="font-mono"
                                    value={row.content_sid}
                                    onChange={(e) => update(event.value, { content_sid: e.target.value })}
                                    error={fieldErrors[`templates.${event.value}.content_sid`]}
                                />
                                <FormField
                                    id={`${event.value}-variables`}
                                    label="Variables, in order"
                                    placeholder="customer_name, order_number"
                                    value={row.variables}
                                    onChange={(e) => update(event.value, { variables: e.target.value })}
                                    error={fieldErrors[`templates.${event.value}.variables`]}
                                />
                            </div>
                            <p className="text-xs break-words text-muted">
                                Placeholders: {event.placeholders.join(', ')}
                            </p>
                            {row.variables.trim() !== '' && (
                                <p className="text-xs break-words">{preview(row.variables, examples)}</p>
                            )}
                        </section>
                    );
                })}

                <Button type="submit" processing={processing} disabled={!isDirty}>
                    Save templates
                </Button>
            </form>
        </AdminLayout>
    );
}
