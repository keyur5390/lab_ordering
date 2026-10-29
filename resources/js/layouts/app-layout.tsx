import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function AppLayout({ children }: { children: ReactNode }) {
    const { name, auth } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-screen flex-col">
            <header className="border-b border-border bg-surface">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    {/* TODO(client): replace text with logo */}
                    <Link href={route('home')} className="text-lg font-semibold text-primary-700">
                        {name}
                    </Link>

                    <nav className="flex items-center gap-1 sm:gap-2" aria-label="Account">
                        {auth.user ? (
                            <>
                                <Link
                                    href={route('account')}
                                    className="inline-flex h-11 items-center rounded-control px-3 text-sm font-medium text-text hover:bg-canvas"
                                >
                                    Account
                                </Link>
                                <Button variant="ghost" onClick={() => router.post(route('logout'))}>
                                    Log out
                                </Button>
                            </>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="inline-flex h-11 items-center rounded-control px-3 text-sm font-medium text-text hover:bg-canvas"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="inline-flex h-11 items-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>
        </div>
    );
}
