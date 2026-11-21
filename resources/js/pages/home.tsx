import { FaqList, type FaqItem } from '@/components/faq-list';
import { PackageCard } from '@/components/catalogue/package-card';
import { Seo } from '@/components/seo';
import { TestCard } from '@/components/catalogue/test-card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import type { Currency, LabPackage, LabTest, ServiceArea } from '@/types';
import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    Clock3,
    Droplet,
    FlaskConical,
    HeartPulse,
    MapPin,
    ShieldCheck,
    Sparkles,
    Stethoscope,
    Truck,
    type LucideIcon,
} from 'lucide-react';

interface HomeProps {
    hero: { title: string; text: string | null; button: string };
    featured: LabPackage[];
    faqs: FaqItem[];
    featured_tests: LabTest[];
    featured_packages: LabPackage[];
    stats: {
        tests: number;
        packages: number;
        service_areas: number;
        branches: number;
    };
    service_areas: ServiceArea[];
    currency: Currency | null;
}

function SectionHeading({
    eyebrow,
    title,
    description,
    href,
    linkText,
}: {
    eyebrow: string;
    title: string;
    description: string;
    href: string;
    linkText: string;
}) {
    return (
        <div className="mb-8 flex flex-wrap items-end justify-between gap-5">
            <div>
                <p className="mb-2 text-xs font-bold tracking-[0.16em] text-primary-700 uppercase">{eyebrow}</p>
                <h2 className="text-2xl font-bold tracking-tight text-text md:text-3xl">{title}</h2>
                <p className="mt-2 text-sm text-muted sm:text-base">{description}</p>
            </div>
            <Link
                href={href}
                className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-primary-700 transition-colors hover:text-primary-900"
            >
                {linkText}
                <ArrowRight className="size-4" aria-hidden="true" />
            </Link>
        </div>
    );
}

export default function Home({
    hero,
    featured,
    faqs,
    featured_tests,
    featured_packages,
    stats,
    service_areas,
    currency,
}: HomeProps) {
    const featuredPackages = featured.length > 0 ? featured : featured_packages;
    const statCards: { label: string; value: number; icon: LucideIcon }[] = [
        { label: 'Lab tests', value: stats.tests, icon: Droplet },
        { label: 'Health packages', value: stats.packages, icon: FlaskConical },
        { label: 'Service areas', value: stats.service_areas, icon: MapPin },
        { label: 'Lab branches', value: stats.branches, icon: Stethoscope },
    ];

    const steps: { title: string; description: string; icon: LucideIcon }[] = [
        {
            title: 'Choose your tests',
            description: 'Find the right test or health package with clear pricing and preparation details.',
            icon: FlaskConical,
        },
        {
            title: 'Pick a convenient time',
            description: 'Book a home collection or choose a visit to one of our labs.',
            icon: Clock3,
        },
        {
            title: 'We collect your sample',
            description: 'A trained professional arrives at your chosen place and time.',
            icon: Truck,
        },
        {
            title: 'Get results online',
            description: 'Your reports are securely available from your account when ready.',
            icon: ShieldCheck,
        },
    ];

    return (
        <AppLayout>
            <Seo title="Home" description={hero.text ?? hero.title} />

            <section className="relative isolate overflow-hidden bg-canvas">
                <div
                    className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_80%_0%,rgba(211,235,228,0.8),transparent_38%),radial-gradient(ellipse_at_0%_90%,rgba(248,238,220,0.55),transparent_34%)]"
                    aria-hidden="true"
                />
                <div className="mx-auto grid max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-24">
                    <div className="max-w-2xl">
                        <Badge
                            tone="info"
                            className="mb-6 gap-2 rounded-full border-primary-100 bg-white/80 px-3 py-1.5 text-primary-700"
                        >
                            <Sparkles className="size-3.5" aria-hidden="true" />A better way to take care of your health
                        </Badge>

                        <h1 className="text-4xl leading-[1.08] font-bold tracking-[-0.04em] text-text sm:text-5xl lg:text-6xl">
                            {hero.title}
                        </h1>
                        {hero.text && (
                            <p className="mt-6 max-w-xl text-base leading-7 text-muted sm:text-lg sm:leading-8">
                                {hero.text}
                            </p>
                        )}

                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link
                                href={route('catalogue.index')}
                                className="inline-flex h-12 items-center justify-center gap-2 rounded-control bg-primary-600 px-6 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(8,120,109,0.22)] transition-all hover:-translate-y-0.5 hover:bg-primary-700"
                            >
                                {hero.button}
                                <ArrowRight className="size-4" aria-hidden="true" />
                            </Link>
                            <Link
                                href={route('catalogue.index', { type: 'packages' })}
                                className="inline-flex h-12 items-center justify-center rounded-control border border-border bg-white/80 px-6 text-sm font-semibold text-text transition-colors hover:border-primary-100 hover:bg-white"
                            >
                                Browse health packages
                            </Link>
                        </div>

                        <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-muted">
                            <span className="inline-flex items-center gap-2">
                                <Check className="size-4 text-primary-600" aria-hidden="true" />
                                Convenient home collection
                            </span>
                            <span className="inline-flex items-center gap-2">
                                <Check className="size-4 text-primary-600" aria-hidden="true" />
                                Secure online reports
                            </span>
                        </div>
                    </div>

                    <div className="relative mx-auto w-full max-w-[34rem] lg:ml-auto" aria-hidden="true">
                        <div className="absolute -top-7 right-8 size-28 rounded-full bg-accent-100/80 blur-2xl" />
                        <div className="absolute -bottom-8 -left-7 size-36 rounded-full bg-primary-100/90 blur-2xl" />
                        <div className="relative overflow-hidden rounded-[1.75rem] bg-primary-900 p-6 text-white shadow-[0_28px_70px_rgba(18,60,54,0.24)] sm:p-8">
                            <div className="absolute -top-20 -right-16 size-64 rounded-full border border-white/10" />
                            <div className="absolute -top-12 -right-8 size-48 rounded-full border border-white/10" />
                            <div className="relative">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-semibold tracking-[0.16em] text-white/60 uppercase">
                                            Care that comes to you
                                        </p>
                                        <h2 className="mt-3 max-w-xs text-2xl leading-tight font-semibold tracking-tight sm:text-3xl">
                                            Your health, on your schedule.
                                        </h2>
                                    </div>
                                    <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-white ring-1 ring-white/15">
                                        <HeartPulse className="size-6" strokeWidth={1.7} />
                                    </span>
                                </div>

                                <div className="mt-8 rounded-2xl bg-white p-5 text-text shadow-xl sm:p-6">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-11 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                                                <FlaskConical className="size-5" />
                                            </span>
                                            <div>
                                                <p className="font-semibold">A simpler testing experience</p>
                                                <p className="mt-0.5 text-xs text-muted">From booking to results</p>
                                            </div>
                                        </div>
                                        <ShieldCheck className="size-5 shrink-0 text-primary-600" />
                                    </div>

                                    <div className="mt-5 grid grid-cols-3 gap-2 border-t border-border pt-5">
                                        {[
                                            { title: 'Choose', detail: 'Find a test' },
                                            { title: 'Schedule', detail: 'Pick a time' },
                                            { title: 'Receive', detail: 'View results' },
                                        ].map((item, index) => (
                                            <div key={item.title} className="relative">
                                                <span className="mb-2 flex size-7 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700">
                                                    0{index + 1}
                                                </span>
                                                <p className="text-sm font-semibold">{item.title}</p>
                                                <p className="mt-1 text-[11px] text-muted sm:text-xs">{item.detail}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <p className="mt-5 flex items-center justify-center gap-2 text-xs font-medium text-white/65">
                                    <ShieldCheck className="size-4" />
                                    Thoughtful care, with privacy built in
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section aria-label="Our network" className="border-y border-border/80 bg-white">
                <div className="mx-auto grid max-w-7xl grid-cols-2 gap-4 px-4 py-7 sm:px-6 sm:py-8 lg:grid-cols-4 lg:px-8">
                    {statCards.map(({ label, value, icon: Icon }) => (
                        <div key={label} className="flex items-center gap-3 px-2 sm:justify-center sm:gap-4">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                                <Icon className="size-5" strokeWidth={1.8} aria-hidden="true" />
                            </span>
                            <span>
                                <span className="block text-xl font-bold tracking-tight text-text tabular-nums sm:text-2xl">
                                    {value.toLocaleString()}
                                </span>
                                <span className="block text-xs font-medium text-muted sm:text-sm">{label}</span>
                            </span>
                        </div>
                    ))}
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div className="mx-auto mb-11 max-w-2xl text-center">
                    <p className="mb-2 text-xs font-bold tracking-[0.16em] text-primary-700 uppercase">
                        Simple from start to finish
                    </p>
                    <h2 className="text-2xl font-bold tracking-tight text-text sm:text-3xl">
                        Care that fits into your life
                    </h2>
                    <p className="mt-3 text-sm leading-6 text-muted sm:text-base">
                        Everything you need for a smooth, dependable lab testing experience.
                    </p>
                </div>

                <ol className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map(({ title, description, icon: Icon }, index) => (
                        <li
                            key={title}
                            className="rounded-card border border-border/80 bg-white p-5 shadow-[0_8px_24px_rgba(23,45,40,0.035)] sm:p-6"
                        >
                            <div className="flex items-center justify-between">
                                <span className="flex size-11 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                                    <Icon className="size-5" strokeWidth={1.8} aria-hidden="true" />
                                </span>
                                <span className="text-sm font-bold tracking-wider text-primary-700/50">
                                    0{index + 1}
                                </span>
                            </div>
                            <h3 className="mt-5 font-semibold text-text">{title}</h3>
                            <p className="mt-2 text-sm leading-6 text-muted">{description}</p>
                        </li>
                    ))}
                </ol>
            </section>

            {featuredPackages.length > 0 && (
                <section className="border-y border-border/70 bg-primary-50/70 py-16 sm:py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <SectionHeading
                            eyebrow="Curated for you"
                            title="Popular health packages"
                            description="Thoughtfully bundled tests for a more complete picture of your health."
                            href={route('catalogue.index', { type: 'packages' })}
                            linkText="Explore all packages"
                        />
                        <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {featuredPackages.map((pkg) => (
                                <li key={pkg.id}>
                                    <PackageCard pkg={pkg} />
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>
            )}

            {featured_tests.length > 0 && (
                <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <SectionHeading
                        eyebrow="A good place to start"
                        title="Popular lab tests"
                        description="Expert testing, clear information, and convenient appointments."
                        href={route('catalogue.index', { type: 'tests' })}
                        linkText="View all tests"
                    />
                    <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {featured_tests.map((test) => (
                            <li key={test.id}>
                                <TestCard test={test} />
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {service_areas.length > 0 && (
                <section className="border-t border-border/70 bg-white py-16 sm:py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <SectionHeading
                            eyebrow="At-home collection"
                            title="Care, closer to home"
                            description="Check the service areas where our collection team can visit."
                            href={route('catalogue.index')}
                            linkText="Browse available tests"
                        />
                        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {service_areas.map((area) => (
                                <li
                                    key={area.id}
                                    className="rounded-card border border-border/80 bg-surface p-5 shadow-[0_8px_24px_rgba(23,45,40,0.035)] transition-shadow hover:shadow-[0_14px_30px_rgba(23,45,40,0.08)]"
                                >
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                                        <MapPin className="size-5" aria-hidden="true" />
                                    </span>
                                    <h3 className="mt-4 font-semibold text-text">{area.name}</h3>
                                    <p className="mt-1 text-sm text-muted">Home collection available</p>
                                    {currency && area.collection_fee_amount > 0 && (
                                        <p className="mt-4 border-t border-border pt-3 text-sm font-medium text-text">
                                            Collection fee{' '}
                                            <span className="float-right text-muted">
                                                {formatMoney(area.collection_fee_amount, currency)}
                                            </span>
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>
            )}

            {faqs.length > 0 && (
                <section aria-labelledby="faq-heading" className="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <p className="mb-2 text-xs font-bold tracking-[0.16em] text-primary-700 uppercase">
                        Helpful answers
                    </p>
                    <h2 id="faq-heading" className="text-2xl font-bold tracking-tight text-text sm:text-3xl">
                        Frequently asked questions
                    </h2>
                    <div className="mt-6">
                        <FaqList faqs={faqs} />
                    </div>
                </section>
            )}

            <section className="px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div className="relative mx-auto max-w-7xl overflow-hidden rounded-[1.75rem] bg-primary-900 px-6 py-12 text-center text-white shadow-[0_24px_60px_rgba(18,60,54,0.16)] sm:px-12 sm:py-16">
                    <div className="pointer-events-none absolute -top-32 left-1/2 size-80 -translate-x-1/2 rounded-full border border-white/10" />
                    <div className="pointer-events-none absolute -top-20 left-1/2 size-56 -translate-x-1/2 rounded-full border border-white/10" />
                    <div className="relative mx-auto max-w-2xl">
                        <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-white/10 text-white ring-1 ring-white/15">
                            <HeartPulse className="size-6" aria-hidden="true" />
                        </span>
                        <p className="mt-5 text-xs font-bold tracking-[0.16em] text-white/60 uppercase">
                            Start with one simple step
                        </p>
                        <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                            Make time for your health.
                        </h2>
                        <p className="mx-auto mt-4 max-w-xl text-sm leading-6 text-white/70 sm:text-base">
                            Find a test, choose a time that works, and let us take care of the rest.
                        </p>
                        <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                            <Link
                                href={route('catalogue.index')}
                                className="inline-flex h-12 items-center justify-center gap-2 rounded-control bg-white px-6 text-sm font-semibold text-primary-900 transition-colors hover:bg-primary-50"
                            >
                                Explore tests
                                <ArrowRight className="size-4" aria-hidden="true" />
                            </Link>
                            <Link
                                href={route('register')}
                                className="inline-flex h-12 items-center justify-center rounded-control border border-white/25 px-6 text-sm font-semibold text-white transition-colors hover:bg-white/10"
                            >
                                Create an account
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </AppLayout>
    );
}
