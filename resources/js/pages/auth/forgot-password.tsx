import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { TextLink } from '@/components/ui/text-link';
import GuestLayout from '@/layouts/guest-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function ForgotPassword() {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout title="Forgot your password?" description="Enter your email and we'll send you a reset link.">
            <form onSubmit={submit} className="space-y-4" noValidate>
                <FormField
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="email"
                    autoFocus
                    required
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <Button type="submit" className="w-full" processing={processing}>
                    Email reset link
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted">
                Remembered it? <TextLink href={route('login')}>Back to log in</TextLink>
            </p>
        </GuestLayout>
    );
}
