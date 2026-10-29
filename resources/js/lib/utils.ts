import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/** Merge class names, letting later Tailwind classes override earlier ones. */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

/** A design token's current value, e.g. cssVar('--color-primary-600') → "#1570ef" (for SVG/canvas attributes). */
export function cssVar(name: string): string {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}
