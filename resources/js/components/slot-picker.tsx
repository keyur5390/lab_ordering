import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SlotDay, SlotStatus } from '@/types';
import { CalendarX } from 'lucide-react';
import { useId, useState } from 'react';

const unavailableLabel: Record<Exclude<SlotStatus, 'available'>, string> = {
    full: 'Full',
    closed: 'Closed',
    too_soon: 'Too soon',
};

interface SlotPickerProps {
    days: SlotDay[];
    /** Selected slot's starts_at. */
    value?: string | null;
    onChange?: (startsAt: string) => void;
    /** Preview only: slots can't be selected. */
    readOnly?: boolean;
}

/**
 * Date strip, then a grid of times for the chosen date (DESIGN.md §6). Unavailable slots stay
 * visible but disabled, with the reason.
 */
export function SlotPicker({ days, value = null, onChange, readOnly = false }: SlotPickerProps) {
    const labelId = useId();
    const firstOpenDate =
        days.find((day) => day.slots.some((slot) => slot.status === 'available'))?.date ?? days[0]?.date ?? null;
    const selectedDate = value ? days.find((day) => day.slots.some((slot) => slot.starts_at === value))?.date : null;
    const [date, setDate] = useState<string | null>(selectedDate ?? firstOpenDate);
    const day = days.find((d) => d.date === date);

    if (days.length === 0) {
        return (
            <div className="flex flex-col items-center rounded-card border border-border bg-surface p-6 text-center">
                <CalendarX className="size-6 text-muted" strokeWidth={1.75} aria-hidden="true" />
                <p className="mt-2 text-sm text-muted">No collection slots are available.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div role="radiogroup" aria-label="Date" className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                {days.map((d) => {
                    const open = d.slots.filter((slot) => slot.status === 'available').length;

                    return (
                        <button
                            key={d.date}
                            type="button"
                            role="radio"
                            aria-checked={d.date === date}
                            onClick={() => setDate(d.date)}
                            className={cn(
                                'flex min-h-16 min-w-16 shrink-0 flex-col items-center justify-center rounded-control border px-2 py-1.5 text-sm',
                                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                                d.date === date
                                    ? 'border-primary-600 bg-primary-50 text-primary-700'
                                    : 'border-border bg-surface hover:bg-canvas',
                                open === 0 && d.date !== date && 'text-muted',
                            )}
                        >
                            <span className="text-xs font-medium">{formatDate(d.date, { weekday: 'short' })}</span>
                            <span className="font-semibold whitespace-nowrap">
                                {formatDate(d.date, { day: 'numeric', month: 'short' })}
                            </span>
                            <span className="text-xs text-muted">{open > 0 ? `${open} open` : 'None'}</span>
                        </button>
                    );
                })}
            </div>

            {day && (
                <div>
                    <p id={labelId} className="mb-2 text-sm font-medium">
                        {formatDate(day.date, { weekday: 'long', day: 'numeric', month: 'long' })}
                    </p>
                    {day.closed_reason ? (
                        <p className="rounded-control border border-border bg-canvas p-3 text-sm text-muted">
                            Closed: {day.closed_reason}
                        </p>
                    ) : day.slots.length === 0 ? (
                        <p className="rounded-control border border-border bg-canvas p-3 text-sm text-muted">
                            No collections on this day.
                        </p>
                    ) : (
                        <div
                            role="radiogroup"
                            aria-labelledby={labelId}
                            className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6"
                        >
                            {day.slots.map((slot) => {
                                const available = slot.status === 'available';
                                const selected = slot.starts_at === value;

                                return (
                                    <button
                                        key={slot.starts_at}
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        disabled={!available || readOnly}
                                        onClick={() => onChange?.(slot.starts_at)}
                                        className={cn(
                                            'flex min-h-11 flex-col items-center justify-center rounded-control border px-2 py-1.5 text-sm tabular-nums',
                                            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                                            selected
                                                ? 'border-primary-600 bg-primary-600 text-white'
                                                : available
                                                  ? 'border-border bg-surface hover:border-primary-600'
                                                  : 'cursor-not-allowed border-dashed border-border bg-canvas text-muted',
                                            readOnly && available && 'cursor-default hover:border-border',
                                        )}
                                    >
                                        <span className="font-medium">{slot.time}</span>
                                        {!available && (
                                            <span className="text-xs">
                                                {unavailableLabel[slot.status as Exclude<SlotStatus, 'available'>]}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
