import { Badge, type BadgeProps } from '@/components/ui/badge';
import type { OrderStatus } from '@/types';

/** One colour per order status (DESIGN.md §5). */
const tones: Record<OrderStatus, NonNullable<BadgeProps['tone']>> = {
    pending_payment: 'warning',
    confirmed: 'info',
    collector_assigned: 'info',
    sample_collected: 'info',
    received_at_lab: 'info',
    report_ready: 'success',
    completed: 'success',
    cancelled: 'neutral',
    refunded: 'neutral',
};

export function OrderStatusBadge({ status, label }: { status: OrderStatus; label: string }) {
    return <Badge tone={tones[status]}>{label}</Badge>;
}
