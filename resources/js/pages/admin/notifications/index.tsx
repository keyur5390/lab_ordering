import { Badge } from '@/components/ui/badge';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';

interface TemplateSummary {
    id: number;
    channel: 'mail' | 'sms';
    channel_label: string;
    is_active: boolean;
}

interface EventRow {
    value: string;
    label: string;
    for_admins: boolean;
    templates: TemplateSummary[];
}

function EventList({ title, description, events }: { title: string; description: string; events: EventRow[] }) {
    return (
        <section aria-labelledby={`${title}-heading`} className="space-y-3">
            <div>
                <h2 id={`${title}-heading`} className="text-lg font-semibold">
                    {title}
                </h2>
                <p className="text-sm text-muted">{description}</p>
            </div>
            <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                {events.map((event) => (
                    <li key={event.value} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <span className="font-medium">{event.label}</span>
                        <span className="flex flex-wrap gap-4">
                            {event.templates.map((template) => (
                                <span key={template.id} className="flex items-center gap-2 text-sm">
                                    <TextLink
                                        href={route('admin.notifications.edit', template.id)}
                                        aria-label={`Edit ${template.channel_label}: ${event.label}`}
                                        className="inline-flex min-h-11 items-center"
                                    >
                                        {template.channel_label}
                                    </TextLink>
                                    {template.is_active ? <Badge tone="success">On</Badge> : <Badge>Off</Badge>}
                                </span>
                            ))}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function NotificationsIndex({ events }: { events: EventRow[] }) {
    return (
        <AdminLayout title="Notifications">
            <div className="max-w-3xl space-y-8">
                <p className="text-sm text-muted">
                    Edit what customers and admins are sent. Channels, the reminder time and the admin address are in{' '}
                    <TextLink href={route('admin.settings.edit')}>Settings</TextLink>.
                </p>
                <EventList
                    title="To customers"
                    description="Sent by email and, where switched on, by SMS."
                    events={events.filter((event) => !event.for_admins)}
                />
                <EventList
                    title="To admins"
                    description="Emailed to the admin alert address."
                    events={events.filter((event) => event.for_admins)}
                />
            </div>
        </AdminLayout>
    );
}
