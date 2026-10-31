import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    Building2,
    Microscope,
    CalendarOff,
    ClipboardList,
    FlaskConical,
    LayoutDashboard,
    MapPinned,
    Menu,
    Package,
    Settings,
    Tags,
    TicketPercent,
    Truck,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';

interface NavItem {
    label: string;
    routeName: string;
    /** Route pattern that marks this item active, e.g. "admin.tests.*". */
    activePattern: string;
    icon: LucideIcon;
    /** Also shown to lab staff (who can't use the rest of the admin area). */
    lab?: boolean;
}

// Admin sections are added here as they are built.
const navItems: NavItem[] = [
    { label: 'Dashboard', routeName: 'admin.dashboard', activePattern: 'admin.dashboard', icon: LayoutDashboard },
    {
        label: 'Orders',
        routeName: 'admin.orders.index',
        activePattern: 'admin.orders.*',
        icon: ClipboardList,
        lab: true,
    },
    { label: 'Lab', routeName: 'admin.lab.index', activePattern: 'admin.lab.*', icon: Microscope, lab: true },
    { label: 'Categories', routeName: 'admin.categories.index', activePattern: 'admin.categories.*', icon: Tags },
    { label: 'Tests', routeName: 'admin.tests.index', activePattern: 'admin.tests.*', icon: FlaskConical },
    { label: 'Packages', routeName: 'admin.packages.index', activePattern: 'admin.packages.*', icon: Package },
    {
        label: 'Service areas',
        routeName: 'admin.service-areas.index',
        activePattern: 'admin.service-areas.*',
        icon: MapPinned,
    },
    { label: 'Coupons', routeName: 'admin.coupons.index', activePattern: 'admin.coupons.*', icon: TicketPercent },
    { label: 'Collectors', routeName: 'admin.collectors.index', activePattern: 'admin.collectors.*', icon: Truck },
    { label: 'Branches', routeName: 'admin.branches.index', activePattern: 'admin.branches.*', icon: Building2 },
    { label: 'Closures', routeName: 'admin.closures.index', activePattern: 'admin.closures.*', icon: CalendarOff },
    {
        label: 'Notifications',
        routeName: 'admin.notifications.index',
        activePattern: 'admin.notifications.*',
        icon: Bell,
    },
    { label: 'Settings', routeName: 'admin.settings.edit', activePattern: 'admin.settings.*', icon: Settings },
];

interface AdminLayoutProps {
    title: string;
    /** Rendered next to the page title, e.g. a "New test" button. */
    actions?: ReactNode;
    children: ReactNode;
}

/** Admin shell: left sidebar on desktop, toggled menu on mobile (DESIGN.md §4). */
export default function AdminLayout({ title, actions, children }: AdminLayoutProps) {
    const { name, auth, status, errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const isAdmin = auth.user?.can_access_admin ?? false;
    // Lab staff have no dashboard; their home is the lab.
    const home = route(isAdmin ? 'admin.dashboard' : 'admin.lab.index');

    const nav = (
        <nav aria-label="Admin" className="space-y-1 p-3">
            {navItems
                .filter((item) => isAdmin || item.lab)
                .map(({ label, routeName, activePattern, icon: Icon }) => {
                    const active = route().current(activePattern);

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
                    <Link href={home} className="font-semibold text-primary-700">
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
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <h1 className="text-2xl font-semibold md:text-3xl">{title}</h1>
                        {actions}
                    </div>

                    {status && (
                        <Alert tone="success" className="mt-4">
                            {status}
                        </Alert>
                    )}
                    {errors.delete && (
                        <Alert tone="danger" className="mt-4">
                            {errors.delete}
                        </Alert>
                    )}

                    <div className="mt-6">{children}</div>
                </main>
            </div>
        </div>
    );
}
