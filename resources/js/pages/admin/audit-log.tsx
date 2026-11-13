import { Pagination } from '@/components/pagination';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { formatDateTime } from '@/lib/format';
import type { Option, Paginated } from '@/types';
import { router } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';

type Outcome = 'done' | 'rejected' | 'forbidden' | 'error';

interface Entry {
    id: number;
    at: string;
    who: string;
    role: string | null;
    action: string;
    label: string;
    subject: string | null;
    outcome: Outcome;
    input: Record<string, unknown> | null;
    ip: string | null;
    path: string | null;
}

interface Filters {
    user: number | null;
    action: string | null;
    outcome: Outcome | null;
    from: string | null;
    to: string | null;
}

interface AuditLogProps {
    entries: Paginated<Entry>;
    filters: Filters;
    staff: { id: number; name: string }[];
    actions: Option[];
    timezone: string;
}

const outcomes: Record<Outcome, { label: string; tone: NonNullable<BadgeProps['tone']> }> = {
    done: { label: 'Done', tone: 'success' },
    rejected: { label: 'Rejected', tone: 'warning' },
    forbidden: { label: 'Not allowed', tone: 'danger' },
    error: { label: 'Error', tone: 'danger' },
};

export default function AuditLog({ entries, filters, staff, actions, timezone }: AuditLogProps) {
    const apply = (next: Partial<Filters>) =>
        router.get(
            route('admin.audit-log'),
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(([, value]) => value !== null && value !== ''),
            ),
            { preserveState: true, replace: true },
        );

    return (
        <AdminLayout title="Audit log">
            <p className="mb-4 max-w-3xl text-sm text-muted">
                Every change made by admins, lab staff and collectors, and staff sign-ins. Customers' own actions aren't
                listed.
            </p>

            <div className="mb-4 grid grid-cols-2 items-end gap-3 lg:flex lg:flex-wrap">
                <div className="space-y-1.5">
                    <Label htmlFor="who">Person</Label>
                    <Select
                        id="who"
                        className="w-full lg:w-48"
                        value={filters.user ?? ''}
                        onChange={(e) => apply({ user: e.target.value ? Number(e.target.value) : null })}
                    >
                        <option value="">Everyone</option>
                        {staff.map((person) => (
                            <option key={person.id} value={person.id}>
                                {person.name}
                            </option>
                        ))}
                    </Select>
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="outcome">Outcome</Label>
                    <Select
                        id="outcome"
                        className="w-full lg:w-36"
                        value={filters.outcome ?? ''}
                        onChange={(e) => apply({ outcome: (e.target.value || null) as Outcome | null })}
                    >
                        <option value="">All</option>
                        {(Object.keys(outcomes) as Outcome[]).map((outcome) => (
                            <option key={outcome} value={outcome}>
                                {outcomes[outcome].label}
                            </option>
                        ))}
                    </Select>
                </div>
                <div className="col-span-2 space-y-1.5 lg:col-span-1">
                    <Label htmlFor="action">Action</Label>
                    <Select
                        id="action"
                        className="w-full lg:w-64"
                        value={filters.action ?? ''}
                        onChange={(e) => apply({ action: e.target.value || null })}
                    >
                        <option value="">All actions</option>
                        {actions.map((action) => (
                            <option key={action.value} value={action.value}>
                                {action.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="from">From</Label>
                    <Input
                        id="from"
                        type="date"
                        className="w-full lg:w-40"
                        value={filters.from ?? ''}
                        onChange={(e) => apply({ from: e.target.value || null })}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="to">To</Label>
                    <Input
                        id="to"
                        type="date"
                        className="w-full lg:w-40"
                        value={filters.to ?? ''}
                        onChange={(e) => apply({ to: e.target.value || null })}
                    />
                </div>
            </div>

            {entries.data.length === 0 ? (
                <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <ScrollText className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">Nothing recorded for these filters.</p>
                </div>
            ) : (
                <div className="space-y-4">
                    <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                        {entries.data.map((entry) => (
                            <li key={entry.id} className="p-4 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">{entry.label}</span>
                                    {entry.subject && <span className="text-muted">{entry.subject}</span>}
                                    <Badge tone={outcomes[entry.outcome].tone}>{outcomes[entry.outcome].label}</Badge>
                                </div>
                                <p className="mt-0.5 text-muted">
                                    {formatDateTime(entry.at, timezone)} · {entry.who}
                                    {entry.role && ` (${entry.role})`}
                                    {entry.ip && ` · ${entry.ip}`}
                                </p>
                                {(entry.input || entry.path) && (
                                    <details className="mt-2">
                                        <summary className="cursor-pointer text-primary-700">Details</summary>
                                        {entry.path && (
                                            <p className="mt-1 font-mono text-xs text-muted">{entry.path}</p>
                                        )}
                                        {entry.input && (
                                            <pre className="mt-1 max-h-64 overflow-auto rounded-control bg-canvas p-2 text-xs break-words whitespace-pre-wrap">
                                                {JSON.stringify(entry.input, null, 2)}
                                            </pre>
                                        )}
                                    </details>
                                )}
                            </li>
                        ))}
                    </ul>
                    <Pagination meta={entries.meta} links={entries.links} />
                </div>
            )}
        </AdminLayout>
    );
}
