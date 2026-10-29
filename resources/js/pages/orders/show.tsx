import { OrderActions } from '@/components/order-actions';
import { OrderDetails } from '@/components/order-details';
import { OrderStatusBadge } from '@/components/order-status-badge';
import { Alert } from '@/components/ui/alert';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { Order, SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';

export default function OrderShow({ order, cutoffHours }: { order: Order; cutoffHours: number | null }) {
    const { status } = usePage<SharedData>().props;
    const beforeCollection = ['pending_payment', 'confirmed', 'collector_assigned'].includes(order.status);

    return (
        <AppLayout>
            <Head title={`Order ${order.number}`} />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('orders.index')} className="text-sm">
                    ← Orders
                </TextLink>

                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-semibold md:text-3xl">Order {order.number}</h1>
                    <OrderStatusBadge status={order.status} label={order.status_label} />
                </div>
                <p className="mt-1 text-sm text-muted">
                    Placed{' '}
                    {formatDate(order.placed_at.slice(0, 10), { day: 'numeric', month: 'long', year: 'numeric' })}
                </p>

                {status && (
                    <Alert tone="success" className="mt-4">
                        {status}
                    </Alert>
                )}

                <div className="mt-6">
                    <OrderDetails order={order} prescriptionUrl={route('orders.prescription', order.number)} />
                </div>

                {beforeCollection && (
                    <div className="mt-6 space-y-3">
                        <OrderActions
                            order={order}
                            cancelUrl={route('orders.cancel', order.number)}
                            rescheduleUrl={route('orders.reschedule.edit', order.number)}
                        />
                        {!order.can_cancel && (
                            <p className="text-sm text-muted">
                                {cutoffHours === null
                                    ? 'To change or cancel this order, please contact us.'
                                    : `Orders can be changed or cancelled up to ${cutoffHours} hours before the collection. To change it now, please contact us.`}
                            </p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
