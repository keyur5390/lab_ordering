import { PackageCard } from '@/components/catalogue/package-card';
import { FaqList, type FaqItem } from '@/components/faq-list';
import { Alert } from '@/components/ui/alert';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { LabPackage, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { t } from '@/lib/i18n';

interface HomeProps {
    hero: { title: string; text: string | null; button: string };
    featured: LabPackage[];
    faqs: FaqItem[];
}

export default function Home({ hero, featured, faqs }: HomeProps) {
    const { status } = usePage<SharedData>().props;

    return (
        <AppLayout>
            <Head title={t('Home')} />

            <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 md:py-20 lg:px-8">
                {status && (
                    <Alert tone="success" className="mb-6">
                        {status}
                    </Alert>
                )}
                <h1 className="max-w-3xl text-2xl font-semibold md:text-4xl">{hero.title}</h1>
                {hero.text && <p className="mt-3 max-w-2xl text-base text-muted md:text-lg">{hero.text}</p>}
                <Link
                    href={route('catalogue.index')}
                    className="mt-6 inline-flex h-11 items-center rounded-control bg-primary-600 px-6 text-sm font-medium text-white hover:bg-primary-700"
                >
                    {hero.button}
                </Link>
            </section>

            {featured.length > 0 && (
                <section
                    aria-labelledby="featured-heading"
                    className="mx-auto max-w-7xl px-4 pb-12 sm:px-6 md:pb-16 lg:px-8"
                >
                    <div className="flex items-baseline justify-between gap-4">
                        <h2 id="featured-heading" className="text-xl font-semibold">
                            {t('Popular packages')}
                        </h2>
                        <TextLink href={route('catalogue.index', { type: 'packages' })} className="text-sm">
                            {t('All packages')}
                        </TextLink>
                    </div>
                    <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {featured.map((pkg) => (
                            <li key={pkg.id}>
                                <PackageCard pkg={pkg} />
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {faqs.length > 0 && (
                <section
                    aria-labelledby="faq-heading"
                    className="mx-auto max-w-3xl px-4 pb-12 sm:px-6 md:pb-20 lg:px-8"
                >
                    <h2 id="faq-heading" className="text-xl font-semibold">
                        {t('Frequently asked questions')}
                    </h2>
                    <div className="mt-4">
                        <FaqList faqs={faqs} />
                    </div>
                </section>
            )}
        </AppLayout>
    );
}
