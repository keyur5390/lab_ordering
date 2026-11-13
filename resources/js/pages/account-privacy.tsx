import { FormField } from '@/components/form-field';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { TextLink } from '@/components/ui/text-link';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

interface AccountPrivacyProps {
    privacyAcceptedAt: string | null;
    /** Why the account can't be deleted right now, or null. */
    cannotDelete: string | null;
    privacyContact: { name: string | null; email: string | null };
    timezone: string;
}

function Section({ id, title, children }: { id: string; title: string; children: ReactNode }) {
    return (
        <section aria-labelledby={id} className="rounded-card border border-border bg-surface p-4 md:p-6">
            <h2 id={id} className="text-base font-semibold">
                {title}
            </h2>
            <div className="mt-2 space-y-3 text-sm">{children}</div>
        </section>
    );
}

function DeleteAccount({ cannotDelete }: { cannotDelete: string | null }) {
    const [confirming, setConfirming] = useState(false);
    const { data, setData, delete: destroy, processing, errors, reset } = useForm({ password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setConfirming(true);
    };
    const confirm = () =>
        destroy(route('account.destroy'), {
            preserveScroll: true,
            onFinish: () => {
                reset('password');
                setConfirming(false);
            },
        });

    if (cannotDelete) {
        return <Alert tone="warning">{cannotDelete}</Alert>;
    }

    return (
        <form onSubmit={submit} noValidate className="space-y-3">
            <FormField
                id="password"
                label="Your password"
                type="password"
                autoComplete="current-password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                error={errors.password}
            />
            <Button type="submit" variant="danger" disabled={data.password === ''}>
                Delete my account
            </Button>
            <ConfirmDialog
                open={confirming}
                title="Delete your account?"
                confirmLabel="Delete my account"
                processing={processing}
                onConfirm={confirm}
                onClose={() => setConfirming(false)}
            >
                This can't be undone. You'll be signed out and won't be able to see your orders or reports again.
            </ConfirmDialog>
        </form>
    );
}

export default function AccountPrivacy({
    privacyAcceptedAt,
    cannotDelete,
    privacyContact,
    timezone,
}: AccountPrivacyProps) {
    return (
        <AppLayout>
            <Head title="Privacy and data" />

            <section className="mx-auto max-w-3xl space-y-4 px-4 py-8 sm:px-6 md:py-12 lg:px-8">
                <TextLink href={route('account')} className="text-sm">
                    ← Your account
                </TextLink>
                <h1 className="text-2xl font-semibold md:text-3xl">Privacy and data</h1>
                <p className="text-sm text-muted">
                    How we use your data is set out in our{' '}
                    <TextLink href={route('pages.show', 'privacy')}>privacy policy</TextLink>.
                    {privacyAcceptedAt && <> You agreed to it on {formatDateTime(privacyAcceptedAt, timezone)}.</>}
                </p>

                <Section id="export-heading" title="Download your data">
                    <p className="text-muted">
                        One file with your account, patients, addresses, orders, payments and the messages we sent you.
                        Reports are downloaded from each order.
                    </p>
                    <a
                        href={route('account.privacy.export')}
                        className="inline-flex min-h-11 items-center gap-2 rounded-control border border-border px-4 font-medium hover:bg-canvas"
                    >
                        <Download className="size-4" aria-hidden="true" />
                        Download my data
                    </a>
                </Section>

                <Section id="delete-heading" title="Delete your account">
                    <p className="text-muted">
                        We remove your name, email, patients and addresses, and you're signed out for good. Orders,
                        invoices and lab results are kept for our medical and tax records, without your name or contact
                        details.
                    </p>
                    <DeleteAccount cannotDelete={cannotDelete} />
                </Section>

                {privacyContact.email && (
                    <Section id="contact-heading" title="Questions or complaints">
                        <p>
                            {privacyContact.name && <>{privacyContact.name} · </>}
                            <a
                                href={`mailto:${privacyContact.email}`}
                                className="text-primary-700 underline underline-offset-2"
                            >
                                {privacyContact.email}
                            </a>
                        </p>
                    </Section>
                )}
            </section>
        </AppLayout>
    );
}
