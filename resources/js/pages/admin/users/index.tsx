import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { Option, Paginated } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface UserSummary {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    disabled: boolean;
    orders_count: number;
}

interface UsersIndexProps {
    users: Paginated<UserSummary>;
    filters: { q: string; role: string | null; status: string | null };
    roles: Option[];
}

const newButtonClass =
    'inline-flex h-11 items-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700';

export default function UsersIndex({ users, filters, roles }: UsersIndexProps) {
    const [q, setQ] = useState(filters.q);
    const first = useRef(true);

    const apply = (next: Partial<UsersIndexProps['filters']>) => {
        const merged = { ...filters, q, ...next };
        router.get(
            route('admin.users.index'),
            Object.fromEntries(Object.entries(merged).filter(([, value]) => value)),
            { preserveState: true, replace: true },
        );
    };

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => apply({ q }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    return (
        <AdminLayout
            title="Users"
            actions={
                <Link href={route('admin.users.create')} className={newButtonClass}>
                    Invite staff
                </Link>
            }
        >
            <div className="mb-4 grid grid-cols-2 items-end gap-3 sm:flex sm:flex-wrap">
                <div className="col-span-2 space-y-1.5 sm:w-72">
                    <Label htmlFor="user-search">Search</Label>
                    <Input
                        id="user-search"
                        type="search"
                        placeholder="Name or email"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="role-filter">Role</Label>
                    <Select
                        id="role-filter"
                        className="w-full sm:w-44"
                        value={filters.role ?? ''}
                        onChange={(e) => apply({ role: e.target.value || null })}
                    >
                        <option value="">All roles</option>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="status-filter">Status</Label>
                    <Select
                        id="status-filter"
                        className="w-full sm:w-36"
                        value={filters.status ?? ''}
                        onChange={(e) => apply({ status: e.target.value || null })}
                    >
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                    </Select>
                </div>
            </div>

            {users.data.length === 0 ? (
                <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <Users className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No users match.</p>
                </div>
            ) : (
                <div className="space-y-4">
                    <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                        {users.data.map((user) => (
                            <li key={user.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <TextLink href={route('admin.users.show', user.id)}>{user.name}</TextLink>
                                        <Badge tone={user.role === 'customer' ? 'neutral' : 'info'}>
                                            {user.role_label}
                                        </Badge>
                                        {user.disabled && <Badge tone="danger">Disabled</Badge>}
                                    </div>
                                    <p className="truncate text-sm text-muted">{user.email}</p>
                                </div>
                                <p className="text-sm text-muted">
                                    {user.orders_count} {user.orders_count === 1 ? 'order' : 'orders'}
                                </p>
                            </li>
                        ))}
                    </ul>
                    <Pagination meta={users.meta} links={users.links} />
                </div>
            )}
        </AdminLayout>
    );
}
