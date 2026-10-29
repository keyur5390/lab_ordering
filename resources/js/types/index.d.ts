/** Mirrors App\Enums\UserRole. */
export type UserRole = 'customer' | 'admin';

/** Mirrors App\Http\Resources\UserResource. */
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
    role: UserRole;
    can_access_admin: boolean;
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
    /** Site currency (Admin → Settings); null until an admin chooses one. */
    currency: Currency | null;
    [key: string]: unknown;
}

/** Mirrors the `currency` shared prop. */
export interface Currency {
    code: string;
    /** Minor-unit digits, e.g. 2 for paise/cents. */
    decimals: number;
}

/** Mirrors App\Http\Resources\LabTestResource. Money is in minor units. */
export interface LabTest {
    id: number;
    name: string;
    code: string;
    slug: string;
    description: string | null;
    sample_type: string;
    preparation: string | null;
    turnaround_hours: number;
    price_amount: number;
    is_active: boolean;
}

export type PackagePricingMode = 'fixed' | 'discounted';
export type DiscountType = 'percent' | 'amount';

/** Mirrors App\Http\Resources\LabPackageResource. Money is in minor units. */
export interface LabPackage {
    id: number;
    name: string;
    code: string;
    slug: string;
    description: string | null;
    pricing_mode: PackagePricingMode;
    fixed_price_amount: number | null;
    discount_type: DiscountType | null;
    discount_value: number | null;
    /** What the customer pays. */
    price_amount: number;
    /** What the tests would cost separately. */
    tests_total_amount: number;
    turnaround_hours: number;
    is_active: boolean;
    tests_count: number;
    tests?: LabTest[];
}

/** Shape of a paginated Laravel resource collection. */
export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; from: number | null; to: number | null; total: number };
}
