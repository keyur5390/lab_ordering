import { Price } from '@/components/catalogue/price';
import { CurrencyMissingNotice } from '@/components/admin/currency-note';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import type { OrderStatus } from '@/types';
import { Link } from '@inertiajs/react';
import { AlertTriangle, ChevronRight, CircleAlert, Info, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Period = 'today' | '7d' | '30d';

interface DashboardProps {
    today: {
        home_collections: number;
        lab_visits: number;
        unassigned: number;
        failed_visits: number;
        awaiting_reports: number;
    };
    statuses: { status: OrderStatus; label: string; count: number }[];
    period: Period;
    money: { currency: string | null; paid_online: number; paid_at_collection: number; refunded: number; net: number };
    alerts: { tone: 'danger' | 'warning' | 'info'; message: string; url: string }[];
}

const periods: { value: Period; label: string }[] = [
    { value: 'today', label: 'Today' },
    { value: '7d', label: 'Last 7 days' },
    { value: '30d', label: 'Last 30 days' },
];

// Status alerts carry an icon and a word, never colour alone.
const alertStyles: Record<
    DashboardProps['alerts'][number]['tone'],
    { icon: LucideIcon; label: string; className: string }
> = {
    danger: { icon: AlertTriangle, label: 'Action needed', className: 'text-danger-600' },
    warning: { icon: CircleAlert, label: 'Check', className: 'text-warning-600' },
    info: { icon: Info, label: 'Note', className: 'text-primary-700' },
};

/** A stat tile: label, value, and an optional link to the matching list. */
function Tile({ label, value, href, note }: { label: string; value: ReactNode; href?: string; note?: string }) {
    const body = (
        <>
            <span className="block text-sm text-muted">{label}</span>
            <span className="mt-1 block text-2xl font-semibold tabular-nums">{value}</span>
            {note && <span className="mt-0.5 block text-xs text-muted">{note}</span>}
        </>
    );
    const className = 'block rounded-card border border-border bg-surface p-4';

    return href ? (
        <Link href={href} className={cn(className, 'hover:bg-canvas')}>
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}

export default function Dashboard({ today, statuses, period, money, alerts }: DashboardProps) {
    const amount = (value: number) => (money.currency ? <Price amount={value} currencyCode={money.currency} /> : '—');

    return (
        <AdminLayout title="Dashboard">
            <div className="space-y-8">
                <CurrencyMissingNotice />

                {alerts.length > 0 && (
                    <section aria-labelledby="alerts-heading">
                        <h2 id="alerts-heading" className="sr-only">
                            Needs attention
                        </h2>
                        <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                            {alerts.map((alert) => {
                                const style = alertStyles[alert.tone];

                                return (
                                    <li key={alert.message}>
                                        <Link href={alert.url} className="flex items-center gap-3 p-4 hover:bg-canvas">
                                            <style.icon
                                                className={cn('size-5 shrink-0', style.className)}
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0 flex-1 text-sm">
                                                <span className={cn('font-medium', style.className)}>
                                                    {style.label}:{' '}
                                                </span>
                                                {alert.message}
                                            </span>
                                            <ChevronRight className="size-4 shrink-0 text-muted" aria-hidden="true" />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                )}

                <section aria-labelledby="today-heading">
                    <h2 id="today-heading" className="text-lg font-semibold">
                        Today
                    </h2>
                    <div className="mt-3 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                        <Tile
                            label="Home collections"
                            value={today.home_collections}
                            href={route('admin.orders.index')}
                        />
                        <Tile label="Lab visits" value={today.lab_visits} href={route('admin.lab.index')} />
                        <Tile
                            label="Visits without a collector"
                            value={today.unassigned}
                            note="Today and tomorrow"
                            href={route('admin.orders.index', { status: 'confirmed' })}
                        />
                        <Tile
                            label="Failed visits"
                            value={today.failed_visits}
                            note="To reschedule or cancel"
                            href={route('admin.orders.index', { status: 'collector_assigned' })}
                        />
                        <Tile
                            label="Waiting for reports"
                            value={today.awaiting_reports}
                            href={route('admin.lab.index', { status: 'received_at_lab' })}
                        />
                    </div>
                </section>

                <section aria-labelledby="money-heading">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="money-heading" className="text-lg font-semibold">
                            Money
                        </h2>
                        <nav aria-label="Period" className="flex rounded-control border border-border bg-surface p-1">
                            {periods.map((option) => (
                                <Link
                                    key={option.value}
                                    href={route(
                                        'admin.dashboard',
                                        option.value === 'today' ? {} : { period: option.value },
                                    )}
                                    preserveScroll
                                    aria-current={period === option.value ? 'page' : undefined}
                                    className={cn(
                                        'inline-flex h-11 items-center rounded-control px-3 text-sm font-medium sm:h-9',
                                        period === option.value
                                            ? 'bg-primary-50 text-primary-700'
                                            : 'text-muted hover:text-text',
                                    )}
                                >
                                    {option.label}
                                </Link>
                            ))}
                        </nav>
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-3 xl:grid-cols-4">
                        <Tile label="Paid online" value={amount(money.paid_online)} />
                        <Tile label="Paid at collection" value={amount(money.paid_at_collection)} />
                        <Tile label="Refunded" value={amount(money.refunded)} />
                        <Tile label="Net" value={amount(money.net)} note="Paid minus refunded" />
                    </div>
                </section>

                <section aria-labelledby="statuses-heading">
                    <h2 id="statuses-heading" className="text-lg font-semibold">
                        Orders by status
                    </h2>
                    <ul className="mt-3 max-w-xl divide-y divide-border rounded-card border border-border bg-surface">
                        {statuses.map((row) => (
                            <li key={row.status}>
                                <Link
                                    href={route('admin.orders.index', { status: row.status })}
                                    className="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-canvas"
                                >
                                    <span>{row.label}</span>
                                    <span className="flex items-center gap-2 font-semibold tabular-nums">
                                        {row.count}
                                        <ChevronRight className="size-4 text-muted" aria-hidden="true" />
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </AdminLayout>
    );
}
