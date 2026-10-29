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
    /** The visitor's cart (guest or customer). */
    cart: CartSummary;
    [key: string]: unknown;
}

export type CartItemType = 'test' | 'package';

/** Mirrors CartService::summary(). */
export interface CartSummary {
    count: number;
    test_ids: number[];
    package_ids: number[];
}

/** Mirrors CartService::lines(). Money is in minor units. */
export interface CartLine {
    /** Cart item id (used to remove it). */
    id: number;
    type: CartItemType;
    /** Test or package id. */
    item_id: number;
    name: string;
    slug: string;
    /** False once an admin has unpublished the item; it's left out of the total. */
    available: boolean;
    price_amount: number;
    /** Packages: the tests' total, struck through when higher than the price. */
    original_amount: number | null;
    tests_count: number;
    /** Tests: names of packages in the cart that already include this test. */
    included_in: string[];
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

export type PatientRelationship = 'self' | 'spouse' | 'child' | 'parent' | 'other';
export type Sex = 'male' | 'female' | 'other';

/** Mirrors App\Http\Resources\PatientResource. */
export interface Patient {
    id: number;
    name: string;
    relationship: PatientRelationship;
    relationship_label: string;
    /** YYYY-MM-DD */
    date_of_birth: string | null;
    age: number | null;
    sex: Sex | null;
    sex_label: string | null;
    phone: string | null;
    email: string | null;
    is_self: boolean;
    /** Has date of birth and sex, so it can be booked. */
    is_complete: boolean;
}

/** A value/label pair for selects and radio groups. */
export interface Option<T extends string = string> {
    value: T;
    label: string;
}
