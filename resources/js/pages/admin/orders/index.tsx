import { Price } from '@/components/catalogue/price';
import { OrderStatusBadge } from '@/components/order-status-badge';
import { Pagination } from '@/components/pagination';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { formatSlot } from '@/lib/format';
import type { Option, Order, OrderStatus, Paginated } from '@/types';
import { router } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';

interface OrdersIndexProps {
    orders: Paginated<Order>;
    status: OrderStatus | null;
    statuses: Option<OrderStatus>[];
}

export default function AdminOrdersIndex({ orders, status, statuses }: OrdersIndexProps) {
    return (
        <AdminLayout title="Orders">
            <div className="mb-4 flex items-center gap-2">
                <Label htmlFor="status-filter">Status</Label>
                <Select
                    id="status-filter"
                    className="w-56"
                    value={status ?? ''}
                    onChange={(e) =>
                        router.get(route('admin.orders.index'), e.target.value ? { status: e.target.value } : {}, {
                            preserveState: true,
                        })
                    }
                >
                    <option value="">All</option>
                    {statuses.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </div>

            {orders.data.length === 0 ? (
                <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <ClipboardList className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No orders{status ? ' with this status' : ' yet'}.</p>
                </div>
            ) : (
                <div className="space-y-4">
                    <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                        {orders.data.map((order) => (
                            <li key={order.number} className="flex items-center justify-between gap-3 p-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <TextLink href={route('admin.orders.show', order.number)}>
                                            {order.number}
                                        </TextLink>
                                        <OrderStatusBadge status={order.status} label={order.status_label} />
                                    </div>
                                    <p className="text-sm text-muted">
                                        {formatSlot(order.slot_starts_at, order.slot_ends_at, order.timezone)} ·{' '}
                                        {order.customer?.name} · {order.address.city}
                                    </p>
                                </div>
                                <Price amount={order.total_amount} currencyCode={order.currency} className="shrink-0" />
                            </li>
                        ))}
                    </ul>
                    <Pagination meta={orders.meta} links={orders.links} />
                </div>
            )}
        </AdminLayout>
    );
}
