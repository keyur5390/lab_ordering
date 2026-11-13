import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TextLink } from '@/components/ui/text-link';
import { formatDateTime } from '@/lib/format';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export interface MessageRow {
    id: number;
    order_number: string | null;
    event: string;
    event_label: string;
    channel: 'mail' | 'sms';
    channel_label: string;
    recipient: string | null;
    status: 'sent' | 'failed';
    error: string | null;
    at: string;
    can_resend: boolean;
}

/** Notification history rows (B-24): what was sent, to whom, and failures with a resend. */
export function MessageList({
    messages,
    timezone,
    showOrder = false,
}: {
    messages: MessageRow[];
    timezone: string;
    showOrder?: boolean;
}) {
    const [resending, setResending] = useState<number | null>(null);

    return (
        <ul className="divide-y divide-border rounded-card border border-border bg-surface">
            {messages.map((message) => (
                <li key={message.id} className="flex flex-wrap items-start justify-between gap-3 p-4">
                    <div className="min-w-0 text-sm">
                        <p className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">{message.event_label}</span>
                            <Badge>{message.channel_label}</Badge>
                            {message.status === 'sent' ? (
                                <Badge tone="success">Sent</Badge>
                            ) : (
                                <Badge tone="danger">Failed</Badge>
                            )}
                        </p>
                        <p className="break-all text-muted">
                            {formatDateTime(message.at, timezone)} · {message.recipient ?? 'no recipient'}
                            {showOrder && message.order_number && (
                                <>
                                    {' · '}
                                    <TextLink href={route('admin.orders.show', message.order_number)}>
                                        {message.order_number}
                                    </TextLink>
                                </>
                            )}
                        </p>
                        {message.error && <p className="mt-1 break-words text-danger-600">{message.error}</p>}
                    </div>
                    {message.can_resend && (
                        <Button
                            size="sm"
                            variant="secondary"
                            processing={resending === message.id}
                            onClick={() =>
                                router.post(
                                    route('admin.notifications.resend', message.id),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onStart: () => setResending(message.id),
                                        onFinish: () => setResending(null),
                                    },
                                )
                            }
                        >
                            Resend
                        </Button>
                    )}
                </li>
            ))}
        </ul>
    );
}
