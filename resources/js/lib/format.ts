import { locale, plural } from '@/lib/i18n';

/** Human-readable turnaround, e.g. 6 → "6 hours", 48 → "2 days". */
export function formatTurnaround(hours: number): string {
    if (hours >= 24 && hours % 24 === 0) {
        const days = hours / 24;

        return plural(days, ':count day', ':count days');
    }

    return plural(hours, ':count hour', ':count hours');
}

/**
 * A calendar date (YYYY-MM-DD) for display, e.g. "Mon 3 Nov". Formatted in UTC so the date
 * never shifts with the viewer's timezone.
 */
export function formatDate(
    date: string,
    options: Intl.DateTimeFormatOptions = { weekday: 'short', day: 'numeric', month: 'short' },
): string {
    return new Intl.DateTimeFormat(locale(), { ...options, timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
}

/**
 * A collection slot in the site timezone, e.g. "Fri 30 Oct, 07:00–07:30".
 * The ISO strings carry the site's offset; `timeZone` keeps the display in that zone.
 */
export function formatSlot(startsAt: string, endsAt: string, timeZone: string): string {
    const date = new Intl.DateTimeFormat(locale(), {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        timeZone,
    }).format(new Date(startsAt));

    return `${date}, ${formatTime(startsAt, timeZone)}–${formatTime(endsAt, timeZone)}`;
}

/** A time of day in the given timezone, e.g. "07:30". */
export function formatTime(iso: string, timeZone: string): string {
    return new Intl.DateTimeFormat(locale(), { hour: '2-digit', minute: '2-digit', hour12: false, timeZone }).format(
        new Date(iso),
    );
}

/** A moment in the given timezone, e.g. "Fri 30 Oct, 07:30". */
export function formatDateTime(iso: string, timeZone: string): string {
    const date = new Intl.DateTimeFormat(locale(), {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        timeZone,
    }).format(new Date(iso));

    return `${date}, ${formatTime(iso, timeZone)}`;
}
