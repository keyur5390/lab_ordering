import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { TextLink } from '@/components/ui/text-link';
import GuestLayout from '@/layouts/guest-layout';
import { cn } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { t } from '@/lib/i18n';

interface LoginProps {
    canResetPassword: boolean;
    /** Phone sign-in is offered (SMS is on). */
    phoneLogin: boolean;
    /** The (masked) number a sign-in code was texted to. */
    phonePending: string | null;
}

function RememberMe({ checked, onChange }: { checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <div className="flex items-center gap-2">
            <Checkbox id="remember" checked={checked} onChange={(e) => onChange(e.target.checked)} />
            <Label htmlFor="remember" className="font-normal">
                {t('Remember me')}
            </Label>
        </div>
    );
}

function EmailLogin({ canResetPassword }: { canResetPassword: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('login.store'), { onFinish: () => reset('password') });
    };

    return (
        <form onSubmit={submit} className="space-y-4" noValidate>
            <FormField
                id="email"
                label={t('Email')}
                type="email"
                autoComplete="email"
                autoFocus
                required
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                error={errors.email}
            />

            <FormField
                id="password"
                label={t('Password')}
                type="password"
                autoComplete="current-password"
                required
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                error={errors.password}
                labelAside={
                    canResetPassword && (
                        <TextLink href={route('password.request')} className="text-sm">
                            {t('Forgot password?')}
                        </TextLink>
                    )
                }
            />

            <RememberMe checked={data.remember} onChange={(checked) => setData('remember', checked)} />

            <Button type="submit" className="w-full" processing={processing}>
                {t('Log in')}
            </Button>
        </form>
    );
}

function PhoneLogin({ pending }: { pending: string | null }) {
    const send = useForm({ phone: '' });
    const verify = useForm({ code: '', remember: false });

    if (!pending) {
        return (
            <form
                noValidate
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    send.post(route('login.phone.send'));
                }}
            >
                <FormField
                    id="phone"
                    label={t('Mobile number')}
                    type="tel"
                    autoComplete="tel"
                    autoFocus
                    placeholder="+91 98000 00001"
                    hint={t('The number you confirmed in your account settings, with the country code.')}
                    value={send.data.phone}
                    onChange={(e) => send.setData('phone', e.target.value)}
                    error={send.errors.phone}
                />
                <Button type="submit" className="w-full" processing={send.processing}>
                    {t('Text me a code')}
                </Button>
            </form>
        );
    }

    return (
        <form
            noValidate
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                verify.post(route('login.phone.verify'), { onFinish: () => verify.reset('code') });
            }}
        >
            <FormField
                id="code"
                label={t('Code sent to :phone', { phone: pending })}
                inputMode="numeric"
                autoComplete="one-time-code"
                autoFocus
                maxLength={6}
                value={verify.data.code}
                onChange={(e) => verify.setData('code', e.target.value.replace(/\D/g, ''))}
                error={verify.errors.code}
            />
            <RememberMe checked={verify.data.remember} onChange={(checked) => verify.setData('remember', checked)} />
            <Button type="submit" className="w-full" processing={verify.processing}>
                {t('Log in')}
            </Button>
            <Button
                type="button"
                variant="ghost"
                className="w-full"
                onClick={() => router.post(route('login.phone.reset'))}
            >
                {t('Use a different number')}
            </Button>
        </form>
    );
}

export default function Login({ canResetPassword, phoneLogin, phonePending }: LoginProps) {
    const [method, setMethod] = useState<'email' | 'phone'>(phoneLogin && phonePending ? 'phone' : 'email');

    return (
        <GuestLayout
            title={t('Log in')}
            description={
                method === 'email'
                    ? t('Use the email and password you registered with.')
                    : t("We'll text a code to your mobile.")
            }
        >
            {phoneLogin && (
                <div
                    role="tablist"
                    aria-label={t('Log in with')}
                    className="mb-6 grid grid-cols-2 rounded-control bg-canvas p-1"
                >
                    {(['email', 'phone'] as const).map((option) => (
                        <button
                            key={option}
                            type="button"
                            role="tab"
                            aria-selected={method === option}
                            className={cn(
                                'min-h-11 rounded-control text-sm font-medium',
                                method === option ? 'bg-surface text-text shadow-sm' : 'text-muted hover:text-text',
                            )}
                            onClick={() => setMethod(option)}
                        >
                            {option === 'email' ? t('Email') : t('Mobile number')}
                        </button>
                    ))}
                </div>
            )}

            {method === 'phone' && phoneLogin ? (
                <PhoneLogin pending={phonePending} />
            ) : (
                <EmailLogin canResetPassword={canResetPassword} />
            )}

            <p className="mt-6 text-center text-sm text-muted">
                {t('New here?')} <TextLink href={route('register')}>{t('Create an account')}</TextLink>
            </p>
        </GuestLayout>
    );
}
