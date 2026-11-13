import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { InputError } from '@/components/ui/input-error';
import { TextLink } from '@/components/ui/text-link';
import GuestLayout from '@/layouts/guest-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { t, tx } from '@/lib/i18n';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('register.store'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout
            title={t('Create an account')}
            description={t("You'll need an account to book tests and see your reports.")}
        >
            <form onSubmit={submit} className="space-y-4" noValidate>
                <FormField
                    id="name"
                    label={t('Full name')}
                    autoComplete="name"
                    autoFocus
                    required
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                />

                <FormField
                    id="email"
                    label={t('Email')}
                    type="email"
                    autoComplete="email"
                    required
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <FormField
                    id="password"
                    label={t('Password')}
                    type="password"
                    autoComplete="new-password"
                    required
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <FormField
                    id="password_confirmation"
                    label={t('Confirm password')}
                    type="password"
                    autoComplete="new-password"
                    required
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                />

                <div>
                    <label className="flex min-h-11 items-start gap-3 py-2 text-sm">
                        <Checkbox
                            id="terms"
                            className="mt-0.5"
                            checked={data.terms}
                            onChange={(e) => setData('terms', e.target.checked)}
                            aria-invalid={errors.terms ? true : undefined}
                            aria-describedby={errors.terms ? 'terms-error' : undefined}
                        />
                        <span>
                            {tx(
                                "I agree to the :terms and the :privacy, including the use of my and my family's health information to provide tests and reports.",
                                {
                                    terms: (
                                        <a
                                            href={route('pages.show', 'terms')}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="font-medium text-primary-700 underline-offset-4 hover:underline"
                                        >
                                            {t('terms of service')}
                                        </a>
                                    ),
                                    privacy: (
                                        <a
                                            href={route('pages.show', 'privacy')}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="font-medium text-primary-700 underline-offset-4 hover:underline"
                                        >
                                            {t('privacy policy')}
                                        </a>
                                    ),
                                },
                            )}
                        </span>
                    </label>
                    <InputError id="terms-error" message={errors.terms} />
                </div>

                <Button type="submit" className="w-full" processing={processing}>
                    {t('Create account')}
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted">
                {t('Already registered?')} <TextLink href={route('login')}>{t('Log in')}</TextLink>
            </p>
        </GuestLayout>
    );
}
