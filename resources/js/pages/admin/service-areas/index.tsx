import { DeleteButton } from '@/components/delete-button';
import { Badge } from '@/components/ui/badge';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { ServiceArea } from '@/types';
import { Link } from '@inertiajs/react';
import { MapPinned } from 'lucide-react';

const newButtonClass =
    'inline-flex h-11 items-center rounded-control bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700';

function summary(area: ServiceArea): string {
    switch (area.type) {
        case 'postcodes':
            return `${area.postcodes_count ?? 0} postcodes · ${area.country_name}`;
        case 'radius':
            return `${area.radius_km} km radius`;
        case 'polygon':
            return `${area.polygon?.length ?? 0}-corner zone`;
    }
}

export default function ServiceAreasIndex({ areas }: { areas: ServiceArea[] }) {
    return (
        <AdminLayout
            title="Service areas"
            actions={
                <Link href={route('admin.service-areas.create')} className={newButtonClass}>
                    New area
                </Link>
            }
        >
            <p className="mb-4 text-sm text-muted">
                Home collection is available at an address inside any published area.
            </p>

            {areas.length === 0 ? (
                <div className="flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <MapPinned className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">
                        No service areas yet, so no address is eligible for home collection.
                    </p>
                    <Link href={route('admin.service-areas.create')} className={`${newButtonClass} mt-4`}>
                        Add your first area
                    </Link>
                </div>
            ) : (
                <ul className="divide-y divide-border rounded-card border border-border bg-surface">
                    {areas.map((area) => (
                        <li key={area.id} className="flex items-center justify-between gap-3 p-4">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <TextLink href={route('admin.service-areas.edit', area.id)}>{area.name}</TextLink>
                                    {area.is_active ? <Badge tone="success">Published</Badge> : <Badge>Hidden</Badge>}
                                    {!area.has_schedule && <Badge tone="warning">No schedule</Badge>}
                                </div>
                                <p className="text-sm text-muted">
                                    {area.type_label} · {summary(area)}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                <Link
                                    href={route('admin.service-areas.schedule.edit', area.id)}
                                    className="inline-flex h-11 items-center rounded-control px-3 text-sm font-medium text-primary-700 hover:bg-canvas sm:h-9"
                                    aria-label={`Schedule for ${area.name}`}
                                >
                                    Schedule
                                </Link>
                                <DeleteButton
                                    href={route('admin.service-areas.destroy', area.id)}
                                    itemName={area.name}
                                    itemType="area"
                                    description="Addresses inside it will no longer be eligible for home collection unless another area covers them."
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </AdminLayout>
    );
}
