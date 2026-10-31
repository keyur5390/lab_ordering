import { Price } from '@/components/catalogue/price';
import { OrderStatusBadge } from '@/components/order-status-badge';
import { Pagination } from '@/components/pagination';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import { formatSlot } from '@/lib/format';
import type { Order, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';

export default function OrdersIndex({ orders }: { orders: Paginated<Order> }) {
    return (
        <AppLayout>
            <Head title="Orders" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('account')} className="text-sm">
                    ← Your account
                </TextLink>
                <h1 className="mt-4 text-2xl font-semibold md:text-3xl">Orders</h1>

                {orders.data.length === 0 ? (
                    <div className="mt-6 flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center">
                        <ClipboardList className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                        <p className="mt-3 text-sm text-muted">You haven't placed any orders yet.</p>
                        <Link
                            href={route('catalogue.index')}
                            className="mt-4 inline-flex h-11 items-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700"
                        >
                            Browse tests
                        </Link>
                    </div>
                ) : (
                    <div className="mt-6 space-y-4">
                        <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                            {orders.data.map((order) => (
                                <li key={order.number}>
                                    <Link
                                        href={route('orders.show', order.number)}
                                        className="flex items-center justify-between gap-3 p-4 hover:bg-canvas"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">{order.number}</span>
                                                <OrderStatusBadge status={order.status} label={order.status_label} />
                                            </div>
                                            <p className="text-sm text-muted">
                                                {formatSlot(order.slot_starts_at, order.slot_ends_at, order.timezone)} ·{' '}
                                                {order.items_count} {order.items_count === 1 ? 'test' : 'tests'}
                                            </p>
                                        </div>
                                        <Price
                                            amount={order.total_amount}
                                            currencyCode={order.currency}
                                            className="shrink-0"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <Pagination meta={orders.meta} links={orders.links} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
