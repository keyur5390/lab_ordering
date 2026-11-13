import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { InputError } from '@/components/ui/input-error';
import { TextLink } from '@/components/ui/text-link';
import GuestLayout from '@/layouts/guest-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

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
        <GuestLayout title="Create an account" description="You'll need an account to book tests and see your reports.">
            <form onSubmit={submit} className="space-y-4" noValidate>
                <FormField
                    id="name"
                    label="Full name"
                    autoComplete="name"
                    autoFocus
                    required
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                />

                <FormField
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="email"
                    required
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <FormField
                    id="password"
                    label="Password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <FormField
                    id="password_confirmation"
                    label="Confirm password"
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
                            I agree to the{' '}
                            <a
                                href={route('pages.show', 'terms')}
                                target="_blank"
                                rel="noreferrer"
                                className="font-medium text-primary-700 underline-offset-4 hover:underline"
                            >
                                terms of service
                            </a>{' '}
                            and the{' '}
                            <a
                                href={route('pages.show', 'privacy')}
                                target="_blank"
                                rel="noreferrer"
                                className="font-medium text-primary-700 underline-offset-4 hover:underline"
                            >
                                privacy policy
                            </a>
                            , including the use of my and my family's health information to provide tests and reports.
                        </span>
                    </label>
                    <InputError id="terms-error" message={errors.terms} />
                </div>

                <Button type="submit" className="w-full" processing={processing}>
                    Create account
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted">
                Already registered? <TextLink href={route('login')}>Log in</TextLink>
            </p>
        </GuestLayout>
    );
}
