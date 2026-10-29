import { OrderStatusBadge } from '@/components/order-status-badge';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { formatSlot } from '@/lib/format';
import type { Option, OrderStatus, Paginated } from '@/types';
import { router } from '@inertiajs/react';
import { Microscope } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface LabOrderRow {
    number: string;
    status: OrderStatus;
    status_label: string;
    slot_starts_at: string;
    slot_ends_at: string;
    items_count: number;
    reports_count: number;
}

interface LabIndexProps {
    orders: Paginated<LabOrderRow>;
    filters: { status: OrderStatus | null; q: string };
    statuses: Option<OrderStatus>[];
    timezone: string;
}

export default function LabIndex({ orders, filters, statuses, timezone }: LabIndexProps) {
    const [q, setQ] = useState(filters.q);

    const visit = (next: { status?: string | null; q?: string }) => {
        const params = { status: filters.status, q: filters.q, ...next };
        router.get(route('admin.lab.index'), Object.fromEntries(Object.entries(params).filter(([, value]) => value)), {
            preserveState: true,
        });
    };

    const search = (e: FormEvent) => {
        e.preventDefault();
        visit({ q });
    };

    return (
        <AdminLayout title="Lab">
            <div className="mb-4 flex flex-wrap items-end gap-4">
                <div className="space-y-1.5">
                    <Label htmlFor="status-filter">Status</Label>
                    <Select
                        id="status-filter"
                        className="w-56"
                        value={filters.status ?? ''}
                        onChange={(e) => visit({ status: e.target.value })}
                    >
                        <option value="">All lab orders</option>
                        {statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <form onSubmit={search} className="flex items-end gap-2" role="search">
                    <div className="space-y-1.5">
                        <Label htmlFor="order-search">Order number</Label>
                        <Input
                            id="order-search"
                            className="w-44"
                            placeholder="ORD-000123"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Find
                    </Button>
                </form>
            </div>

            {orders.data.length === 0 ? (
                <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <Microscope className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">
                        {filters.status || filters.q ? 'No orders match.' : 'No orders for the lab yet.'}
                    </p>
                </div>
            ) : (
                <div className="space-y-4">
                    <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                        {orders.data.map((order) => (
                            <li key={order.number} className="flex flex-wrap items-center justify-between gap-3 p-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <TextLink href={route('admin.lab.show', order.number)}>{order.number}</TextLink>
                                        <OrderStatusBadge status={order.status} label={order.status_label} />
                                    </div>
                                    <p className="text-sm text-muted">
                                        {formatSlot(order.slot_starts_at, order.slot_ends_at, timezone)}
                                    </p>
                                </div>
                                <p className="text-sm text-muted">
                                    Reports {order.reports_count} of {order.items_count}
                                </p>
                            </li>
                        ))}
                    </ul>
                    <Pagination meta={orders.meta} links={orders.links} />
                </div>
            )}
        </AdminLayout>
    );
}
