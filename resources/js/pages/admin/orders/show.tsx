import { OrderActions } from '@/components/order-actions';
import { OrderDetails } from '@/components/order-details';
import { OrderStatusBadge } from '@/components/order-status-badge';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { Order } from '@/types';

export default function AdminOrderShow({ order }: { order: Order }) {
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
            </div>
            <div className="mt-6 max-w-3xl">
                <OrderDetails order={order} prescriptionUrl={route('orders.prescription', order.number)} />
            </div>
        </AdminLayout>
    );
}
