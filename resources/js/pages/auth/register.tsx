import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
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
