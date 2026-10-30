import { addressLines } from '@/components/address-lines';
import { Price } from '@/components/catalogue/price';
import { SlotPicker } from '@/components/slot-picker';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { InputError } from '@/components/ui/input-error';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Address, Branch, CartLine, CollectionType, Patient, PaymentMethod, SlotDay } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FileUp } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

interface CheckoutProps {
    /** Available cart lines. */
    lines: CartLine[];
    patients: Patient[];
    addresses: Address[];
    addressId: number | null;
    /** Lab visits (B-17): branches taking bookings, and the chosen one. */
    collectionType: CollectionType;
    branches: Branch[];
    branchId: number | null;
    slots: SlotDay[];
    prescriptionRequired: boolean;
    /** Whether customers can pay online (a gateway is set up). */
    onlinePayment: boolean;
    paymentHoldMinutes: number;
    /** Worked out on the server (OrderPricingService); reloaded as the order changes. */
    quote: CheckoutQuote;
    /** The code the customer entered, if any, and why it can't be used. */
    coupon: { code: string; description: string | null; error: string | null } | null;
}

interface CheckoutQuote {
    subtotal: number;
    discount: number;
    collection_fee: number;
    collection_fee_waived: boolean;
    tax_rate_basis_points: number | null;
    tax_inclusive: boolean;
    tax: number;
    total: number;
    minimum: number | null;
    free_collection_above: number | null;
}

function SummaryRow({ label, children, muted }: { label: ReactNode; children: ReactNode; muted?: boolean }) {
    return (
        <div className={cn('flex justify-between gap-3 text-sm', muted && 'text-muted')}>
            <span className="min-w-0">{label}</span>
            <span className="shrink-0">{children}</span>
        </div>
    );
}

const paymentOptions: { value: PaymentMethod; title: string; hint: (holdMinutes: number) => string }[] = [
    {
        value: 'online',
        title: 'Pay online now',
        hint: (holdMinutes) =>
            `You'll pay on our payment partner's secure page. We hold your slot for ${holdMinutes} minutes while you pay.`,
    },
    { value: 'pay_at_collection', title: 'Pay at collection', hint: () => 'Pay the collector when they arrive.' },
];

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
    collectionType,
    branches,
    branchId,
    slots,
    prescriptionRequired,
    onlinePayment,
    paymentHoldMinutes,
    quote,
    coupon,
}: CheckoutProps) {
    const selfPatient = patients.find((patient) => patient.is_self && patient.is_complete);
    const { data, setData, post, processing, errors, transform } = useForm({
        // Cart item id → chosen patient ids; defaults to the customer themselves when their profile is complete.
        items: Object.fromEntries(lines.map((line) => [line.id, selfPatient ? [selfPatient.id] : []])) as Record<
            string,
            number[]
        >,
        address_id: addressId,
        collection_type: collectionType,
        branch_id: branchId,
        slot_starts_at: null as string | null,
        prescription: null as File | null,
        payment_method: (onlinePayment ? 'online' : 'pay_at_collection') as PaymentMethod,
    });
    const allErrors = errors as Record<string, string>;
    const prescriptionStep = 4;
    const paymentStep = prescriptionRequired ? 5 : 4;
    const payOnline = data.payment_method === 'online';

    const [couponInput, setCouponInput] = useState(coupon?.code ?? '');
    const appliedCoupon = coupon && !coupon.error ? coupon.code : '';
    const belowMinimum = quote.minimum !== null && quote.subtotal < quote.minimum;
    const taxPercent = quote.tax_rate_basis_points !== null ? quote.tax_rate_basis_points / 100 : null;

    transform((form) => ({
        ...form,
        prescription: prescriptionRequired ? form.prescription : null,
        coupon_code: appliedCoupon || null,
    }));

    /** Re-prices the order on the server (and reloads slots when the address changes). */
    const refresh = (next: {
        items?: Record<string, number[]>;
        address_id?: number | null;
        collection_type?: CollectionType;
        branch_id?: number | null;
        coupon?: string;
    }) => {
        const items = next.items ?? data.items;
        const placeChanged =
            next.address_id !== undefined || next.collection_type !== undefined || next.branch_id !== undefined;
        router.reload({
            only: placeChanged
                ? ['slots', 'addressId', 'collectionType', 'branchId', 'quote', 'coupon']
                : ['quote', 'coupon'],
            data: {
                address_id: next.address_id ?? data.address_id,
                collection_type: next.collection_type ?? data.collection_type,
                branch_id: next.branch_id ?? data.branch_id,
                counts: Object.fromEntries(Object.entries(items).map(([id, patients]) => [id, patients.length])),
                coupon: next.coupon ?? appliedCoupon,
            },
        });
    };

    const togglePatient = (lineId: number, patientId: number, checked: boolean) => {
        const current = data.items[lineId] ?? [];
        const items = {
            ...data.items,
            [lineId]: checked ? [...current, patientId] : current.filter((id) => id !== patientId),
        };
        setData('items', items);
        refresh({ items });
    };

    const labVisit = data.collection_type === 'lab_visit';

    const chooseType = (type: CollectionType) => {
        const branch = type === 'lab_visit' ? (data.branch_id ?? branches[0]?.id ?? null) : data.branch_id;
        setData((form) => ({ ...form, collection_type: type, branch_id: branch, slot_starts_at: null }));
        refresh({ collection_type: type, branch_id: branch });
    };

    const chooseBranch = (id: number) => {
        setData((form) => ({ ...form, branch_id: id, slot_starts_at: null }));
        refresh({ branch_id: id });
    };

    const chooseAddress = (id: number) => {
        setData((form) => ({ ...form, address_id: id, slot_starts_at: null }));
        refresh({ address_id: id });
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

                        <Step number={2} title={labVisit ? 'Which branch will you visit?' : 'Where should we collect?'}>
                            {branches.length > 0 && (
                                <div
                                    role="radiogroup"
                                    aria-label="How to give your sample"
                                    className="mb-4 grid gap-2 sm:grid-cols-2"
                                >
                                    {(
                                        [
                                            { value: 'home', title: 'Home collection', hint: 'We come to you.' },
                                            {
                                                value: 'lab_visit',
                                                title: 'Visit a lab',
                                                hint: 'Come to one of our branches.',
                                            },
                                        ] as const
                                    ).map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex cursor-pointer gap-3 rounded-control border border-border p-3 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50"
                                        >
                                            <input
                                                type="radio"
                                                name="collection_type"
                                                className="mt-0.5 size-4 accent-primary-600"
                                                checked={data.collection_type === option.value}
                                                onChange={() => chooseType(option.value)}
                                            />
                                            <span className="text-sm">
                                                <span className="block font-medium">{option.title}</span>
                                                <span className="block text-muted">{option.hint}</span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}
                            {labVisit ? (
                                <div role="radiogroup" aria-label="Branch" className="space-y-2">
                                    {branches.map((branch) => (
                                        <label
                                            key={branch.id}
                                            className="flex cursor-pointer gap-3 rounded-control border border-border p-3 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50"
                                        >
                                            <input
                                                type="radio"
                                                name="branch_id"
                                                className="mt-0.5 size-4 shrink-0 accent-primary-600"
                                                checked={data.branch_id === branch.id}
                                                onChange={() => chooseBranch(branch.id)}
                                            />
                                            <span className="min-w-0 flex-1 text-sm">
                                                <span className="block font-medium">{branch.name}</span>
                                                <span className="block">{addressLines(branch).join(', ')}</span>
                                                {branch.directions && (
                                                    <span className="block text-muted">{branch.directions}</span>
                                                )}
                                                <span className="mt-1 flex flex-wrap gap-x-4">
                                                    <a
                                                        href={branch.map_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="font-medium text-primary-700 hover:underline"
                                                    >
                                                        Map
                                                    </a>
                                                    {branch.phone && <span className="text-muted">{branch.phone}</span>}
                                                </span>
                                            </span>
                                            {branch.photo_url && (
                                                <img
                                                    src={branch.photo_url}
                                                    alt=""
                                                    className="size-16 shrink-0 rounded-control object-cover"
                                                />
                                            )}
                                        </label>
                                    ))}
                                    <InputError message={allErrors.branch_id} className="mt-2" />
                                </div>
                            ) : addresses.length === 0 ? (
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
                            {!labVisit && <InputError message={errors.address_id} className="mt-2" />}
                        </Step>

                        <Step number={3} title={labVisit ? 'When will you visit?' : 'When should we come?'}>
                            {(labVisit ? data.branch_id : data.address_id) ? (
                                <SlotPicker
                                    key={`${data.collection_type}-${labVisit ? data.branch_id : data.address_id}`}
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
                            <Step number={prescriptionStep} title="Upload your prescription">
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

                        {onlinePayment && (
                            <Step number={paymentStep} title="How would you like to pay?">
                                <div role="radiogroup" aria-label="Payment" className="space-y-2">
                                    {paymentOptions.map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex cursor-pointer gap-3 rounded-control border border-border p-3 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50"
                                        >
                                            <input
                                                type="radio"
                                                name="payment_method"
                                                className="mt-0.5 size-4 accent-primary-600"
                                                checked={data.payment_method === option.value}
                                                onChange={() => setData('payment_method', option.value)}
                                            />
                                            <span className="min-w-0 text-sm">
                                                <span className="block font-medium">{option.title}</span>
                                                <span className="block text-muted">
                                                    {option.hint(paymentHoldMinutes)}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.payment_method} className="mt-2" />
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
                        <div className="space-y-1.5 border-t border-border pt-3">
                            <SummaryRow label="Tests">
                                <Price amount={quote.subtotal} />
                            </SummaryRow>
                            {quote.discount > 0 && (
                                <SummaryRow label={`Code ${appliedCoupon}`}>
                                    −<Price amount={quote.discount} />
                                </SummaryRow>
                            )}
                            {!labVisit && data.address_id && (
                                <SummaryRow label="Home collection">
                                    {quote.collection_fee > 0 ? <Price amount={quote.collection_fee} /> : 'Free'}
                                </SummaryRow>
                            )}
                            {!labVisit && quote.collection_fee > 0 && quote.free_collection_above !== null && (
                                <p className="text-xs text-muted">
                                    Free collection on orders of <Price amount={quote.free_collection_above} /> or more.
                                </p>
                            )}
                            {taxPercent !== null && !quote.tax_inclusive && (
                                <SummaryRow label={`Tax (${taxPercent}%)`}>
                                    <Price amount={quote.tax} />
                                </SummaryRow>
                            )}
                        </div>
                        <div className="flex items-baseline justify-between border-t border-border pt-3">
                            <span className="font-semibold">Total</span>
                            <Price amount={quote.total} className="text-lg" />
                        </div>
                        {taxPercent !== null && quote.tax_inclusive && (
                            <p className="text-xs text-muted">
                                Includes <Price amount={quote.tax} /> tax ({taxPercent}%).
                            </p>
                        )}

                        <div className="space-y-1.5 border-t border-border pt-3">
                            <label htmlFor="coupon" className="text-sm font-medium">
                                Promo code
                            </label>
                            <div className="flex gap-2">
                                <Input
                                    id="coupon"
                                    value={couponInput}
                                    autoCapitalize="characters"
                                    aria-describedby={
                                        coupon?.error || allErrors.coupon_code ? 'coupon-error' : undefined
                                    }
                                    onChange={(e) => setCouponInput(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            refresh({ coupon: couponInput.trim() });
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    disabled={!couponInput.trim()}
                                    onClick={() => refresh({ coupon: couponInput.trim() })}
                                >
                                    Apply
                                </Button>
                            </div>
                            {appliedCoupon && (
                                <p className="text-sm text-success-600" role="status">
                                    {appliedCoupon} applied{coupon?.description && `: ${coupon.description}`}.{' '}
                                    <button
                                        type="button"
                                        className="font-medium text-primary-700 underline"
                                        onClick={() => {
                                            setCouponInput('');
                                            refresh({ coupon: '' });
                                        }}
                                    >
                                        Remove
                                    </button>
                                </p>
                            )}
                            <InputError id="coupon-error" message={coupon?.error ?? allErrors.coupon_code} />
                        </div>

                        {belowMinimum && quote.minimum !== null && (
                            <Alert tone="warning">
                                The minimum order is <Price amount={quote.minimum} />. Add more tests to continue.
                            </Alert>
                        )}
                        <p className="text-sm text-muted">
                            Payment: {payOnline ? 'online, on the next page' : 'pay at collection'}.
                        </p>
                        {!onlinePayment && <InputError message={errors.payment_method} />}
                        <Button type="submit" className="w-full" processing={processing} disabled={belowMinimum}>
                            {payOnline ? 'Continue to payment' : 'Place order'}
                        </Button>
                    </aside>
                </div>
            </form>
        </AppLayout>
    );
}
