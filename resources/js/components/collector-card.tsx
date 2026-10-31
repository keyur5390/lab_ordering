import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { formatDateTime } from '@/lib/format';
import type { Order } from '@/types';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export interface CollectorInfo {
    assigned: { id: number; name: string } | null;
    /** Collectors who work in the order's service area. */
    options: { id: number; name: string }[];
    can_assign: boolean;
    visit_failed: { at: string; reason: string | null; note: string | null } | null;
}

/** Admin order page: the collector, how the visit went, and (re)assignment. */
export function CollectorCard({ order, collector }: { order: Order; collector: CollectorInfo }) {
    const { data, setData, put, processing, errors } = useForm({ collector_id: collector.assigned?.id ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.orders.collector.update', order.number), { preserveScroll: true });
    };

    return (
        <section
            aria-labelledby="collector-heading"
            className="space-y-3 rounded-card border border-border bg-surface p-4 md:p-6"
        >
            <h2 id="collector-heading" className="text-base font-semibold">
                Collector
            </h2>
            <p className="text-sm">{collector.assigned ? collector.assigned.name : 'Not assigned yet.'}</p>

            {collector.visit_failed && (
                <Alert tone="warning">
                    Visit failed {formatDateTime(collector.visit_failed.at, order.timezone)}:{' '}
                    {collector.visit_failed.reason}
                    {collector.visit_failed.note && ` (${collector.visit_failed.note})`}. Reschedule or cancel the
                    order.
                </Alert>
            )}

            {order.payment_collected && (
                <p className="text-sm text-muted">
                    Payment collected {formatDateTime(order.payment_collected.at, order.timezone)} by{' '}
                    {order.payment_collected.method}.
                </p>
            )}

            {collector.can_assign &&
                (collector.options.length === 0 ? (
                    <p className="text-sm text-muted">
                        No collectors work in this order's area yet. Add one under Collectors.
                    </p>
                ) : (
                    <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="collector_id">
                                {collector.assigned ? 'Change collector' : 'Assign a collector'}
                            </Label>
                            <Select
                                id="collector_id"
                                className="w-56"
                                value={data.collector_id}
                                onChange={(e) => setData('collector_id', e.target.value ? Number(e.target.value) : '')}
                            >
                                <option value="">{collector.assigned ? 'Remove collector' : 'Choose…'}</option>
                                {collector.options.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <Button
                            type="submit"
                            variant="secondary"
                            processing={processing}
                            disabled={data.collector_id === (collector.assigned?.id ?? '')}
                        >
                            Save
                        </Button>
                        <InputError message={errors.collector_id} className="w-full" />
                    </form>
                ))}
        </section>
    );
}
