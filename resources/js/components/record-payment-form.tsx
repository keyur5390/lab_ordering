import { Price } from '@/components/catalogue/price';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import type { Order } from '@/types';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

/** Collection payment methods (App\Enums\CollectionPaymentMethod). */
const methods = [
    { value: 'cash', label: 'Cash' },
    { value: 'card', label: 'Card' },
    { value: 'upi', label: 'UPI' },
    { value: 'other', label: 'Other' },
];

/** Staff record a pay-at-collection payment, which issues the invoice (B-21). */
export function RecordPaymentForm({ order }: { order: Order }) {
    const { data, setData, post, processing, errors } = useForm({ method: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.orders.payment.store', order.number), { preserveScroll: true });
    };

    return (
        <section
            aria-labelledby="record-payment-heading"
            className="rounded-card border border-border bg-surface p-4 md:p-6"
        >
            <h2 id="record-payment-heading" className="text-base font-semibold">
                Payment at collection
            </h2>
            <p className="mt-1 text-sm text-muted">
                <Price amount={order.total_amount} currencyCode={order.currency} /> is still to be paid. Record it once
                received; the invoice is issued then.
            </p>
            <form onSubmit={submit} className="mt-3 flex flex-wrap items-end gap-2">
                <div className="space-y-1.5">
                    <Label htmlFor="payment-method">Paid by</Label>
                    <Select
                        id="payment-method"
                        className="w-40"
                        value={data.method}
                        onChange={(e) => setData('method', e.target.value)}
                    >
                        <option value="" disabled>
                            Choose…
                        </option>
                        {methods.map((method) => (
                            <option key={method.value} value={method.value}>
                                {method.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <Button type="submit" variant="secondary" processing={processing} disabled={!data.method}>
                    Record payment
                </Button>
                <InputError message={errors.method} className="w-full" />
            </form>
        </section>
    );
}
