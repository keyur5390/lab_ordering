import { SlotPicker } from '@/components/slot-picker';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import { formatSlot } from '@/lib/format';
import type { Order, SlotDay } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

interface RescheduleProps {
    order: Order;
    slots: SlotDay[];
    submitUrl: string;
    backUrl: string;
    /** Rendered in the admin layout for admins. */
    admin: boolean;
}

/** Choose a new collection slot (customers and admins). */
export default function Reschedule({ order, slots, submitUrl, backUrl, admin }: RescheduleProps) {
    const { data, setData, put, processing, errors } = useForm({ slot_starts_at: null as string | null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(submitUrl, { preserveScroll: true });
    };

    const content: ReactNode = (
        <form onSubmit={submit} noValidate className="space-y-6">
            <TextLink href={backUrl} className="text-sm">
                ← Order {order.number}
            </TextLink>
            <p className="text-sm text-muted">
                Currently booked: {formatSlot(order.slot_starts_at, order.slot_ends_at, order.timezone)}
            </p>
            <div className="rounded-card border border-border bg-surface p-4 md:p-6">
                <SlotPicker
                    days={slots}
                    value={data.slot_starts_at}
                    onChange={(startsAt) => setData('slot_starts_at', startsAt)}
                />
                <InputError message={errors.slot_starts_at} className="mt-3" />
            </div>
            <Button type="submit" processing={processing} disabled={!data.slot_starts_at}>
                Move to this slot
            </Button>
        </form>
    );

    if (admin) {
        return <AdminLayout title={`Reschedule ${order.number}`}>{content}</AdminLayout>;
    }

    return (
        <AppLayout>
            <Head title={`Reschedule ${order.number}`} />
            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <h1 className="mb-4 text-2xl font-semibold md:text-3xl">Reschedule collection</h1>
                {content}
            </div>
        </AppLayout>
    );
}
