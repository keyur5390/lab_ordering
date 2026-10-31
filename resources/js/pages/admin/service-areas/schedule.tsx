import { FormField } from '@/components/form-field';
import { SlotPicker } from '@/components/slot-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputError } from '@/components/ui/input-error';
import { TextLink } from '@/components/ui/text-link';
import AdminLayout from '@/layouts/admin-layout';
import type { SlotDay } from '@/types';
import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import type { FormEvent } from 'react';

interface HourRange {
    weekday: number;
    opens_at: string;
    closes_at: string;
}

interface ScheduleProps {
    /** A service area or a branch (B-17); both have the same schedule. */
    owner: { name: string; kind: 'area' | 'branch' };
    submitUrl: string;
    backUrl: string;
    backLabel: string;
    schedule: {
        slot_minutes: number | null;
        capacity_per_slot: number | null;
        lead_time_hours: number | null;
        booking_window_days: number | null;
        hours: HourRange[];
    };
    timezone: string;
    /** Slots as currently saved. */
    preview: SlotDay[];
}

const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

export default function Schedule({ owner, submitUrl, backUrl, backLabel, schedule, timezone, preview }: ScheduleProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        slot_minutes: schedule.slot_minutes?.toString() ?? '',
        capacity_per_slot: schedule.capacity_per_slot?.toString() ?? '',
        lead_time_hours: schedule.lead_time_hours?.toString() ?? '',
        booking_window_days: schedule.booking_window_days?.toString() ?? '',
        hours: schedule.hours,
    });
    const allErrors = errors as Record<string, string>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(submitUrl, { preserveScroll: true });
    };

    const updateRange = (index: number, field: 'opens_at' | 'closes_at', value: string) =>
        setData(
            'hours',
            data.hours.map((range, i) => (i === index ? { ...range, [field]: value } : range)),
        );

    return (
        <AdminLayout title={`Schedule: ${owner.name}`}>
            <TextLink href={backUrl} className="text-sm">
                ← {backLabel}
            </TextLink>

            <form onSubmit={submit} className="mt-4 max-w-3xl space-y-6" noValidate>
                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <div>
                        <h2 className="text-base font-semibold">Slots</h2>
                        <p className="mt-1 text-sm text-muted">
                            Times are in {timezone} (Admin → Settings). Without these, this{' '}
                            {owner.kind === 'branch' ? 'branch' : 'area'} offers no slots.
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="slot_minutes"
                            label="Slot length (minutes)"
                            type="number"
                            inputMode="numeric"
                            value={data.slot_minutes}
                            onChange={(e) => setData('slot_minutes', e.target.value)}
                            error={errors.slot_minutes}
                        />
                        <FormField
                            id="capacity_per_slot"
                            label="Collections per slot"
                            hint="How many homes can be visited in one slot."
                            type="number"
                            inputMode="numeric"
                            value={data.capacity_per_slot}
                            onChange={(e) => setData('capacity_per_slot', e.target.value)}
                            error={errors.capacity_per_slot}
                        />
                        <FormField
                            id="lead_time_hours"
                            label="Lead time (hours)"
                            hint="The earliest slot a customer can book, counted from now."
                            inputMode="decimal"
                            value={data.lead_time_hours}
                            onChange={(e) => setData('lead_time_hours', e.target.value)}
                            error={errors.lead_time_hours}
                        />
                        <FormField
                            id="booking_window_days"
                            label="Booking window (days)"
                            hint="How many days, starting today, customers can book."
                            type="number"
                            inputMode="numeric"
                            value={data.booking_window_days}
                            onChange={(e) => setData('booking_window_days', e.target.value)}
                            error={errors.booking_window_days}
                        />
                    </div>
                </section>

                <section className="space-y-4 rounded-card border border-border bg-surface p-4 md:p-6">
                    <div>
                        <h2 className="text-base font-semibold">Weekly hours</h2>
                        <p className="mt-1 text-sm text-muted">
                            Add one or more time ranges per day. Days without a range are closed.
                        </p>
                    </div>

                    <ul className="divide-y divide-border">
                        {weekdays.map((name, dayIndex) => {
                            const weekday = dayIndex + 1;
                            const ranges = data.hours
                                .map((range, index) => ({ range, index }))
                                .filter(({ range }) => range.weekday === weekday);

                            return (
                                <li key={weekday} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-start">
                                    <span className="w-28 shrink-0 pt-2.5 text-sm font-medium">{name}</span>
                                    <div className="flex-1 space-y-2">
                                        {ranges.length === 0 && <p className="pt-2.5 text-sm text-muted">Closed</p>}
                                        {ranges.map(({ range, index }) => (
                                            <div key={index}>
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        type="time"
                                                        aria-label={`${name} opens`}
                                                        className="min-w-0 flex-1 px-2 sm:w-36 sm:flex-none sm:px-3"
                                                        value={range.opens_at}
                                                        invalid={!!allErrors[`hours.${index}.opens_at`]}
                                                        onChange={(e) => updateRange(index, 'opens_at', e.target.value)}
                                                    />
                                                    <span className="shrink-0 text-sm text-muted">to</span>
                                                    <Input
                                                        type="time"
                                                        aria-label={`${name} closes`}
                                                        className="min-w-0 flex-1 px-2 sm:w-36 sm:flex-none sm:px-3"
                                                        value={range.closes_at}
                                                        invalid={!!allErrors[`hours.${index}.closes_at`]}
                                                        onChange={(e) =>
                                                            updateRange(index, 'closes_at', e.target.value)
                                                        }
                                                    />
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="w-11 px-0"
                                                        aria-label={`Remove ${name} ${range.opens_at}–${range.closes_at}`}
                                                        onClick={() =>
                                                            setData(
                                                                'hours',
                                                                data.hours.filter((_, i) => i !== index),
                                                            )
                                                        }
                                                    >
                                                        <X className="size-4" />
                                                    </Button>
                                                </div>
                                                <InputError
                                                    message={
                                                        allErrors[`hours.${index}.opens_at`] ??
                                                        allErrors[`hours.${index}.closes_at`]
                                                    }
                                                    className="mt-1"
                                                />
                                            </div>
                                        ))}
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="-ml-3 text-primary-700"
                                            onClick={() =>
                                                setData('hours', [
                                                    ...data.hours,
                                                    { weekday, opens_at: '', closes_at: '' },
                                                ])
                                            }
                                        >
                                            <Plus className="size-4" aria-hidden="true" />
                                            Add hours
                                        </Button>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </section>

                <Button type="submit" processing={processing} disabled={!isDirty}>
                    Save schedule
                </Button>
            </form>

            <section className="mt-10 max-w-3xl space-y-3">
                <div>
                    <h2 className="text-lg font-semibold">What customers see</h2>
                    <p className="mt-1 text-sm text-muted">
                        Preview of the saved schedule, including closures. {isDirty && 'Save to update it.'}
                    </p>
                </div>
                <div className="rounded-card border border-border bg-surface p-4 md:p-6">
                    <SlotPicker days={preview} readOnly />
                </div>
            </section>
        </AdminLayout>
    );
}
