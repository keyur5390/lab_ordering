import { DeleteButton } from '@/components/delete-button';
import { Field } from '@/components/field';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { formatDate } from '@/lib/format';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import type { FormEvent } from 'react';

interface Closure {
    id: number;
    /** Null when the closure applies to every area. */
    area: string | null;
    starts_on: string;
    ends_on: string;
    starts_at: string | null;
    ends_at: string | null;
    reason: string | null;
}

interface ClosuresProps {
    closures: Closure[];
    areas: Option[];
    branches: Option[];
    today: string;
}

function when(closure: Closure): string {
    const date = (d: string) => formatDate(d, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

    if (closure.starts_at) {
        return `${date(closure.starts_on)}, ${closure.starts_at}–${closure.ends_at}`;
    }

    return closure.starts_on === closure.ends_on
        ? `${date(closure.starts_on)}, all day`
        : `${date(closure.starts_on)} – ${date(closure.ends_on)}`;
}

export default function Closures({ closures, areas, branches, today }: ClosuresProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        service_area_id: '',
        branch_id: '',
        starts_on: '',
        ends_on: '',
        whole_day: true,
        starts_at: '',
        ends_at: '',
        reason: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.closures.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <AdminLayout title="Closures">
            <p className="mb-6 max-w-3xl text-sm text-muted">
                Holidays and other times when no home collections or lab visits happen. Slots inside a closure show as
                closed.
            </p>

            <form
                onSubmit={submit}
                className="max-w-3xl space-y-4 rounded-card border border-border bg-surface p-4 md:p-6"
                noValidate
            >
                <h2 className="text-base font-semibold">Add a closure</h2>

                <Field id="service_area_id" label="Applies to" error={errors.service_area_id ?? errors.branch_id}>
                    {(control) => (
                        <Select
                            {...control}
                            value={
                                data.branch_id
                                    ? `branch:${data.branch_id}`
                                    : data.service_area_id
                                      ? `area:${data.service_area_id}`
                                      : ''
                            }
                            onChange={(e) => {
                                const [kind, id] = e.target.value.split(':');
                                setData((form) => ({
                                    ...form,
                                    service_area_id: kind === 'area' ? (id ?? '') : '',
                                    branch_id: kind === 'branch' ? (id ?? '') : '',
                                }));
                            }}
                        >
                            <option value="">Everywhere (all areas and branches)</option>
                            <optgroup label="Service areas (home collection)">
                                {areas.map((area) => (
                                    <option key={area.value} value={`area:${area.value}`}>
                                        {area.label}
                                    </option>
                                ))}
                            </optgroup>
                            {branches.length > 0 && (
                                <optgroup label="Branches (lab visits)">
                                    {branches.map((branch) => (
                                        <option key={branch.value} value={`branch:${branch.value}`}>
                                            {branch.label}
                                        </option>
                                    ))}
                                </optgroup>
                            )}
                        </Select>
                    )}
                </Field>

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="whole_day"
                        checked={data.whole_day}
                        onChange={(e) => setData('whole_day', e.target.checked)}
                    />
                    <Label htmlFor="whole_day" className="font-normal">
                        Whole day(s)
                    </Label>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="starts_on"
                        label={data.whole_day ? 'From' : 'Date'}
                        type="date"
                        min={today}
                        value={data.starts_on}
                        onChange={(e) => setData('starts_on', e.target.value)}
                        error={errors.starts_on}
                    />
                    {data.whole_day ? (
                        <FormField
                            id="ends_on"
                            label="Until (optional)"
                            hint="Leave empty for a single day."
                            type="date"
                            min={data.starts_on || today}
                            value={data.ends_on}
                            onChange={(e) => setData('ends_on', e.target.value)}
                            error={errors.ends_on}
                        />
                    ) : (
                        <div className="grid grid-cols-2 gap-4">
                            <FormField
                                id="starts_at"
                                label="From"
                                type="time"
                                value={data.starts_at}
                                onChange={(e) => setData('starts_at', e.target.value)}
                                error={errors.starts_at}
                            />
                            <FormField
                                id="ends_at"
                                label="To"
                                type="time"
                                value={data.ends_at}
                                onChange={(e) => setData('ends_at', e.target.value)}
                                error={errors.ends_at ?? errors.ends_on}
                            />
                        </div>
                    )}
                </div>

                <FormField
                    id="reason"
                    label="Reason (optional)"
                    hint="Shown to customers, e.g. Diwali, Staff training."
                    value={data.reason}
                    onChange={(e) => setData('reason', e.target.value)}
                    error={errors.reason}
                />

                <Button type="submit" processing={processing}>
                    Add closure
                </Button>
            </form>

            <section className="mt-8 max-w-3xl">
                <h2 className="text-base font-semibold">Upcoming closures</h2>
                {closures.length === 0 ? (
                    <div className="mt-3 flex flex-col items-center rounded-card border border-border bg-surface p-8 text-center">
                        <CalendarCheck className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                        <p className="mt-3 text-sm text-muted">No upcoming closures.</p>
                    </div>
                ) : (
                    <ul className="mt-3 divide-y divide-border rounded-card border border-border bg-surface">
                        {closures.map((closure) => (
                            <li key={closure.id} className="flex items-center justify-between gap-3 p-4">
                                <div className="min-w-0">
                                    <p className="font-medium">{when(closure)}</p>
                                    <p className="text-sm text-muted">
                                        {closure.area ?? 'Everywhere'}
                                        {closure.reason && ` · ${closure.reason}`}
                                    </p>
                                </div>
                                <DeleteButton
                                    href={route('admin.closures.destroy', closure.id)}
                                    itemName={when(closure)}
                                    itemType="closure"
                                    verb="Remove"
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AdminLayout>
    );
}
