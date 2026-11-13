import { Seo } from '@/components/seo';
import { PackageCard } from '@/components/catalogue/package-card';
import { TestCard } from '@/components/catalogue/test-card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Category, LabPackage, LabTest } from '@/types';
import { Link, router } from '@inertiajs/react';
import { FlaskConical, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { plural, t } from '@/lib/i18n';

type Sort = 'name' | 'price_asc' | 'price_desc';

interface Filters {
    q: string;
    category: string | null;
    type: 'tests' | 'packages' | null;
    no_fasting: boolean;
    sort: Sort;
}

interface CatalogueProps {
    packages: LabPackage[];
    tests: LabTest[];
    categories: Category[];
    filters: Filters;
}

/** Query string for the filters, leaving out defaults so links stay short. */
function params(filters: Filters): Record<string, string> {
    const out: Record<string, string> = {};
    if (filters.q.trim()) out.q = filters.q.trim();
    if (filters.category) out.category = filters.category;
    if (filters.type) out.type = filters.type;
    if (filters.no_fasting) out.no_fasting = '1';
    if (filters.sort !== 'name') out.sort = filters.sort;

    return out;
}

const chipClass = 'inline-flex h-11 shrink-0 items-center rounded-full border px-4 text-sm font-medium sm:h-9';

export default function Catalogue({ packages, tests, categories, filters }: CatalogueProps) {
    const [q, setQ] = useState(filters.q);
    const first = useRef(true);

    const apply = (next: Partial<Filters>) =>
        router.get(route('catalogue.index'), params({ ...filters, q, ...next }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    // Search after a short pause in typing.
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => apply({ q }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const filtered = Object.keys(params(filters)).length > 0;
    const empty = packages.length === 0 && tests.length === 0;

    return (
        <AppLayout>
            <Seo
                title={t('Tests & packages')}
                description={t('Book lab tests and health packages online, with prices, preparation and report times.')}
            />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <h1 className="text-2xl font-semibold md:text-3xl">{t('Tests & packages')}</h1>

                <div className="mt-6 space-y-4">
                    <div className="relative max-w-xl">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted"
                            aria-hidden="true"
                        />
                        <Input
                            type="search"
                            aria-label={t('Search tests and packages')}
                            placeholder={t('Search by test name or code')}
                            className="pl-9"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                    </div>

                    {categories.length > 0 && (
                        <nav
                            aria-label={t('Categories')}
                            className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0"
                        >
                            {[{ name: t('All'), slug: null as string | null }, ...categories].map((category) => {
                                const active = filters.category === category.slug;

                                return (
                                    <Link
                                        key={category.slug ?? 'all'}
                                        href={route(
                                            'catalogue.index',
                                            params({ ...filters, q, category: category.slug }),
                                        )}
                                        preserveScroll
                                        preserveState
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            chipClass,
                                            active
                                                ? 'border-primary-600 bg-primary-50 text-primary-700'
                                                : 'border-border bg-surface hover:bg-canvas',
                                        )}
                                    >
                                        {category.name}
                                    </Link>
                                );
                            })}
                        </nav>
                    )}

                    <div className="grid grid-cols-2 items-end gap-3 sm:flex sm:flex-wrap sm:gap-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="type-filter">{t('Show')}</Label>
                            <Select
                                id="type-filter"
                                className="w-full sm:w-52"
                                value={filters.type ?? ''}
                                onChange={(e) => apply({ type: (e.target.value || null) as Filters['type'] })}
                            >
                                <option value="">{t('Everything')}</option>
                                <option value="packages">{t('Packages')}</option>
                                <option value="tests">{t('Single tests')}</option>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="sort">{t('Sort by')}</Label>
                            <Select
                                id="sort"
                                className="w-full sm:w-52"
                                value={filters.sort}
                                onChange={(e) => apply({ sort: e.target.value as Sort })}
                            >
                                <option value="name">{t('Name, A–Z')}</option>
                                <option value="price_asc">{t('Lowest price')}</option>
                                <option value="price_desc">{t('Highest price')}</option>
                            </Select>
                        </div>
                        <label className="col-span-2 flex min-h-11 items-center gap-2 text-sm">
                            <Checkbox
                                checked={filters.no_fasting}
                                onChange={(e) => apply({ no_fasting: e.target.checked })}
                            />
                            {t('No fasting or preparation')}
                        </label>
                    </div>
                </div>

                <p className="mt-6 text-sm text-muted" role="status">
                    {plural(packages.length + tests.length, ':count result', ':count results')}
                </p>

                {empty && (
                    <div className="mt-4 flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                        <FlaskConical className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                        <p className="mt-3 text-sm text-muted">
                            {filtered
                                ? t('Nothing matches your search.')
                                : t('No tests are available yet. Please check back soon.')}
                        </p>
                        {filtered && (
                            <Link
                                href={route('catalogue.index')}
                                onClick={() => setQ('')}
                                className="mt-3 text-sm font-medium text-primary-700 hover:underline"
                            >
                                {t('Clear search and filters')}
                            </Link>
                        )}
                    </div>
                )}

                {packages.length > 0 && (
                    <section aria-labelledby="packages-heading" className="mt-6">
                        <h2 id="packages-heading" className="text-lg font-semibold">
                            {t('Packages')}
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
                            {t('Individual tests')}
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
