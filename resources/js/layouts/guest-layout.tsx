import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface GuestLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

/** Centred card layout for the authentication screens. */
export default function GuestLayout({ title, description, children }: GuestLayoutProps) {
    const { name, status } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-screen flex-col items-center px-4 py-10 sm:justify-center">
            <Head title={title} />

            {/* TODO(client): replace text with logo */}
            <Link href={route('home')} className="mb-6 text-xl font-semibold text-primary-700">
                {name}
            </Link>

            <div className="w-full max-w-md rounded-card border border-border bg-surface p-4 md:p-6">
                <h1 className="text-lg font-semibold">{title}</h1>
                {description && <p className="mt-1 text-sm text-muted">{description}</p>}

                {status && status !== 'verification-link-sent' && (
                    <p role="status" className="mt-4 rounded-control bg-primary-50 p-3 text-sm text-success-600">
                        {status}
                    </p>
                )}

                <div className="mt-6">{children}</div>
            </div>
        </div>
    );
}
