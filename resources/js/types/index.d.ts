/**
 * Shared props every Inertia page receives (see HandleInertiaRequests::share).
 */
export interface SharedData {
    name: string;
    [key: string]: unknown;
}
