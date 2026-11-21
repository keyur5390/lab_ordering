import { MessageList, type MessageRow } from '@/components/admin/message-list';
import { Pagination } from '@/components/pagination';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { Option, Paginated } from '@/types';
import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Filters {
    status: string | null;
    channel: string | null;
    event: string | null;
    order: string;
}

interface HistoryProps {
    logs: Paginated<MessageRow>;
    filters: Filters;
    events: Option[];
    timezone: string;
}

export default function NotificationHistory({ logs, filters, events, timezone }: HistoryProps) {
    const [order, setOrder] = useState(filters.order);
    const apply = (next: Partial<Filters>) =>
        router.get(
            route('admin.notifications.history'),
            Object.fromEntries(Object.entries({ ...filters, order, ...next }).filter(([, value]) => value)),
            { preserveState: true, replace: true },
        );
    const searchOrder = (e: FormEvent) => {
        e.preventDefault();
        apply({ order });
    };

    return (
        <AdminLayout title="Notification history">
            <TextLink href={route('admin.notifications.index')} className="text-sm">
                ← Notifications
            </TextLink>

            <div className="mt-4 mb-4 grid grid-cols-2 items-end gap-3 sm:flex sm:flex-wrap">
                <div className="space-y-1.5">
                    <Label htmlFor="status">Status</Label>
                    <Select
                        id="status"
                        className="w-full sm:w-36"
                        value={filters.status ?? ''}
                        onChange={(e) => apply({ status: e.target.value || null })}
                    >
                        <option value="">All</option>
                        <option value="sent">Sent</option>
                        <option value="failed">Failed</option>
                    </Select>
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="channel">Channel</Label>
                    <Select
                        id="channel"
                        className="w-full sm:w-36"
                        value={filters.channel ?? ''}
                        onChange={(e) => apply({ channel: e.target.value || null })}
                    >
                        <option value="">All</option>
                        <option value="mail">Email</option>
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                    </Select>
                </div>
                <div className="col-span-2 space-y-1.5 sm:col-span-1">
                    <Label htmlFor="event">Message</Label>
                    <Select
                        id="event"
                        className="w-full sm:w-64"
                        value={filters.event ?? ''}
                        onChange={(e) => apply({ event: e.target.value || null })}
                    >
                        <option value="">All messages</option>
                        {events.map((event) => (
                            <option key={event.value} value={event.value}>
                                {event.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <form onSubmit={searchOrder} role="search" className="col-span-2 space-y-1.5 sm:col-span-1">
                    <Label htmlFor="order">Order number</Label>
                    <Input
                        id="order"
                        className="w-full sm:w-44"
                        placeholder="ORD-000123"
                        value={order}
                        onChange={(e) => setOrder(e.target.value)}
                    />
                </form>
            </div>

            {logs.data.length === 0 ? (
                <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <Bell className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No messages match.</p>
                </div>
            ) : (
                <div className="space-y-4">
                    <MessageList messages={logs.data} timezone={timezone} showOrder />
                    <Pagination meta={logs.meta} links={logs.links} />
                </div>
            )}
        </AdminLayout>
    );
}
