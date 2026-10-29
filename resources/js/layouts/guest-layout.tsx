import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function GuestLayout({ children }: { children: ReactNode }) {
    const { name } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-screen flex-col">
            <header className="border-b border-border bg-surface">
                <div className="mx-auto flex h-16 max-w-7xl items-center px-4 sm:px-6 lg:px-8">
                    {/* TODO(client): replace text with logo */}
                    <Link href={route('home')} className="text-lg font-semibold text-primary-700">
                        {name}
                    </Link>
                </div>
            </header>

            <main className="flex-1">{children}</main>
        </div>
    );
}
