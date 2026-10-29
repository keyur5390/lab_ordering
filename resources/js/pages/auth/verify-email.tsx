import { Button } from '@/components/ui/button';
import GuestLayout from '@/layouts/guest-layout';
import type { SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function VerifyEmail() {
    const { status, auth } = usePage<SharedData>().props;
    const { post, processing } = useForm({});

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout
            title="Verify your email"
            description={`We've sent a verification link to ${auth.user?.email ?? 'your email'}. Click it to finish setting up your account.`}
        >
            {status === 'verification-link-sent' && (
                <p role="status" className="mb-4 rounded-control bg-primary-50 p-3 text-sm text-success-600">
                    A new verification link has been sent.
                </p>
            )}

            <form onSubmit={submit} className="space-y-3">
                <Button type="submit" className="w-full" processing={processing}>
                    Resend verification email
                </Button>
                <Button variant="ghost" className="w-full" onClick={() => router.post(route('logout'))}>
                    Log out
                </Button>
            </form>
        </GuestLayout>
    );
}
