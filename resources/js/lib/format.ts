/** Human-readable turnaround, e.g. 6 → "6 hours", 48 → "2 days". */
export function formatTurnaround(hours: number): string {
    if (hours >= 24 && hours % 24 === 0) {
        const days = hours / 24;

        return `${days} ${days === 1 ? 'day' : 'days'}`;
    }

    return `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
}

/**
 * A calendar date (YYYY-MM-DD) for display, e.g. "Mon 3 Nov". Formatted in UTC so the date
 * never shifts with the viewer's timezone.
 */
export function formatDate(
    date: string,
    options: Intl.DateTimeFormatOptions = { weekday: 'short', day: 'numeric', month: 'short' },
): string {
    return new Intl.DateTimeFormat(undefined, { ...options, timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
}
