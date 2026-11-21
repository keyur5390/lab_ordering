import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { FlaskConical, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';

interface GuestLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

/** Centred layout for the authentication screens. */
export default function GuestLayout({ title, description, children }: GuestLayoutProps) {
    const { name, status } = usePage<SharedData>().props;

    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-10">
            <Head title={title} />

            <div className="pointer-events-none absolute -top-32 -right-32 size-96 rounded-full bg-primary-100/70 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-40 -left-40 size-[30rem] rounded-full bg-accent-100/70 blur-3xl" />

            <Link href={route('home')} className="relative mb-8 flex items-center gap-3">
                <span className="flex size-11 items-center justify-center rounded-xl bg-primary-600 text-white shadow-md">
                    <FlaskConical className="size-5" strokeWidth={1.8} aria-hidden="true" />
                </span>
                <span className="text-xl font-bold tracking-tight text-text">{name}</span>
            </Link>

            <div className="relative w-full max-w-md rounded-2xl border border-white/80 bg-surface/95 p-6 shadow-[0_24px_70px_rgba(23,45,40,0.12)] backdrop-blur sm:p-8">
                <div className="mb-6">
                    <p className="mb-2 text-xs font-semibold tracking-[0.16em] text-primary-700 uppercase">
                        Your health, made simpler
                    </p>
                    <h1 className="text-2xl font-bold tracking-tight text-text">{title}</h1>
                    {description && <p className="mt-2 text-sm leading-6 text-muted">{description}</p>}
                </div>

                {status && status !== 'verification-link-sent' && (
                    <p role="status" className="mt-4 rounded-control bg-primary-50 p-3 text-sm text-success-600">
                        {status}
                    </p>
                )}

                <div>{children}</div>
            </div>
            <p className="relative mt-6 flex items-center gap-2 text-xs font-medium text-muted">
                <ShieldCheck className="size-4 text-primary-600" aria-hidden="true" />
                Your information is private and secure
            </p>
        </div>
    );
}
