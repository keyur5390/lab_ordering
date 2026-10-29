import { OrderActions } from '@/components/order-actions';
import { OrderDetails } from '@/components/order-details';
import { OrderStatusBadge } from '@/components/order-status-badge';
import { RefundForm } from '@/components/refund-form';
import { Alert } from '@/components/ui/alert';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { Order, SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/** Orders the lab works on (Lab\\OrderController::STATUSES). */
const labStatuses: Order['status'][] = [
    'confirmed',
    'collector_assigned',
    'sample_collected',
    'received_at_lab',
    'report_ready',
];

export default function AdminOrderShow({ order }: { order: Order }) {
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;

    return (
        <AdminLayout
            title={`Order ${order.number}`}
            actions={
                <OrderActions
                    order={order}
                    cancelUrl={route('admin.orders.cancel', order.number)}
                    rescheduleUrl={route('admin.orders.reschedule.edit', order.number)}
                />
            }
        >
            <TextLink href={route('admin.orders.index')} className="text-sm">
                ← Orders
            </TextLink>
            <div className="mt-4 flex flex-wrap items-center gap-3">
                <OrderStatusBadge status={order.status} label={order.status_label} />
                <span className="text-sm text-muted">
                    {order.customer?.name} · {order.customer?.email}
                </span>
                {labStatuses.includes(order.status) && (
                    <TextLink href={route('admin.lab.show', order.number)} className="text-sm">
                        Lab view
                    </TextLink>
                )}
            </div>
            {/* Refund errors are shown in the form; this covers a refund that failed while cancelling. */}
            {errors.amount && !order.can_refund && (
                <Alert tone="danger" className="mt-4">
                    {errors.amount}
                </Alert>
            )}
            <div className="mt-6 max-w-3xl space-y-6">
                {/* Keyed so the amount resets to what is left after each refund. */}
                {order.can_refund && <RefundForm key={order.payment?.refundable} order={order} />}
                <OrderDetails order={order} prescriptionUrl={route('orders.prescription', order.number)} />
            </div>
        </AdminLayout>
    );
}
