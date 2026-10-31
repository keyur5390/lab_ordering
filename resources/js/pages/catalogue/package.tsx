import { CategoryLinks } from '@/components/catalogue/category-links';
import { DetailRow } from '@/components/catalogue/detail-row';
import { AddToCartButton } from '@/components/catalogue/add-to-cart-button';
import { Price } from '@/components/catalogue/price';
import { Badge } from '@/components/ui/badge';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import { formatTurnaround } from '@/lib/format';
import type { LabPackage } from '@/types';
import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';

export default function PackageDetail({ package: pkg }: { package: LabPackage }) {
    const tests = pkg.tests ?? [];
    const withPreparation = tests.filter((test) => test.preparation);

    return (
        <AppLayout>
            <Head title={pkg.name} />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('catalogue.index')} className="text-sm">
                    ← All tests & packages
                </TextLink>

                <h1 className="mt-4 text-2xl font-semibold md:text-3xl">{pkg.name}</h1>
                <div className="mt-3 flex flex-wrap items-center gap-4">
                    <Price amount={pkg.price_amount} originalAmount={pkg.tests_total_amount} className="text-xl" />
                    <AddToCartButton type="package" id={pkg.id} className="min-w-32" />
                </div>
                {pkg.description && <p className="mt-4 text-base text-muted">{pkg.description}</p>}
                <div className="mt-4">
                    <CategoryLinks categories={pkg.categories} />
                </div>

                <dl className="mt-6 divide-y divide-border rounded-card border border-border bg-surface px-4 md:px-6">
                    <DetailRow label="Tests included">{tests.length}</DetailRow>
                    <DetailRow label="Reports in">{formatTurnaround(pkg.turnaround_hours)}</DetailRow>
                    <DetailRow label="Package code">{pkg.code}</DetailRow>
                </dl>

                {withPreparation.length > 0 && (
                    <section className="mt-6 rounded-card border border-warning-600/30 bg-surface p-4 md:p-6">
                        <h2 className="flex items-center gap-2 text-base font-semibold text-warning-600">
                            <Info className="size-5" strokeWidth={1.75} aria-hidden="true" />
                            Preparation
                        </h2>
                        <ul className="mt-3 space-y-2 text-sm">
                            {withPreparation.map((test) => (
                                <li key={test.id}>
                                    <span className="font-medium">{test.name}:</span>{' '}
                                    <span className="whitespace-pre-line">{test.preparation}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section aria-labelledby="included-heading" className="mt-8">
                    <h2 id="included-heading" className="text-lg font-semibold">
                        What's included
                    </h2>
                    <ul className="mt-4 divide-y divide-border rounded-card border border-border bg-surface">
                        {tests.map((test) => (
                            <li key={test.id} className="flex flex-wrap items-center justify-between gap-2 p-4">
                                <div>
                                    {test.is_active ? (
                                        <TextLink href={route('catalogue.tests.show', test.slug)}>{test.name}</TextLink>
                                    ) : (
                                        <span className="font-medium">{test.name}</span>
                                    )}
                                    <p className="text-sm text-muted">{test.sample_type}</p>
                                </div>
                                <Badge>{formatTurnaround(test.turnaround_hours)}</Badge>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </AppLayout>
    );
}
