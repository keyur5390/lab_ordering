/** Human-readable turnaround, e.g. 6 → "6 hours", 48 → "2 days". */
export function formatTurnaround(hours: number): string {
    if (hours >= 24 && hours % 24 === 0) {
        const days = hours / 24;

        return `${days} ${days === 1 ? 'day' : 'days'}`;
    }

    return `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
}
