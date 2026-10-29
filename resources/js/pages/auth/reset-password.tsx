import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import GuestLayout from '@/layouts/guest-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('password.update'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <GuestLayout title="Choose a new password">
            <form onSubmit={submit} className="space-y-4" noValidate>
                <FormField
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="email"
                    readOnly
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <FormField
                    id="password"
                    label="New password"
                    type="password"
                    autoComplete="new-password"
                    autoFocus
                    required
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <FormField
                    id="password_confirmation"
                    label="Confirm new password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                />

                <Button type="submit" className="w-full" processing={processing}>
                    Reset password
                </Button>
            </form>
        </GuestLayout>
    );
}
