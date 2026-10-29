import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

const headerLinkClass =
    'inline-flex h-11 items-center rounded-control px-2 text-sm font-medium whitespace-nowrap text-text hover:bg-canvas sm:px-3';

export default function AppLayout({ children }: { children: ReactNode }) {
    const { name, auth } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-screen flex-col">
            <header className="border-b border-border bg-surface">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    {/* TODO(client): replace text with logo */}
                    <Link href={route('home')} className="text-lg font-semibold whitespace-nowrap text-primary-700">
                        {name}
                    </Link>

                    <nav className="flex items-center sm:gap-2" aria-label="Main">
                        <Link href={route('catalogue.index')} className={headerLinkClass}>
                            Tests
                        </Link>
                        {auth.user ? (
                            <>
                                {auth.user.can_access_admin && (
                                    <Link href={route('admin.dashboard')} className={headerLinkClass}>
                                        Admin
                                    </Link>
                                )}
                                <Link href={route('account')} className={headerLinkClass}>
                                    Account
                                </Link>
                                <Button
                                    variant="ghost"
                                    className="px-2 sm:px-4"
                                    onClick={() => router.post(route('logout'))}
                                >
                                    Log out
                                </Button>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className={headerLinkClass}>
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
