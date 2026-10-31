import { Alert } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronRight, ClipboardList, FileText, MapPin, Users, type LucideIcon } from 'lucide-react';

interface AccountProps {
    /** Present when SMS is switched on for the site. */
    sms: { opted_out: boolean; email: boolean; phone: string | null } | null;
}

function SmsPreference({ sms }: { sms: NonNullable<AccountProps['sms']> }) {
    const [saving, setSaving] = useState(false);

    return (
        <section
            aria-labelledby="notifications-heading"
            className="mt-4 rounded-card border border-border bg-surface p-4 md:p-6"
        >
            <h2 id="notifications-heading" className="font-medium">
                Notifications
            </h2>
            {sms.email && <p className="mt-1 text-sm text-muted">We email you about every order.</p>}
            <label className="mt-3 flex min-h-11 items-center gap-3 text-sm">
                <Checkbox
                    checked={!sms.opted_out}
                    disabled={saving}
                    onChange={(e) =>
                        router.put(
                            route('account.notifications.update'),
                            { sms: e.target.checked },
                            {
                                preserveScroll: true,
                                onStart: () => setSaving(true),
                                onFinish: () => setSaving(false),
                            },
                        )
                    }
                />
                {sms.email ? 'Also text me order updates' : 'Text me order updates'}
            </label>
            <p className="text-sm text-muted">
                {sms.phone ? (
                    <>Texts go to {sms.phone}, the phone on your own profile.</>
                ) : (
                    <>
                        To get texts, add a mobile number with the country code (e.g. +91…) to{' '}
                        <TextLink href={route('patients.index')}>your own profile</TextLink>.
                    </>
                )}
            </p>
        </section>
    );
}

export default function Account({ sms }: AccountProps) {
    const { auth, status } = usePage<SharedData>().props;

    const links: { href: string; label: string; description: string; icon: LucideIcon }[] = [
        {
            href: route('orders.index'),
            label: 'Orders',
            description: 'Your bookings, collection times and changes',
            icon: ClipboardList,
        },
        {
            href: route('reports.index'),
            label: 'Reports',
            description: 'Your test reports from every order',
            icon: FileText,
        },
        {
            href: route('patients.index'),
            label: 'Patients',
            description: 'You and the family members you book tests for',
            icon: Users,
        },
        {
            href: route('addresses.index'),
            label: 'Addresses',
            description: 'Where we collect samples from you',
            icon: MapPin,
        },
    ];

    return (
        <AppLayout>
            <Head title="Your account" />

            <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <h1 className="text-2xl font-semibold md:text-3xl">Your account</h1>

                <div className="mt-6 rounded-card border border-border bg-surface p-4 md:p-6">
                    <p className="text-sm text-muted">Signed in as</p>
                    <p className="mt-1 font-medium">{auth.user?.name}</p>
                    <p className="text-sm text-muted">{auth.user?.email}</p>
                </div>

                {status && (
                    <Alert tone="success" className="mt-4">
                        {status}
                    </Alert>
                )}

                {sms && <SmsPreference sms={sms} />}

                <ul className="mt-4 space-y-3">
                    {links.map(({ href, label, description, icon: Icon }) => (
                        <li key={href}>
                            <Link
                                href={href}
                                className="flex items-center gap-3 rounded-card border border-border bg-surface p-4 hover:bg-canvas md:p-6"
                            >
                                <Icon className="size-5 text-muted" strokeWidth={1.75} aria-hidden="true" />
                                <span className="flex-1">
                                    <span className="block font-medium">{label}</span>
                                    <span className="block text-sm text-muted">{description}</span>
                                </span>
                                <ChevronRight className="size-5 text-muted" aria-hidden="true" />
                            </Link>
                        </li>
                    ))}
                </ul>
            </section>
        </AppLayout>
    );
}
