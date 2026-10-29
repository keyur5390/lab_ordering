import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { LayoutDashboard, Menu, X, type LucideIcon } from 'lucide-react';
import { useState, type ReactNode } from 'react';

interface NavItem {
    label: string;
    routeName: string;
    icon: LucideIcon;
}

// Admin sections are added here as they are built.
const navItems: NavItem[] = [{ label: 'Dashboard', routeName: 'admin.dashboard', icon: LayoutDashboard }];

interface AdminLayoutProps {
    title: string;
    children: ReactNode;
}

/** Admin shell: left sidebar on desktop, toggled menu on mobile (DESIGN.md §4). */
export default function AdminLayout({ title, children }: AdminLayoutProps) {
    const { name, auth } = usePage<SharedData>().props;
    const [menuOpen, setMenuOpen] = useState(false);

    const nav = (
        <nav aria-label="Admin" className="space-y-1 p-3">
            {navItems.map(({ label, routeName, icon: Icon }) => {
                const active = route().current(routeName);

                return (
                    <Link
                        key={routeName}
                        href={route(routeName)}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'flex h-11 items-center gap-3 rounded-control px-3 text-sm font-medium',
                            active ? 'bg-primary-50 text-primary-700' : 'text-text hover:bg-canvas',
                        )}
                    >
                        <Icon className="size-5" strokeWidth={1.75} aria-hidden="true" />
                        {label}
                    </Link>
                );
            })}
        </nav>
    );

    return (
        <div className="min-h-screen bg-canvas lg:flex">
            <Head title={`${title} · Admin`} />

            <aside className="hidden w-64 shrink-0 border-r border-border bg-surface lg:block">
                <div className="flex h-16 items-center border-b border-border px-6">
                    <Link href={route('admin.dashboard')} className="font-semibold text-primary-700">
                        {name} admin
                    </Link>
                </div>
                {nav}
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-16 items-center justify-between gap-2 border-b border-border bg-surface px-4 sm:px-6">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            className="w-11 px-0 lg:hidden"
                            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                            aria-expanded={menuOpen}
                            aria-controls="admin-mobile-nav"
                            onClick={() => setMenuOpen((open) => !open)}
                        >
                            {menuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </Button>
                        <span className="font-semibold text-primary-700 lg:hidden">Admin</span>
                    </div>

                    <div className="flex items-center gap-1">
                        <span className="hidden text-sm text-muted sm:inline">{auth.user?.name}</span>
                        <Link
                            href={route('home')}
                            className="inline-flex h-11 items-center rounded-control px-3 text-sm font-medium whitespace-nowrap text-text hover:bg-canvas"
                        >
                            View site
                        </Link>
                        <Button variant="ghost" onClick={() => router.post(route('logout'))}>
                            Log out
                        </Button>
                    </div>
                </header>

                {menuOpen && (
                    <div id="admin-mobile-nav" className="border-b border-border bg-surface lg:hidden">
                        {nav}
                    </div>
                )}

                <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-semibold md:text-3xl">{title}</h1>
                    <div className="mt-6">{children}</div>
                </main>
            </div>
        </div>
    );
}
