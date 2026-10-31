import { addressLines } from '@/components/address-lines';
import { Price } from '@/components/catalogue/price';
import { OrderStatusBadge } from '@/components/order-status-badge';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { formatDate, formatSlot } from '@/lib/format';
import type { Address, Option, Order, Patient, SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import type { UserSummary } from './index';

interface UserShowProps {
    user: UserSummary & { email_verified: boolean; created_at: string; is_self: boolean };
    patients: Patient[];
    addresses: Address[];
    orders: Order[];
    roles: Option[];
}

function Card({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-card border border-border bg-surface p-4 md:p-6">
            <h2 className="text-base font-semibold">{title}</h2>
            <div className="mt-3">{children}</div>
        </section>
    );
}

export default function UserShow({ user, patients, addresses, orders, roles }: UserShowProps) {
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const { data, setData, put, processing, isDirty } = useForm({ role: user.role });
    const [confirming, setConfirming] = useState(false);
    const [toggling, setToggling] = useState(false);

    const saveRole = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.users.role', user.id), { preserveScroll: true });
    };

    const toggle = () =>
        router.post(
            route(user.disabled ? 'admin.users.enable' : 'admin.users.disable', user.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setToggling(true),
                onFinish: () => {
                    setToggling(false);
                    setConfirming(false);
                },
            },
        );

    return (
        <AdminLayout title={user.name}>
            <TextLink href={route('admin.users.index')} className="text-sm">
                ← Users
            </TextLink>

            <div className="mt-4 grid max-w-5xl gap-6 lg:grid-cols-2 lg:items-start">
                <div className="space-y-6">
                    <Card title="Account">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge tone={user.role === 'customer' ? 'neutral' : 'info'}>{user.role_label}</Badge>
                            {user.disabled ? (
                                <Badge tone="danger">Disabled</Badge>
                            ) : (
                                <Badge tone="success">Active</Badge>
                            )}
                            {!user.email_verified && <Badge tone="warning">Email not verified</Badge>}
                        </div>
                        <p className="mt-2 text-sm">{user.email}</p>
                        <p className="text-sm text-muted">
                            Joined {formatDate(user.created_at, { day: 'numeric', month: 'short', year: 'numeric' })}
                        </p>
                        {user.role === 'collector' && (
                            <p className="mt-2 text-sm">
                                <TextLink href={route('admin.collectors.index')}>
                                    Choose this collector's areas
                                </TextLink>
                            </p>
                        )}

                        {user.is_self ? (
                            <p className="mt-4 text-sm text-muted">
                                This is you: you can't change your own role or disable your account.
                            </p>
                        ) : (
                            <div className="mt-4 space-y-4 border-t border-border pt-4">
                                <form onSubmit={saveRole} className="flex flex-wrap items-end gap-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="role">Role</Label>
                                        <Select
                                            id="role"
                                            className="w-48"
                                            value={data.role}
                                            onChange={(e) => setData('role', e.target.value)}
                                        >
                                            {roles.map((role) => (
                                                <option key={role.value} value={role.value}>
                                                    {role.label}
                                                </option>
                                            ))}
                                        </Select>
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        processing={processing}
                                        disabled={!isDirty}
                                    >
                                        Change role
                                    </Button>
                                    <InputError message={errors.role} className="w-full" />
                                </form>
                                <div>
                                    <Button
                                        variant={user.disabled ? 'secondary' : 'ghost'}
                                        className={user.disabled ? undefined : 'text-danger-600'}
                                        onClick={() => (user.disabled ? toggle() : setConfirming(true))}
                                        processing={toggling && user.disabled}
                                    >
                                        {user.disabled ? 'Enable account' : 'Disable account'}
                                    </Button>
                                    <InputError message={errors.account} className="mt-1" />
                                </div>
                                <ConfirmDialog
                                    open={confirming}
                                    title={`Disable ${user.name}'s account?`}
                                    confirmLabel="Disable account"
                                    processing={toggling}
                                    onClose={() => setConfirming(false)}
                                    onConfirm={toggle}
                                >
                                    They will be signed out and can't sign in until the account is enabled again. Their
                                    orders and history stay.
                                </ConfirmDialog>
                            </div>
                        )}
                    </Card>

                    {patients.length > 0 && (
                        <Card title="Patients">
                            <ul className="divide-y divide-border text-sm">
                                {patients.map((patient) => (
                                    <li key={patient.id} className="py-2 first:pt-0 last:pb-0">
                                        <span className="font-medium">{patient.name}</span>
                                        <span className="text-muted">
                                            {' '}
                                            · {patient.relationship_label}
                                            {patient.date_of_birth &&
                                                ` · born ${formatDate(patient.date_of_birth, { day: 'numeric', month: 'short', year: 'numeric' })}`}
                                            {patient.phone && ` · ${patient.phone}`}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}

                    {addresses.length > 0 && (
                        <Card title="Addresses">
                            <ul className="divide-y divide-border text-sm">
                                {addresses.map((address) => (
                                    <li key={address.id} className="py-2 first:pt-0 last:pb-0">
                                        {address.label && <span className="font-medium">{address.label}: </span>}
                                        {addressLines(address).join(', ')}
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>

                <Card title={`Orders (${user.orders_count})`}>
                    {orders.length === 0 ? (
                        <p className="text-sm text-muted">No orders yet.</p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {orders.map((order) => (
                                <li
                                    key={order.number}
                                    className="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <TextLink href={route('admin.orders.show', order.number)}>
                                                {order.number}
                                            </TextLink>
                                            <OrderStatusBadge status={order.status} label={order.status_label} />
                                        </div>
                                        <p className="text-sm text-muted">
                                            {formatSlot(order.slot_starts_at, order.slot_ends_at, order.timezone)}
                                        </p>
                                    </div>
                                    <Price
                                        amount={order.total_amount}
                                        currencyCode={order.currency}
                                        className="text-sm"
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                    {user.orders_count > orders.length && (
                        <p className="mt-3 text-sm text-muted">Showing the latest {orders.length}.</p>
                    )}
                </Card>
            </div>
            {user.disabled && (
                <Alert tone="warning" className="mt-6 max-w-5xl">
                    This account is disabled: {user.name} can't sign in.
                </Alert>
            )}
        </AdminLayout>
    );
}
