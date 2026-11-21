import { Button } from '@/components/ui/button';
import GuestLayout from '@/layouts/guest-layout';
import type { SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { t } from '@/lib/i18n';

export default function VerifyEmail() {
    const { status, auth } = usePage<SharedData>().props;
    const { post, processing } = useForm({});

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout
            title={t('Verify your email')}
            description={t("We've sent a verification link to :email. Click it to finish setting up your account.", {
                email: auth.user?.email ?? t('your email'),
            })}
        >
            {status === 'verification-link-sent' && (
                <p role="status" className="mb-4 rounded-control bg-primary-50 p-3 text-sm text-success-600">
                    {t('A new verification link has been sent.')}
                </p>
            )}

            <form onSubmit={submit} className="space-y-3">
                <Button type="submit" className="w-full" processing={processing}>
                    {t('Resend verification email')}
                </Button>
                <Button variant="ghost" className="w-full" onClick={() => router.post(route('logout'))}>
                    {t('Log out')}
                </Button>
            </form>
        </GuestLayout>
    );
}
