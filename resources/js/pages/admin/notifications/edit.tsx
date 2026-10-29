import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface EditProps {
    template: {
        id: number;
        channel: 'mail' | 'sms';
        channel_label: string;
        is_active: boolean;
        event_label: string;
        for_admins: boolean;
        subject: string | null;
        body: string;
    };
    /** Placeholder → example value. */
    placeholders: Record<string, string>;
}

/** Fills placeholders with example values, the same way the server does. */
function preview(text: string, placeholders: Record<string, string>): string {
    return text.replace(/\{([a-z_]+)\}/g, (match, name: string) => placeholders[name] ?? match);
}

export default function NotificationEdit({ template, placeholders }: EditProps) {
    const isMail = template.channel === 'mail';
    const { data, setData, put, processing, errors } = useForm({
        ...(isMail ? { subject: template.subject ?? '' } : {}),
        body: template.body,
        is_active: template.is_active,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.notifications.update', template.id), { preserveScroll: true });
    };

    return (
        <AdminLayout title={`${template.event_label} · ${template.channel_label}`}>
            <TextLink href={route('admin.notifications.index')} className="text-sm">
                ← Notifications
            </TextLink>

            <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
                <form
                    onSubmit={submit}
                    noValidate
                    className="min-w-0 space-y-4 rounded-card border border-border bg-surface p-4 md:p-6"
                >
                    <label className="flex min-h-11 items-center gap-3 text-sm">
                        <Checkbox checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        Send this {isMail ? 'email' : 'text message'}
                    </label>

                    {isMail && (
                        <FormField
                            id="subject"
                            label="Subject"
                            value={data.subject ?? ''}
                            onChange={(e) => setData('subject', e.target.value)}
                            error={errors.subject}
                        />
                    )}

                    <Field
                        id="body"
                        label="Message"
                        hint={
                            isMail
                                ? 'Separate paragraphs with a blank line. A "View order" button is added below.'
                                : `${data.body.length} characters. Over 160 is sent as more than one SMS.`
                        }
                        error={errors.body}
                    >
                        {(control) => (
                            <Textarea
                                {...control}
                                rows={isMail ? 8 : 4}
                                value={data.body}
                                onChange={(e) => setData('body', e.target.value)}
                            />
                        )}
                    </Field>

                    <Button type="submit" processing={processing}>
                        Save
                    </Button>
                </form>

                <aside className="space-y-4">
                    <section
                        aria-labelledby="placeholders-heading"
                        className="rounded-card border border-border bg-surface p-4"
                    >
                        <h2 id="placeholders-heading" className="text-sm font-semibold">
                            Placeholders
                        </h2>
                        <dl className="mt-2 space-y-2 text-sm">
                            {Object.entries(placeholders).map(([name, example]) => (
                                <div key={name}>
                                    <dt className="font-mono text-xs">{`{${name}}`}</dt>
                                    <dd className="break-words text-muted">{example}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    <section
                        aria-labelledby="preview-heading"
                        className="rounded-card border border-border bg-surface p-4"
                    >
                        <h2 id="preview-heading" className="text-sm font-semibold">
                            Preview
                        </h2>
                        {isMail && (
                            <p className="mt-2 text-sm font-medium">{preview(data.subject ?? '', placeholders)}</p>
                        )}
                        <p className="mt-2 text-sm break-words whitespace-pre-line">
                            {preview(data.body, placeholders)}
                        </p>
                    </section>
                </aside>
            </div>
        </AdminLayout>
    );
}
