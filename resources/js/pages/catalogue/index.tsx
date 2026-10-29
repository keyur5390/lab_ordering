import { PackageCard } from '@/components/catalogue/package-card';
import { TestCard } from '@/components/catalogue/test-card';
import AppLayout from '@/layouts/app-layout';
import type { LabPackage, LabTest } from '@/types';
import { Head } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';

interface CatalogueProps {
    packages: LabPackage[];
    tests: LabTest[];
}

export default function Catalogue({ packages, tests }: CatalogueProps) {
    const empty = packages.length === 0 && tests.length === 0;

    return (
        <AppLayout>
            <Head title="Tests & packages" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <h1 className="text-2xl font-semibold md:text-3xl">Tests & packages</h1>

                {empty && (
                    <div className="mt-8 flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                        <FlaskConical className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                        <p className="mt-3 text-sm text-muted">No tests are available yet. Please check back soon.</p>
                    </div>
                )}

                {packages.length > 0 && (
                    <section aria-labelledby="packages-heading" className="mt-8">
                        <h2 id="packages-heading" className="text-lg font-semibold">
                            Packages
                        </h2>
                        <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {packages.map((pkg) => (
                                <li key={pkg.id}>
                                    <PackageCard pkg={pkg} />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {tests.length > 0 && (
                    <section aria-labelledby="tests-heading" className="mt-10">
                        <h2 id="tests-heading" className="text-lg font-semibold">
                            Individual tests
                        </h2>
                        <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {tests.map((test) => (
                                <li key={test.id}>
                                    <TestCard test={test} />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
