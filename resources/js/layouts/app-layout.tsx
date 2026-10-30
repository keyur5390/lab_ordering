import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Menu, ShoppingCart, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

const headerLinkClass =
    'inline-flex h-11 items-center rounded-control px-3 text-sm font-medium whitespace-nowrap text-text hover:bg-canvas';
const registerLinkClass =
    'inline-flex h-11 items-center justify-center rounded-control bg-primary-600 px-4 text-sm font-medium whitespace-nowrap text-white hover:bg-primary-700';

/** Header links, laid out in a row on desktop and stacked in the mobile menu. */
function NavLinks({ stacked }: { stacked?: boolean }) {
    const { auth } = usePage<SharedData>().props;
    const linkClass = cn(headerLinkClass, stacked && 'w-full');

    return (
        <>
            <Link href={route('catalogue.index')} className={linkClass}>
                Tests
            </Link>
            {auth.user ? (
                <>
                    {auth.user.can_access_collector && (
                        <Link href={route('collector.index')} className={linkClass}>
                            My visits
                        </Link>
                    )}
                    {auth.user.can_access_admin ? (
                        <Link href={route('admin.dashboard')} className={linkClass}>
                            Admin
                        </Link>
                    ) : (
                        auth.user.can_access_lab && (
                            <Link href={route('admin.lab.index')} className={linkClass}>
                                Lab
                            </Link>
                        )
                    )}
                    <Link href={route('account')} className={linkClass}>
                        Account
                    </Link>
                    <Button
                        variant="ghost"
                        className={cn('px-3', stacked && 'w-full justify-start')}
                        onClick={() => router.post(route('logout'))}
                    >
                        Log out
                    </Button>
                </>
            ) : (
                <>
                    <Link href={route('login')} className={linkClass}>
                        Log in
                    </Link>
                    <Link href={route('register')} className={cn(registerLinkClass, stacked && 'mt-2 w-full')}>
                        Register
                    </Link>
                </>
            )}
        </>
    );
}

function CartLink() {
    const { cart } = usePage<SharedData>().props;

    return (
        <Link
            href={route('cart.show')}
            className={cn(headerLinkClass, 'relative')}
            aria-label={`Cart, ${cart.count} ${cart.count === 1 ? 'item' : 'items'}`}
        >
            <ShoppingCart className="size-5" strokeWidth={1.75} aria-hidden="true" />
            {cart.count > 0 && (
                <span
                    className="absolute top-1 right-0 flex min-w-5 items-center justify-center rounded-full bg-primary-600 px-1 text-xs font-semibold text-white"
                    aria-hidden="true"
                >
                    {cart.count}
                </span>
            )}
        </Link>
    );
}

/** Customer layout: inline nav from md up; cart + menu button with a stacked menu below md (DESIGN.md §4). */
export default function AppLayout({ children }: { children: ReactNode }) {
    const { name } = usePage<SharedData>().props;
    const [menuOpen, setMenuOpen] = useState(false);

    // Close the mobile menu on any navigation, including to another page of the same kind.
    useEffect(() => router.on('start', () => setMenuOpen(false)), []);

    return (
        <div className="flex min-h-screen flex-col">
            <header className="border-b border-border bg-surface">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    {/* TODO(client): replace text with logo */}
                    <Link href={route('home')} className="text-lg font-semibold whitespace-nowrap text-primary-700">
                        {name}
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex" aria-label="Main">
                        <NavLinks />
                        <CartLink />
                    </nav>

                    <div className="flex items-center md:hidden">
                        <CartLink />
                        <Button
                            variant="ghost"
                            className="w-11 px-0"
                            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                            aria-expanded={menuOpen}
                            aria-controls="main-mobile-nav"
                            onClick={() => setMenuOpen((open) => !open)}
                        >
                            {menuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </Button>
                    </div>
                </div>

                {menuOpen && (
                    <nav id="main-mobile-nav" aria-label="Main" className="border-t border-border px-4 py-3 md:hidden">
                        <div className="flex flex-col">
                            <NavLinks stacked />
                        </div>
                    </nav>
                )}
            </header>

            <main className="flex-1">{children}</main>
        </div>
    );
}
