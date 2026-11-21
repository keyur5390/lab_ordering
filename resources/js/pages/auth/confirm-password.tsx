import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import GuestLayout from '@/layouts/guest-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { t } from '@/lib/i18n';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('password.confirm.store'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout title={t('Confirm your password')} description={t('Please confirm your password to continue.')}>
            <form onSubmit={submit} className="space-y-4" noValidate>
                <FormField
                    id="password"
                    label={t('Password')}
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    required
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <Button type="submit" className="w-full" processing={processing}>
                    {t('Confirm')}
                </Button>
            </form>
        </GuestLayout>
    );
}
