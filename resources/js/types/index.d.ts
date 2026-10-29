/** Mirrors App\Http\Resources\UserResource. */
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
}

/**
 * Shared props every Inertia page receives (see HandleInertiaRequests::share).
 */
export interface SharedData {
    name: string;
    auth: {
        user: User | null;
    };
    /** One-off status message flashed by the backend (e.g. "verification-link-sent"). */
    status: string | null;
    [key: string]: unknown;
}
