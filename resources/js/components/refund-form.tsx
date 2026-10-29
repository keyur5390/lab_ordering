import { Price } from '@/components/catalogue/price';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import type { Order } from '@/types';
import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

/** Admin refund of an online payment, in full or in part (with confirmation). */
export function RefundForm({ order }: { order: Order }) {
    const refundable = order.payment?.refundable ?? 0;
    // All supported currencies have 2 decimals (App\Enums\Currency).
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: (refundable / 100).toFixed(2),
        reason: '',
    });
    const [confirming, setConfirming] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setConfirming(true);
    };

    return (
        <section aria-labelledby="refund-heading" className="rounded-card border border-border bg-surface p-4 md:p-6">
            <h2 id="refund-heading" className="text-base font-semibold">
                Refund
            </h2>
            <p className="mt-1 text-sm text-muted">
                Up to <Price amount={refundable} currencyCode={order.currency} /> can be refunded to the customer's
                original payment method.
            </p>
            <form onSubmit={submit} noValidate className="mt-4 grid gap-4 sm:grid-cols-[10rem_1fr_auto] sm:items-start">
                <FormField
                    id="refund-amount"
                    label={`Amount (${order.currency})`}
                    inputMode="decimal"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    error={errors.amount}
                />
                <FormField
                    id="refund-reason"
                    label="Reason (optional)"
                    value={data.reason}
                    onChange={(e) => setData('reason', e.target.value)}
                    error={errors.reason}
                />
                <Button type="submit" variant="secondary" className="sm:mt-7">
                    Refund
                </Button>
            </form>
            <ConfirmDialog
                open={confirming}
                title={`Refund ${order.currency} ${data.amount}?`}
                confirmLabel="Refund"
                processing={processing}
                onClose={() => setConfirming(false)}
                onConfirm={() =>
                    post(route('admin.orders.refunds.store', order.number), {
                        preserveScroll: true,
                        onSuccess: () => reset('reason'),
                        onFinish: () => setConfirming(false),
                    })
                }
            >
                The money goes back to the customer's original payment method. This can't be undone.
            </ConfirmDialog>
        </section>
    );
}
