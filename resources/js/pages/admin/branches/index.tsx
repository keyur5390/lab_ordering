import { addressLines } from '@/components/address-lines';
import { DeleteButton } from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { Branch } from '@/types';
import { Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

const newButtonClass =
    'inline-flex h-11 items-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700';

export default function BranchesIndex({ branches }: { branches: Branch[] }) {
    return (
        <AdminLayout
            title="Branches"
            actions={
                <Link href={route('admin.branches.create')} className={newButtonClass}>
                    New branch
                </Link>
            }
        >
            <p className="mb-4 max-w-3xl text-sm text-muted">
                Customers can book a lab visit at a published branch that has opening hours and slots.
            </p>
            {branches.length === 0 ? (
                <div className="flex max-w-3xl flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <Building2 className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No branches yet. Customers can only book home collection.</p>
                </div>
            ) : (
                <ul className="max-w-3xl divide-y divide-border rounded-card border border-border bg-surface">
                    {branches.map((branch) => (
                        <li key={branch.id} className="flex flex-wrap items-start justify-between gap-3 p-4">
                            <div className="flex min-w-0 gap-3">
                                {branch.photo_url && (
                                    <img
                                        src={branch.photo_url}
                                        alt=""
                                        className="size-14 shrink-0 rounded-control object-cover"
                                    />
                                )}
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <TextLink href={route('admin.branches.edit', branch.id)}>
                                            {branch.name}
                                        </TextLink>
                                        {branch.is_active ? (
                                            <Badge tone="success">Published</Badge>
                                        ) : (
                                            <Badge>Hidden</Badge>
                                        )}
                                        {!branch.has_schedule && <Badge tone="warning">No slots yet</Badge>}
                                    </div>
                                    <p className="text-sm text-muted">{addressLines(branch).join(', ')}</p>
                                    <TextLink
                                        href={route('admin.branches.schedule.edit', branch.id)}
                                        className="text-sm"
                                    >
                                        Opening hours and slots
                                    </TextLink>
                                </div>
                            </div>
                            <DeleteButton
                                href={route('admin.branches.destroy', branch.id)}
                                itemName={branch.name}
                                itemType="branch"
                                description="Branches with bookings can't be deleted; unpublish them instead."
                            />
                        </li>
                    ))}
                </ul>
            )}
        </AdminLayout>
    );
}
