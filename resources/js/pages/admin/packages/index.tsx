import { DeleteButton } from '@/components/delete-button';
import { Price } from '@/components/catalogue/price';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { LabPackage, Paginated } from '@/types';
import { Link } from '@inertiajs/react';
import { Package } from 'lucide-react';

const newButtonClass =
    'inline-flex h-11 items-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700';

function StatusBadge({ active }: { active: boolean }) {
    return active ? <Badge tone="success">Published</Badge> : <Badge>Hidden</Badge>;
}

function pricingLabel(pkg: LabPackage): string {
    if (pkg.pricing_mode === 'fixed') return 'Fixed price';

    return pkg.discount_type === 'percent' ? `${pkg.discount_value}% off tests` : 'Amount off tests';
}

export default function PackagesIndex({ packages }: { packages: Paginated<LabPackage> }) {
    return (
        <AdminLayout
            title="Packages"
            actions={
                <Link href={route('admin.packages.create')} className={newButtonClass}>
                    New package
                </Link>
            }
        >
            {packages.data.length === 0 ? (
                <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <Package className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No packages yet.</p>
                    <Link href={route('admin.packages.create')} className={`${newButtonClass} mt-4`}>
                        Add your first package
                    </Link>
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="hidden overflow-hidden rounded-card border border-border bg-surface md:block">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border bg-canvas text-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Name</th>
                                    <th className="px-4 py-3 font-medium">Code</th>
                                    <th className="px-4 py-3 font-medium">Tests</th>
                                    <th className="px-4 py-3 font-medium">Pricing</th>
                                    <th className="px-4 py-3 font-medium">Price</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {packages.data.map((pkg) => (
                                    <tr key={pkg.id}>
                                        <td className="px-4 py-3 font-medium">
                                            <TextLink href={route('admin.packages.edit', pkg.slug)}>
                                                {pkg.name}
                                            </TextLink>
                                        </td>
                                        <td className="px-4 py-3 text-muted">{pkg.code}</td>
                                        <td className="px-4 py-3">{pkg.tests_count}</td>
                                        <td className="px-4 py-3 text-muted">{pricingLabel(pkg)}</td>
                                        <td className="px-4 py-3">
                                            <Price amount={pkg.price_amount} originalAmount={pkg.tests_total_amount} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge active={pkg.is_active} />
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <DeleteButton
                                                href={route('admin.packages.destroy', pkg.slug)}
                                                itemName={pkg.name}
                                                itemType="package"
                                                description="To hide it from customers instead, unpublish it."
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <ul className="space-y-3 md:hidden">
                        {packages.data.map((pkg) => (
                            <li key={pkg.id} className="rounded-card border border-border bg-surface p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <TextLink href={route('admin.packages.edit', pkg.slug)}>{pkg.name}</TextLink>
                                    <StatusBadge active={pkg.is_active} />
                                </div>
                                <p className="mt-1 text-sm text-muted">
                                    {pkg.code} · {pkg.tests_count} tests · {pricingLabel(pkg)}
                                </p>
                                <div className="mt-2 flex items-center justify-between">
                                    <Price amount={pkg.price_amount} originalAmount={pkg.tests_total_amount} />
                                    <DeleteButton
                                        href={route('admin.packages.destroy', pkg.slug)}
                                        itemName={pkg.name}
                                        itemType="package"
                                        description="To hide it from customers instead, unpublish it."
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>

                    <Pagination meta={packages.meta} links={packages.links} />
                </div>
            )}
        </AdminLayout>
    );
}
