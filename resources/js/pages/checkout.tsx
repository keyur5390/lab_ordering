import { addressLines } from '@/components/address-lines';
import { Price } from '@/components/catalogue/price';
import { SlotPicker } from '@/components/slot-picker';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { InputError } from '@/components/ui/input-error';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Address, CartLine, Patient, SlotDay } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FileUp } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

interface CheckoutProps {
    /** Available cart lines. */
    lines: CartLine[];
    patients: Patient[];
    addresses: Address[];
    addressId: number | null;
    slots: SlotDay[];
    prescriptionRequired: boolean;
}

function Step({ number, title, children }: { number: number; title: string; children: ReactNode }) {
    return (
        <section aria-labelledby={`step-${number}`} className="rounded-card border border-border bg-surface p-4 md:p-6">
            <h2 id={`step-${number}`} className="flex items-center gap-3 text-base font-semibold">
                <span
                    className="flex size-7 items-center justify-center rounded-full bg-primary-50 text-sm text-primary-700"
                    aria-hidden="true"
                >
                    {number}
                </span>
                {title}
            </h2>
            <div className="mt-4">{children}</div>
        </section>
    );
}

export default function Checkout({
    lines,
    patients,
    addresses,
    addressId,
    slots,
    prescriptionRequired,
}: CheckoutProps) {
    const selfPatient = patients.find((patient) => patient.is_self && patient.is_complete);
    const { data, setData, post, processing, errors, transform } = useForm({
        // Cart item id → chosen patient ids; defaults to the customer themselves when their profile is complete.
        items: Object.fromEntries(lines.map((line) => [line.id, selfPatient ? [selfPatient.id] : []])) as Record<
            string,
            number[]
        >,
        address_id: addressId,
        slot_starts_at: null as string | null,
        prescription: null as File | null,
    });
    const allErrors = errors as Record<string, string>;
    const step = prescriptionRequired ? 4 : 3;

    // Price is per patient; the server works out the final total.
    const total = lines.reduce((sum, line) => sum + line.price_amount * (data.items[line.id]?.length ?? 0), 0);

    transform((form) => ({ ...form, prescription: prescriptionRequired ? form.prescription : null }));

    const togglePatient = (lineId: number, patientId: number, checked: boolean) => {
        const current = data.items[lineId] ?? [];
        setData('items', {
            ...data.items,
            [lineId]: checked ? [...current, patientId] : current.filter((id) => id !== patientId),
        });
    };

    const chooseAddress = (id: number) => {
        setData((form) => ({ ...form, address_id: id, slot_starts_at: null }));
        router.reload({ only: ['slots', 'addressId'], data: { address_id: id } });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('checkout.store'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Checkout" />

            <form onSubmit={submit} noValidate className="mx-auto max-w-5xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('cart.show')} className="text-sm">
                    ← Cart
                </TextLink>
                <h1 className="mt-4 text-2xl font-semibold md:text-3xl">Checkout</h1>

                {(allErrors.order || allErrors.items) && (
                    <Alert tone="danger" className="mt-4">
                        {allErrors.order ?? allErrors.items}
                    </Alert>
                )}

                <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
                    <div className="min-w-0 space-y-6">
                        <Step number={1} title="Who is each test for?">
                            {patients.some((patient) => !patient.is_complete) && (
                                <p className="mb-3 text-sm text-muted">
                                    Patients need a date of birth and sex before they can be booked.{' '}
                                    <TextLink href={route('patients.index')}>Update patients</TextLink>
                                </p>
                            )}
                            <ul className="space-y-4">
                                {lines.map((line) => (
                                    <li key={line.id}>
                                        <fieldset
                                            aria-describedby={
                                                allErrors[`items.${line.id}`] ? `items-${line.id}-error` : undefined
                                            }
                                        >
                                            <legend className="flex w-full flex-wrap items-baseline justify-between gap-2">
                                                <span className="font-medium">{line.name}</span>
                                                <span className="text-sm text-muted">
                                                    <Price amount={line.price_amount} /> per person
                                                </span>
                                            </legend>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {patients.map((patient) => (
                                                    <label
                                                        key={patient.id}
                                                        className={cn(
                                                            'flex min-h-11 items-center gap-2 rounded-control border border-border px-3 text-sm',
                                                            patient.is_complete
                                                                ? 'cursor-pointer has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50'
                                                                : 'cursor-not-allowed text-muted',
                                                        )}
                                                    >
                                                        <Checkbox
                                                            disabled={!patient.is_complete}
                                                            checked={(data.items[line.id] ?? []).includes(patient.id)}
                                                            onChange={(e) =>
                                                                togglePatient(line.id, patient.id, e.target.checked)
                                                            }
                                                        />
                                                        {patient.name}
                                                        {!patient.is_complete && (
                                                            <Badge tone="warning">Details needed</Badge>
                                                        )}
                                                    </label>
                                                ))}
                                                <TextLink
                                                    href={route('patients.create')}
                                                    className="flex min-h-11 items-center px-2 text-sm"
                                                >
                                                    + Add patient
                                                </TextLink>
                                            </div>
                                            <InputError
                                                id={`items-${line.id}-error`}
                                                message={allErrors[`items.${line.id}`]}
                                                className="mt-1"
                                            />
                                        </fieldset>
                                    </li>
                                ))}
                            </ul>
                        </Step>

                        <Step number={2} title="Where should we collect?">
                            {addresses.length === 0 ? (
                                <p className="text-sm text-muted">
                                    You have no saved addresses.{' '}
                                    <TextLink href={route('addresses.create')}>Add an address</TextLink>
                                </p>
                            ) : (
                                <div role="radiogroup" aria-label="Address" className="space-y-2">
                                    {addresses.map((address) => (
                                        <label
                                            key={address.id}
                                            className={cn(
                                                'flex gap-3 rounded-control border border-border p-3',
                                                address.serviceable
                                                    ? 'cursor-pointer has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50'
                                                    : 'cursor-not-allowed text-muted',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="address_id"
                                                className="mt-0.5 size-4 accent-primary-600"
                                                disabled={!address.serviceable}
                                                checked={data.address_id === address.id}
                                                onChange={() => chooseAddress(address.id)}
                                            />
                                            <span className="min-w-0 text-sm">
                                                {address.label && (
                                                    <span className="block font-medium">{address.label}</span>
                                                )}
                                                <span className="block">{addressLines(address).join(', ')}</span>
                                                {!address.serviceable && (
                                                    <Badge tone="warning" className="mt-1">
                                                        Outside our collection area
                                                    </Badge>
                                                )}
                                            </span>
                                        </label>
                                    ))}
                                    <TextLink href={route('addresses.create')} className="inline-block pt-1 text-sm">
                                        + Add address
                                    </TextLink>
                                </div>
                            )}
                            <InputError message={errors.address_id} className="mt-2" />
                        </Step>

                        <Step number={3} title="When should we come?">
                            {data.address_id ? (
                                <SlotPicker
                                    key={data.address_id}
                                    days={slots}
                                    value={data.slot_starts_at}
                                    onChange={(startsAt) => setData('slot_starts_at', startsAt)}
                                />
                            ) : (
                                <p className="text-sm text-muted">Choose an address to see available times.</p>
                            )}
                            <InputError message={errors.slot_starts_at} className="mt-2" />
                        </Step>

                        {prescriptionRequired && (
                            <Step number={step} title="Upload your prescription">
                                <p className="text-sm text-muted">
                                    One or more tests in your order need a doctor's prescription. PDF, JPG or PNG, up to
                                    10 MB.
                                </p>
                                <label className="mt-3 flex cursor-pointer items-center gap-3 rounded-control border border-dashed border-border p-4 hover:bg-canvas">
                                    <FileUp className="size-5 text-muted" strokeWidth={1.75} aria-hidden="true" />
                                    <span className="text-sm">
                                        {data.prescription ? data.prescription.name : 'Choose a file'}
                                    </span>
                                    <input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        className="sr-only"
                                        aria-describedby={errors.prescription ? 'prescription-error' : undefined}
                                        onChange={(e) => setData('prescription', e.target.files?.[0] ?? null)}
                                    />
                                </label>
                                <InputError id="prescription-error" message={errors.prescription} className="mt-2" />
                            </Step>
                        )}
                    </div>

                    <aside
                        aria-label="Order summary"
                        className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6 lg:sticky lg:top-6"
                    >
                        <h2 className="text-base font-semibold">Summary</h2>
                        <ul className="space-y-2 text-sm">
                            {lines.map((line) => {
                                const count = data.items[line.id]?.length ?? 0;

                                return (
                                    <li key={line.id} className="flex justify-between gap-3">
                                        <span className={cn('min-w-0', count === 0 && 'text-muted')}>
                                            {line.name}
                                            {count > 1 && ` × ${count}`}
                                        </span>
                                        <Price amount={line.price_amount * count} className="shrink-0" />
                                    </li>
                                );
                            })}
                        </ul>
                        <div className="flex items-baseline justify-between border-t border-border pt-3">
                            <span className="font-semibold">Total</span>
                            <Price amount={total} className="text-lg" />
                        </div>
                        <p className="text-sm text-muted">Payment: pay at collection.</p>
                        <Button type="submit" className="w-full" processing={processing}>
                            Place order
                        </Button>
                    </aside>
                </div>
            </form>
        </AppLayout>
    );
}
