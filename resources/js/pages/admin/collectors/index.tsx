import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { InputError } from '@/components/ui/input-error';
import AdminLayout from '@/layouts/admin-layout';
import { useForm } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import type { FormEvent } from 'react';

interface Collector {
    id: number;
    name: string;
    email: string;
    area_ids: number[];
}

interface Area {
    id: number;
    name: string;
    is_active: boolean;
}

function CollectorAreas({ collector, areas }: { collector: Collector; areas: Area[] }) {
    const { data, setData, put, processing, errors, isDirty } = useForm({ area_ids: collector.area_ids });

    const toggle = (id: number, checked: boolean) =>
        setData('area_ids', checked ? [...data.area_ids, id] : data.area_ids.filter((areaId) => areaId !== id));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(route('admin.collectors.update', collector.id), { preserveScroll: true });
    };

    return (
        <li className="rounded-card border border-border bg-surface p-4 md:p-6">
            <form onSubmit={submit}>
                <fieldset>
                    <legend className="font-medium">
                        {collector.name} <span className="text-sm font-normal text-muted">· {collector.email}</span>
                    </legend>
                    <p className="mt-1 text-sm text-muted">Works in:</p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {areas.map((area) => (
                            <label
                                key={area.id}
                                className="flex min-h-11 cursor-pointer items-center gap-2 rounded-control border border-border px-3 text-sm has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50"
                            >
                                <Checkbox
                                    checked={data.area_ids.includes(area.id)}
                                    onChange={(e) => toggle(area.id, e.target.checked)}
                                />
                                {area.name}
                                {!area.is_active && <Badge>Hidden</Badge>}
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.area_ids} className="mt-1" />
                </fieldset>
                <Button
                    type="submit"
                    size="sm"
                    variant="secondary"
                    className="mt-3"
                    processing={processing}
                    disabled={!isDirty}
                >
                    Save areas
                </Button>
            </form>
        </li>
    );
}

export default function CollectorsIndex({ collectors, areas }: { collectors: Collector[]; areas: Area[] }) {
    return (
        <AdminLayout title="Collectors">
            <p className="mb-4 max-w-3xl text-sm text-muted">
                Collectors see the home visits in their areas and can take unassigned ones. To make someone a collector,
                run{' '}
                <code className="rounded bg-canvas px-1 py-0.5 text-xs">
                    php artisan user:role their@email collector
                </code>
                .
            </p>
            {collectors.length === 0 ? (
                <div className="flex max-w-3xl flex-col items-center rounded-card border border-border bg-surface p-8 text-center md:p-12">
                    <Truck className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                    <p className="mt-3 text-sm text-muted">No collectors yet.</p>
                </div>
            ) : (
                <ul className="max-w-3xl space-y-3">
                    {collectors.map((collector) => (
                        <CollectorAreas key={collector.id} collector={collector} areas={areas} />
                    ))}
                </ul>
            )}
        </AdminLayout>
    );
}
