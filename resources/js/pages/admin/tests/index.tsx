import { DeleteButton } from '@/components/delete-button';
import { Price } from '@/components/catalogue/price';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import { formatTurnaround } from '@/lib/format';
import type { LabTest, Paginated } from '@/types';
import { Link } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';

const newButtonClass =
    'inline-flex h-11 items-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700';

function StatusBadge({ active }: { active: boolean }) {
    return active ? <Badge tone="success">Published</Badge> : <Badge>Hidden</Badge>;
}

export default function TestsIndex({ tests }: { tests: Paginated<LabTest> }) {
    return (
        <AdminLayout
            title="Tests"
            actions={
                <Link href={route('admin.tests.create')} className={newButtonClass}>
                    New test
                </Link>
            }
        >
            {tests.data.length === 0 ? (
                <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <FlaskConical className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No tests yet.</p>
                    <Link href={route('admin.tests.create')} className={`${newButtonClass} mt-4`}>
                        Add your first test
                    </Link>
                </div>
            ) : (
                <div className="space-y-4">
                    {/* Desktop: table. Mobile: stacked cards (DESIGN.md §5). */}
                    <div className="hidden overflow-hidden rounded-card border border-border bg-surface md:block">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border bg-canvas text-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Name</th>
                                    <th className="px-4 py-3 font-medium">Code</th>
                                    <th className="px-4 py-3 font-medium">Turnaround</th>
                                    <th className="px-4 py-3 font-medium">Price</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {tests.data.map((test) => (
                                    <tr key={test.id}>
                                        <td className="px-4 py-3 font-medium">
                                            <TextLink href={route('admin.tests.edit', test.slug)}>{test.name}</TextLink>
                                        </td>
                                        <td className="px-4 py-3 text-muted">{test.code}</td>
                                        <td className="px-4 py-3">{formatTurnaround(test.turnaround_hours)}</td>
                                        <td className="px-4 py-3">
                                            <Price amount={test.price_amount} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge active={test.is_active} />
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <DeleteButton
                                                href={route('admin.tests.destroy', test.slug)}
                                                itemName={test.name}
                                                itemType="test"
                                                description="To hide it from customers instead, unpublish it."
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <ul className="space-y-3 md:hidden">
                        {tests.data.map((test) => (
                            <li key={test.id} className="rounded-card border border-border bg-surface p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <TextLink href={route('admin.tests.edit', test.slug)}>{test.name}</TextLink>
                                    <StatusBadge active={test.is_active} />
                                </div>
                                <p className="mt-1 text-sm text-muted">
                                    {test.code} · {formatTurnaround(test.turnaround_hours)}
                                </p>
                                <div className="mt-2 flex items-center justify-between">
                                    <Price amount={test.price_amount} />
                                    <DeleteButton
                                        href={route('admin.tests.destroy', test.slug)}
                                        itemName={test.name}
                                        itemType="test"
                                        description="To hide it from customers instead, unpublish it."
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>

                    <Pagination meta={tests.meta} links={tests.links} />
                </div>
            )}
        </AdminLayout>
    );
}
