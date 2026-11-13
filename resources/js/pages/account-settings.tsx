import { FormField } from '@/components/form-field';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { t, tx } from '@/lib/i18n';

function Section({ id, title, children }: { id: string; title: string; children: ReactNode }) {
    return (
        <section aria-labelledby={id} className="rounded-card border border-border bg-surface p-4 md:p-6">
            <h2 id={id} className="text-base font-semibold">
                {title}
            </h2>
            <div className="mt-4">{children}</div>
        </section>
    );
}

function ProfileForm() {
    const { auth, status } = usePage<SharedData>().props;
    const user = auth.user!;
    const { data, setData, put, processing, errors, isDirty } = useForm({ name: user.name, email: user.email });
    const emailChanged = data.email.trim().toLowerCase() !== user.email;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('user-profile-information.update'), { errorBag: 'updateProfileInformation', preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-4">
            {status === 'profile-information-updated' && <Alert tone="success">{t('Your details are saved.')}</Alert>}
            {!user.email_verified && (
                <Alert tone="warning">
                    {t("Please confirm :email: we've emailed you a link.", { email: user.email })}{' '}
                    <TextLink href={route('verification.notice')}>{t('Resend the link')}</TextLink>
                </Alert>
            )}
            <FormField
                id="name"
                label={t('Name')}
                autoComplete="name"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                error={errors.name}
            />
            <FormField
                id="email"
                label={t('Email')}
                type="email"
                autoComplete="email"
                hint={emailChanged ? t("We'll email the new address a link to confirm it.") : undefined}
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                error={errors.email}
            />
            <Button type="submit" processing={processing} disabled={!isDirty}>
                {t('Save details')}
            </Button>
        </form>
    );
}

function PasswordForm() {
    const { status } = usePage<SharedData>().props;
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('user-password.update'), {
            errorBag: 'updatePassword',
            preserveScroll: true,
            onFinish: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-4">
            {status === 'password-updated' && <Alert tone="success">{t('Your password is changed.')}</Alert>}
            <FormField
                id="current_password"
                label={t('Current password')}
                type="password"
                autoComplete="current-password"
                value={data.current_password}
                onChange={(e) => setData('current_password', e.target.value)}
                error={errors.current_password}
            />
            <FormField
                id="password"
                label={t('New password')}
                type="password"
                autoComplete="new-password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                error={errors.password}
            />
            <FormField
                id="password_confirmation"
                label={t('Confirm new password')}
                type="password"
                autoComplete="new-password"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                error={errors.password_confirmation}
            />
            <Button type="submit" processing={processing}>
                {t('Change password')}
            </Button>
        </form>
    );
}

interface PhoneSettings {
    /** Whether texts can be sent (an SMS driver is on). */
    available: boolean;
    /** The confirmed number. */
    number: string | null;
    /** A number waiting for its code. */
    pending: string | null;
}

function PhoneForm({ phone }: { phone: PhoneSettings }) {
    const { status } = usePage<SharedData>().props;
    const send = useForm({ phone: '' });
    const verify = useForm({ code: '' });
    const remove = useForm({});

    const messages: Record<string, string> = {
        'phone-verified': t('Your mobile number is confirmed. You can now sign in with it.'),
        'phone-removed': t('Your mobile number is removed.'),
    };

    if (phone.pending) {
        return (
            <form
                noValidate
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    verify.post(route('account.phone.verify'), {
                        preserveScroll: true,
                        onFinish: () => verify.reset(),
                    });
                }}
            >
                <p className="text-sm">
                    {tx("We've texted a 6-digit code to :phone.", {
                        phone: <span className="font-medium">{phone.pending}</span>,
                    })}
                </p>
                <FormField
                    id="phone_code"
                    label={t('Code')}
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={6}
                    value={verify.data.code}
                    onChange={(e) => verify.setData('code', e.target.value.replace(/\D/g, ''))}
                    error={verify.errors.code}
                />
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" processing={verify.processing}>
                        {t('Confirm number')}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => remove.delete(route('account.phone.destroy'), { preserveScroll: true })}
                    >
                        {t('Use a different number')}
                    </Button>
                </div>
            </form>
        );
    }

    return (
        <div className="space-y-4">
            {status && messages[status] && <Alert tone="success">{messages[status]}</Alert>}
            {phone.number && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm">
                        <span className="font-medium">{phone.number}</span>{' '}
                        <span className="text-muted">· {t('confirmed, used to sign in and for order texts')}</span>
                    </p>
                    <Button
                        type="button"
                        variant="secondary"
                        processing={remove.processing}
                        onClick={() => remove.delete(route('account.phone.destroy'), { preserveScroll: true })}
                    >
                        {t('Remove')}
                    </Button>
                </div>
            )}
            {phone.available ? (
                <form
                    noValidate
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        send.post(route('account.phone.send'), { preserveScroll: true });
                    }}
                >
                    <FormField
                        id="phone"
                        label={phone.number ? t('Change to') : t('Mobile number')}
                        type="tel"
                        autoComplete="tel"
                        placeholder="+91 98000 00001"
                        hint={t("With the country code. We'll text you a code to confirm it.")}
                        value={send.data.phone}
                        onChange={(e) => send.setData('phone', e.target.value)}
                        error={send.errors.phone}
                    />
                    <Button type="submit" processing={send.processing} disabled={send.data.phone.trim() === ''}>
                        {t('Send code')}
                    </Button>
                </form>
            ) : (
                !phone.number && (
                    <p className="text-sm text-muted">{t("Adding a mobile number isn't available right now.")}</p>
                )
            )}
        </div>
    );
}

export default function AccountSettings({ phone }: { phone: PhoneSettings }) {
    return (
        <AppLayout>
            <Head title={t('Account settings')} />

            <div className="mx-auto max-w-xl space-y-6 px-4 py-8 sm:px-6 md:py-12">
                <div>
                    <TextLink href={route('account')} className="text-sm">
                        {t('← Account')}
                    </TextLink>
                    <h1 className="mt-4 text-2xl font-semibold md:text-3xl">{t('Account settings')}</h1>
                </div>

                <Section id="details-heading" title={t('Your details')}>
                    <ProfileForm />
                </Section>

                <Section id="password-heading" title={t('Password')}>
                    <PasswordForm />
                </Section>

                <Section id="phone-heading" title={t('Mobile number')}>
                    <PhoneForm phone={phone} />
                </Section>
            </div>
        </AppLayout>
    );
}
