import { Alert } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronRight, ClipboardList, FileText, MapPin, ShieldCheck, Users, type LucideIcon } from 'lucide-react';
import { t, tx } from '@/lib/i18n';

interface AccountProps {
    /** Present when SMS is switched on for the site. */
    sms: { opted_out: boolean; email: boolean; phone: string | null } | null;
    /** Present when WhatsApp is switched on for the site. */
    whatsapp: { opted_in: boolean; phone: string | null } | null;
}

function WhatsAppPreference({ whatsapp }: { whatsapp: NonNullable<AccountProps['whatsapp']> }) {
    const [saving, setSaving] = useState(false);

    return (
        <section
            aria-labelledby="whatsapp-heading"
            className="mt-4 rounded-card border border-border bg-surface p-4 md:p-6"
        >
            <h2 id="whatsapp-heading" className="font-medium">
                {t('WhatsApp')}
            </h2>
            <label className="mt-3 flex min-h-11 items-center gap-3 text-sm">
                <Checkbox
                    checked={whatsapp.opted_in}
                    disabled={saving}
                    onChange={(e) =>
                        router.put(
                            route('account.notifications.update'),
                            { whatsapp: e.target.checked },
                            {
                                preserveScroll: true,
                                onStart: () => setSaving(true),
                                onFinish: () => setSaving(false),
                            },
                        )
                    }
                />
                {t('Send me order updates on WhatsApp')}
            </label>
            <p className="text-sm text-muted">
                {whatsapp.phone ? (
                    <>{t('Messages go to :phone.', { phone: whatsapp.phone })}</>
                ) : (
                    tx('Add a mobile number in :settings to get WhatsApp messages.', {
                        settings: <TextLink href={route('account.settings')}>{t('account settings')}</TextLink>,
                    })
                )}
            </p>
        </section>
    );
}

function SmsPreference({ sms }: { sms: NonNullable<AccountProps['sms']> }) {
    const [saving, setSaving] = useState(false);

    return (
        <section
            aria-labelledby="notifications-heading"
            className="mt-4 rounded-card border border-border bg-surface p-4 md:p-6"
        >
            <h2 id="notifications-heading" className="font-medium">
                {t('Notifications')}
            </h2>
            {sms.email && <p className="mt-1 text-sm text-muted">{t('We email you about every order.')}</p>}
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
                {sms.email ? t('Also text me order updates') : t('Text me order updates')}
            </label>
            <p className="text-sm text-muted">
                {sms.phone ? (
                    <>{t('Texts go to :phone, the phone on your own profile.', { phone: sms.phone })}</>
                ) : (
                    tx('To get texts, add a mobile number with the country code (e.g. +91…) to :profile.', {
                        profile: <TextLink href={route('patients.index')}>{t('your own profile')}</TextLink>,
                    })
                )}
            </p>
        </section>
    );
}

export default function Account({ sms, whatsapp }: AccountProps) {
    const { auth, status } = usePage<SharedData>().props;

    const links: { href: string; label: string; description: string; icon: LucideIcon }[] = [
        {
            href: route('orders.index'),
            label: t('Orders'),
            description: t('Your bookings, collection times and changes'),
            icon: ClipboardList,
        },
        {
            href: route('reports.index'),
            label: t('Reports'),
            description: t('Your test reports from every order'),
            icon: FileText,
        },
        {
            href: route('patients.index'),
            label: t('Patients'),
            description: t('You and the family members you book tests for'),
            icon: Users,
        },
        {
            href: route('addresses.index'),
            label: t('Addresses'),
            description: t('Where we collect samples from you'),
            icon: MapPin,
        },
        {
            href: route('account.privacy'),
            label: t('Privacy and data'),
            description: t('Download your data or delete your account'),
            icon: ShieldCheck,
        },
    ];

    return (
        <AppLayout>
            <Head title={t('Your account')} />

            <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <h1 className="text-2xl font-semibold md:text-3xl">{t('Your account')}</h1>

                <div className="mt-6 rounded-card border border-border bg-surface p-4 md:p-6">
                    <p className="text-sm text-muted">{t('Signed in as')}</p>
                    <p className="mt-1 font-medium">{auth.user?.name}</p>
                    <p className="text-sm text-muted">{auth.user?.email}</p>
                    <TextLink href={route('account.settings')} className="mt-2 inline-block text-sm">
                        {t('Change your details or password')}
                    </TextLink>
                </div>

                {status && (
                    <Alert tone="success" className="mt-4">
                        {status}
                    </Alert>
                )}

                {sms && <SmsPreference sms={sms} />}
                {whatsapp && <WhatsAppPreference whatsapp={whatsapp} />}

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
