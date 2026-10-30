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
    /** Lab area and read-only order pages (lab staff and admins). */
    can_access_lab: boolean;
    /** The collector's visits screen. */
    can_access_collector: boolean;
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
    /** The test, or a test in the package, needs a prescription. */
    requires_prescription: boolean;
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
    requires_prescription: boolean;
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

/** [latitude, longitude] */
export type LatLng = [number, number];

/** Mirrors App\Support\MapConfig. */
export interface MapConfig {
    tile_url: string;
    attribution: string;
    center: LatLng;
    zoom: number;
}

/** Mirrors App\Http\Resources\AddressResource. */
export interface Address {
    id: number;
    label: string | null;
    line1: string;
    line2: string | null;
    city: string;
    region: string | null;
    postcode: string;
    /** ISO 3166-1 alpha-2 */
    country: string;
    country_name: string;
    latitude: number | null;
    longitude: number | null;
    /** Home collection is available here. */
    serviceable: boolean;
}

export type ServiceAreaType = 'postcodes' | 'radius' | 'polygon';

/** Mirrors App\Http\Resources\ServiceAreaResource. */
export interface ServiceArea {
    id: number;
    name: string;
    type: ServiceAreaType;
    type_label: string;
    country: string | null;
    country_name: string | null;
    postcodes_count?: number;
    postcodes?: string[];
    center: LatLng | null;
    radius_km: number | null;
    polygon: LatLng[] | null;
    is_active: boolean;
    /** Has slot settings, so it offers collection slots. */
    has_schedule: boolean;
    /** Home collection fee, as a decimal for the form and in minor units. */
    collection_fee: string;
    collection_fee_amount: number;
}

export type SlotStatus = 'available' | 'full' | 'closed' | 'too_soon';

/** Mirrors one slot from App\Services\SlotService::days(). */
export interface Slot {
    /** ISO-8601 with the site timezone's offset. */
    starts_at: string;
    ends_at: string;
    /** Local start time, HH:MM. */
    time: string;
    remaining: number;
    status: SlotStatus;
}

/** Mirrors one day from App\Services\SlotService::days(). */
export interface SlotDay {
    /** YYYY-MM-DD in the site timezone. */
    date: string;
    /** Set when a whole-day closure applies. */
    closed_reason: string | null;
    slots: Slot[];
}

export type OrderStatus =
    | 'pending_payment'
    | 'confirmed'
    | 'collector_assigned'
    | 'sample_collected'
    | 'received_at_lab'
    | 'report_ready'
    | 'completed'
    | 'cancelled'
    | 'refunded';

/** Mirrors App\Http\Resources\OrderResource. Money in minor units of `currency`; times in the site timezone. */
export interface Order {
    number: string;
    status: OrderStatus;
    status_label: string;
    payment_method: PaymentMethod;
    payment_method_label: string;
    /** While pending payment: when the slot hold ends (site timezone). */
    payment_expires_at: string | null;
    slot_starts_at: string;
    slot_ends_at: string;
    placed_at: string;
    timezone: string;
    address: {
        label: string | null;
        line1: string;
        line2: string | null;
        city: string;
        region: string | null;
        postcode: string;
        country: string;
        country_name: string;
    };
    currency: string;
    subtotal_amount: number;
    total_amount: number;
    coupon_code: string | null;
    discount_amount: number;
    collection_fee_amount: number;
    /** Null when no tax applied. */
    tax_rate_percent: number | null;
    tax_inclusive: boolean;
    tax_amount: number;
    items_count?: number;
    customer?: { name: string; email: string };
    patients?: {
        name: string;
        date_of_birth: string;
        sex: string;
        items: {
            id: number;
            type: CartItemType;
            name: string;
            code: string;
            test_names: string[] | null;
            price_amount: number;
            /** Download link once the lab has uploaded the report. */
            report_url: string | null;
        }[];
    }[];
    has_prescription: boolean;
    prescription_name: string | null;
    /** Online payment totals and refunds, on detail pages. */
    payment?: OrderPayment;
    /** Collector's first name, once assigned. */
    collector_first_name: string | null;
    /** Pay-at-collection orders: when and how the collector took payment. */
    payment_collected: { at: string; method: string | null } | null;
    can_pay: boolean;
    can_refund: boolean;
    can_cancel: boolean;
    can_reschedule: boolean;
}

export type PaymentMethod = 'pay_at_collection' | 'online';

export interface OrderPayment {
    paid: number;
    refunded: number;
    refundable: number;
    refunds: {
        id: number;
        amount: number;
        status: 'pending' | 'succeeded' | 'failed';
        status_label: string;
        reason: string | null;
        /** Admin who issued it; null for automatic refunds. */
        by: string | null;
        created_at: string;
    }[];
}
