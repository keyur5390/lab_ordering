import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import GuestLayout from '@/layouts/guest-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('password.confirm.store'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title="Confirm your password" description="Please confirm your password to continue.">
            <form onSubmit={submit} className="space-y-4" noValidate>
                <FormField
                    id="password"
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    required
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <Button type="submit" className="w-full" processing={processing}>
                    Confirm
                </Button>
            </form>
        </GuestLayout>
    );
}
