import { FormField } from '@/components/form-field';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

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
            {status === 'profile-information-updated' && <Alert tone="success">Your details are saved.</Alert>}
            {!user.email_verified && (
                <Alert tone="warning">
                    Please confirm {user.email}: we've emailed you a link.{' '}
                    <TextLink href={route('verification.notice')}>Resend the link</TextLink>
                </Alert>
            )}
            <FormField
                id="name"
                label="Name"
                autoComplete="name"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                error={errors.name}
            />
            <FormField
                id="email"
                label="Email"
                type="email"
                autoComplete="email"
                hint={emailChanged ? "We'll email the new address a link to confirm it." : undefined}
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                error={errors.email}
            />
            <Button type="submit" processing={processing} disabled={!isDirty}>
                Save details
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
            {status === 'password-updated' && <Alert tone="success">Your password is changed.</Alert>}
            <FormField
                id="current_password"
                label="Current password"
                type="password"
                autoComplete="current-password"
                value={data.current_password}
                onChange={(e) => setData('current_password', e.target.value)}
                error={errors.current_password}
            />
            <FormField
                id="password"
                label="New password"
                type="password"
                autoComplete="new-password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                error={errors.password}
            />
            <FormField
                id="password_confirmation"
                label="Confirm new password"
                type="password"
                autoComplete="new-password"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                error={errors.password_confirmation}
            />
            <Button type="submit" processing={processing}>
                Change password
            </Button>
        </form>
    );
}

export default function AccountSettings() {
    return (
        <AppLayout>
            <Head title="Account settings" />

            <div className="mx-auto max-w-xl space-y-6 px-4 py-8 sm:px-6 md:py-12">
                <div>
                    <TextLink href={route('account')} className="text-sm">
                        ← Account
                    </TextLink>
                    <h1 className="mt-4 text-2xl font-semibold md:text-3xl">Account settings</h1>
                </div>

                <Section id="details-heading" title="Your details">
                    <ProfileForm />
                </Section>

                <Section id="password-heading" title="Password">
                    <PasswordForm />
                </Section>
            </div>
        </AppLayout>
    );
}
