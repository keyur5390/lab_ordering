import { Price } from '@/components/catalogue/price';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { CartLine, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FlaskConical, Info, Package, ShoppingCart } from 'lucide-react';
import { useState } from 'react';

interface CartProps {
    lines: CartLine[];
    /** Total of available lines, in minor units. */
    totalAmount: number;
}

const primaryLinkClass =
    'inline-flex h-11 w-full items-center justify-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700';

function CartLineRow({ line }: { line: CartLine }) {
    const [removing, setRemoving] = useState(false);
    const href =
        line.type === 'test' ? route('catalogue.tests.show', line.slug) : route('catalogue.packages.show', line.slug);
    const Icon = line.type === 'test' ? FlaskConical : Package;

    return (
        <li className="flex gap-3 p-4">
            <Icon className="mt-0.5 size-5 shrink-0 text-muted" strokeWidth={1.75} aria-hidden="true" />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                    {line.available ? (
                        <TextLink href={href}>{line.name}</TextLink>
                    ) : (
                        <span className="font-medium text-muted">{line.name}</span>
                    )}
                    {line.available && (
                        <Price amount={line.price_amount} originalAmount={line.original_amount ?? undefined} />
                    )}
                </div>
                <p className="text-sm text-muted">
                    {line.type === 'package' ? `Package · ${line.tests_count} tests` : 'Test'}
                </p>

                {!line.available && (
                    <Badge tone="warning" className="mt-2">
                        No longer available
                    </Badge>
                )}
                {line.included_in.length > 0 && (
                    <p className="mt-2 flex items-start gap-1.5 text-sm text-warning-600">
                        <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        Also included in {line.included_in.join(', ')}.
                    </p>
                )}

                <Button
                    variant="ghost"
                    size="sm"
                    className="mt-1 -ml-3 text-danger-600"
                    processing={removing}
                    aria-label={`Remove ${line.name}`}
                    onClick={() =>
                        router.delete(route('cart.items.destroy', line.id), {
                            preserveScroll: true,
                            onStart: () => setRemoving(true),
                            onFinish: () => setRemoving(false),
                        })
                    }
                >
                    Remove
                </Button>
            </div>
        </li>
    );
}

export default function Cart({ lines, totalAmount }: CartProps) {
    const { auth } = usePage<SharedData>().props;
    const unavailable = lines.filter((line) => !line.available).length;

    return (
        <AppLayout>
            <Head title="Your cart" />

            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <h1 className="text-2xl font-semibold md:text-3xl">Your cart</h1>

                {lines.length === 0 ? (
                    <div className="mt-8 flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                        <ShoppingCart className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                        <p className="mt-3 text-sm text-muted">Your cart is empty.</p>
                        <Link href={route('catalogue.index')} className={`${primaryLinkClass} mt-4 w-auto`}>
                            Browse tests
                        </Link>
                    </div>
                ) : (
                    <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
                        <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                            {lines.map((line) => (
                                <CartLineRow key={line.id} line={line} />
                            ))}
                        </ul>

                        <aside
                            aria-label="Summary"
                            className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6"
                        >
                            <div className="flex items-baseline justify-between gap-4">
                                <span className="text-base font-semibold">Total</span>
                                <Price amount={totalAmount} className="text-lg" />
                            </div>
                            {unavailable > 0 && (
                                <p className="text-sm text-muted">
                                    {unavailable === 1 ? '1 item is' : `${unavailable} items are`} no longer available
                                    and not included.
                                </p>
                            )}
                            {/* Collection fee, tax and discounts are added at checkout once PROJECT.md §4 settles them. */}
                            <p className="text-sm text-muted">Collection details and payment come next.</p>

                            {auth.user ? (
                                <Link href={route('checkout.show')} className={primaryLinkClass}>
                                    Continue to checkout
                                </Link>
                            ) : (
                                <a href={route('cart.login')} className={primaryLinkClass}>
                                    Log in to check out
                                </a>
                            )}
                            <TextLink href={route('catalogue.index')} className="block text-center text-sm">
                                Continue browsing
                            </TextLink>
                        </aside>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
