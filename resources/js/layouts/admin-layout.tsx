import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    FileText,
    Building2,
    Microscope,
    CalendarOff,
    ClipboardList,
    FlaskConical,
    LayoutDashboard,
    MapPinned,
    Menu,
    Package,
    PanelLeftClose,
    PanelLeftOpen,
    ShieldCheck,
    ScrollText,
    Settings,
    Tags,
    TicketPercent,
    Truck,
    Users,
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

interface NavGroup {
    label: string;
    items: NavItem[];
}

const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            {
                label: 'Dashboard',
                routeName: 'admin.dashboard',
                activePattern: 'admin.dashboard',
                icon: LayoutDashboard,
            },
        ],
    },
    {
        label: 'Orders & lab',
        items: [
            {
                label: 'Orders',
                routeName: 'admin.orders.index',
                activePattern: 'admin.orders.*',
                icon: ClipboardList,
                lab: true,
            },
            { label: 'Lab', routeName: 'admin.lab.index', activePattern: 'admin.lab.*', icon: Microscope, lab: true },
        ],
    },
    {
        label: 'Catalogue',
        items: [
            {
                label: 'Categories',
                routeName: 'admin.categories.index',
                activePattern: 'admin.categories.*',
                icon: Tags,
            },
            { label: 'Tests', routeName: 'admin.tests.index', activePattern: 'admin.tests.*', icon: FlaskConical },
            { label: 'Packages', routeName: 'admin.packages.index', activePattern: 'admin.packages.*', icon: Package },
            {
                label: 'Coupons',
                routeName: 'admin.coupons.index',
                activePattern: 'admin.coupons.*',
                icon: TicketPercent,
            },
        ],
    },
    {
        label: 'Operations',
        items: [
            {
                label: 'Service areas',
                routeName: 'admin.service-areas.index',
                activePattern: 'admin.service-areas.*',
                icon: MapPinned,
            },
            {
                label: 'Collectors',
                routeName: 'admin.collectors.index',
                activePattern: 'admin.collectors.*',
                icon: Truck,
            },
            {
                label: 'Branches',
                routeName: 'admin.branches.index',
                activePattern: 'admin.branches.*',
                icon: Building2,
            },
            {
                label: 'Closures',
                routeName: 'admin.closures.index',
                activePattern: 'admin.closures.*',
                icon: CalendarOff,
            },
        ],
    },
    {
        label: 'Communications',
        items: [
            {
                label: 'Notifications',
                routeName: 'admin.notifications.index',
                activePattern: 'admin.notifications.*',
                icon: Bell,
            },
        ],
    },
    {
        label: 'Website',
        items: [{ label: 'Pages', routeName: 'admin.pages.index', activePattern: 'admin.pages.*', icon: FileText }],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Users', routeName: 'admin.users.index', activePattern: 'admin.users.*', icon: Users },
            { label: 'Audit log', routeName: 'admin.audit-log', activePattern: 'admin.audit-log', icon: ScrollText },
            { label: 'Settings', routeName: 'admin.settings.edit', activePattern: 'admin.settings.*', icon: Settings },
        ],
    },
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
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const isAdmin = auth.user?.can_access_admin ?? false;
    const userInitials = (auth.user?.name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
    const roleLabel = auth.user?.role === 'admin' ? 'Administrator' : 'Lab staff';
    // Lab staff have no dashboard; their home is the lab.
    const home = route(isAdmin ? 'admin.dashboard' : 'admin.lab.index');

    const nav = (collapsed: boolean) => (
        <nav aria-label="Admin" className="space-y-3 p-4">
            {navGroups.map(({ label, items }, index) => {
                const visibleItems = items.filter((item) => isAdmin || item.lab);
                if (!visibleItems.length) return null;

                return (
                    <section key={label} aria-label={label} className={cn(index > 0 && 'border-t border-border pt-4')}>
                        <h2
                            className={cn(
                                'px-3 pb-2 text-[11px] font-semibold tracking-wider text-muted uppercase',
                                collapsed && 'sr-only',
                            )}
                        >
                            {label}
                        </h2>
                        <div className="space-y-1">
                            {visibleItems.map(({ label: itemLabel, routeName, activePattern, icon: Icon }) => {
                                const active = route().current(activePattern);

                                return (
                                    <Link
                                        key={routeName}
                                        href={route(routeName)}
                                        aria-current={active ? 'page' : undefined}
                                        aria-label={collapsed ? itemLabel : undefined}
                                        title={collapsed ? itemLabel : undefined}
                                        onClick={() => setMenuOpen(false)}
                                        className={cn(
                                            'flex h-11 items-center gap-3 rounded-control text-sm font-medium transition-colors',
                                            collapsed ? 'justify-center px-0' : 'px-3',
                                            active
                                                ? 'bg-primary-50 text-primary-700 ring-1 ring-primary-100'
                                                : 'text-muted hover:bg-canvas hover:text-text',
                                        )}
                                    >
                                        <Icon
                                            className={cn('size-5 shrink-0', active && 'text-primary-700')}
                                            strokeWidth={1.75}
                                            aria-hidden="true"
                                        />
                                        <span className={collapsed ? 'sr-only' : undefined}>{itemLabel}</span>
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                );
            })}
        </nav>
    );

    return (
        <div className="min-h-screen bg-canvas lg:flex">
            <Head title={`${title} · Admin`} />

            <aside
                id="admin-sidebar"
                className={cn(
                    'hidden h-screen shrink-0 border-r border-border bg-surface transition-[width] duration-200 lg:sticky lg:top-0 lg:flex lg:flex-col',
                    sidebarCollapsed ? 'w-20' : 'w-72',
                )}
            >
                <div
                    className={cn(
                        'flex h-20 shrink-0 items-center border-b border-border',
                        sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-5',
                    )}
                >
                    <Link
                        href={home}
                        aria-label={`${name} admin console`}
                        title={sidebarCollapsed ? `${name} admin console` : undefined}
                        className="flex min-w-0 items-center gap-3"
                    >
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-control bg-primary-600 text-white shadow-sm">
                            <FlaskConical className="size-5" strokeWidth={1.75} aria-hidden="true" />
                        </span>
                        {!sidebarCollapsed && (
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-semibold tracking-tight text-text">
                                    {name}
                                </span>
                                <span className="mt-0.5 block text-[11px] font-medium tracking-wider text-muted uppercase">
                                    Admin console
                                </span>
                            </span>
                        )}
                    </Link>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto">{nav(sidebarCollapsed)}</div>
                <div className={cn('shrink-0 border-t border-border p-3', sidebarCollapsed && 'px-2')}>
                    <div
                        className={cn(
                            'flex items-center gap-3 rounded-control bg-canvas p-3',
                            sidebarCollapsed && 'justify-center px-1',
                        )}
                    >
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">
                            {userInitials || <ShieldCheck className="size-4" aria-hidden="true" />}
                        </span>
                        {!sidebarCollapsed && (
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-medium text-text">{auth.user?.name}</span>
                                <span className="block text-xs text-muted">{roleLabel}</span>
                            </span>
                        )}
                    </div>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-20 flex h-16 items-center justify-between gap-2 border-b border-border bg-surface/95 px-4 backdrop-blur sm:px-6">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            className="hidden w-11 px-0 lg:inline-flex"
                            aria-label={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                            aria-expanded={!sidebarCollapsed}
                            aria-controls="admin-sidebar"
                            onClick={() => setSidebarCollapsed((collapsed) => !collapsed)}
                        >
                            {sidebarCollapsed ? (
                                <PanelLeftOpen className="size-5" aria-hidden="true" />
                            ) : (
                                <PanelLeftClose className="size-5" aria-hidden="true" />
                            )}
                        </Button>
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
                        <span className="font-semibold tracking-tight text-primary-900 lg:hidden">Admin console</span>
                    </div>

                    <div className="flex items-center gap-1">
                        <span className="hidden items-center gap-2 border-r border-border pr-3 sm:flex">
                            <span className="flex size-8 items-center justify-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700">
                                {userInitials}
                            </span>
                            <span className="max-w-40 truncate text-sm font-medium text-text">{auth.user?.name}</span>
                        </span>
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
                    <div
                        id="admin-mobile-nav"
                        className="max-h-[calc(100dvh-4rem)] overflow-y-auto border-b border-border bg-surface shadow-lg lg:hidden"
                    >
                        {nav(false)}
                    </div>
                )}

                <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                    <div className="mb-6 flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
                        <div>
                            <p className="mb-1 text-xs font-semibold tracking-wider text-muted uppercase">
                                Admin console <span className="px-1 text-border">/</span> {title}
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight text-text md:text-3xl">{title}</h1>
                        </div>
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

                    <div>{children}</div>
                </main>
            </div>
        </div>
    );
}
