# PROJECT — Lab-Test Ordering Platform

> Source of truth for **what** we build and **every business rule**.
> If a rule is not written here, it does not exist yet. Ask the client; don't guess.
>
> Notation:
> - `[bracketed]` = a provisional default we may build against. Code using it carries `// TODO(client):`.
> - **OPEN** = no default. Ask before building anything that depends on it.
> - ⚙️ = value lives in settings or the database and is editable by an admin. Never hard-code it.

---

## 1. Product summary

A web platform where customers browse lab tests and health packages, order them, choose
home sample collection or a lab visit, pay online, and receive their reports. Staff manage
the catalogue, orders, collection schedule and reports from an admin area.

- **OPEN:** Business name, market/country, and launch city/cities.
- **OPEN:** Single lab, or a marketplace of multiple partner labs?
- [Web only, responsive. No native apps in v1.] `TODO(client)`

## 2. Users and roles

| Role | Can do | Status |
|------|--------|--------|
| Guest | Browse catalogue, view test details, view prices | [assumed] |
| Customer | Everything a guest can, plus order, pay, manage patients/addresses, download reports | [assumed] |
| Phlebotomist / collector | Home visits in their areas: take visits, mark collected or failed, take payment | Client, 2026-10-30 (B-15) |
| Lab staff | Mark samples collected and received, upload reports, read all orders | Client, 2026-10-29 (B-14) |
| Admin | Manage catalogue, prices, settings, orders, users | [assumed] |

- Roles are built as `customer`, `admin` (B-04), `lab_staff` (B-14) and `collector` (B-15).
- **Collectors (client, 2026-10-30):** *Built in B-15.*
  - Each collector works in one or more service areas (Admin → Collectors).
  - Admins assign a collector from the order's area, or change or remove them, until the sample is collected.
    Collectors can also take unassigned visits in their areas from their day screen (`/collector`).
  - Collectors see their visits for a day: address with a map link, the customer's phone, and patients with
    their tests.
  - They mark the sample collected, or the visit failed. [Reasons: nobody at home, refused/not ready, address
    not found, other with a note.] `TODO(client)` A failed visit alerts admins, who reschedule (which clears
    it) or cancel.
  - For pay-at-collection orders they record the payment. [Methods: cash, card, UPI, other.] `TODO(client)`
  - Customers get a "Collector assigned" message (again if the collector changes) and see the collector's
    first name on the order. Admins are not created via sign-up: an existing
  account is promoted with `php artisan user:role {email} admin`. [default] `TODO(client)`: who may
  create admins, and whether an admin user-management screen is needed.
- **Patient profiles (client, 2026-10-29):** customers book for themselves and family members through
  patient profiles. *Built in B-08.*
  - Each profile has full name, relationship, date of birth, sex (all required) and optional phone and email.
  - Every account gets a "Self" profile at sign-up, named after the account holder. Date of birth and sex
    are filled in later, and incomplete profiles are flagged until then.
  - [Relationships: Self, Spouse, Child, Parent, Other. Sex: Male, Female, Other.] `TODO(client)`
  - [One "Self" profile per account; it can be edited but not deleted, and its relationship is fixed.] `TODO(client)`
  - [Phone accepts 7–20 digits with spaces, dashes and an optional leading +; tighten once the launch country is known.] `TODO(client)`
  - [No limit on the number of profiles per account.] `TODO(client)`
- **OPEN:** Login method: email + password, phone + OTP, or both? [Email + password, with email verification] `TODO(client)`. *Built with this default in B-03; no 2FA.*
- **OPEN:** Can a guest check out without an account? [No] `TODO(client)`

## 3. Catalogue

- Items are **tests** (single) and **packages** (bundles of tests). [assumed] *Built in B-06.*
- Each test: name, code, description, sample type, preparation instructions (e.g. fasting), turnaround time ⚙️, price ⚙️. [assumed fields] `TODO(client)`
  Turnaround is stored in hours and price in minor units, both per test in the DB. Admins can publish/hide tests and packages.
- **Package pricing (client, 2026-10-29):** each package has a pricing mode chosen by the admin:
  - **Fixed:** the package has its own price.
  - **Discounted:** the sum of its tests' current prices minus a discount, either a whole percent (1–100) or a fixed amount (less than the tests' total).
- [A percent discount is rounded half-up to the minor unit.] `TODO(client)`
- [A package must contain at least 2 tests.] `TODO(client)`
- [A package's turnaround is its slowest test's.] `TODO(client)`
- [A test that belongs to a package can't be deleted; remove it from the package or unpublish it.] `TODO(client)`
- **OPEN:** Categories/organ groupings? Search? Filters?
- **OPEN:** Is a package price independent, or derived from its tests minus a discount?
- **OPEN:** Is a catalogue item ever unavailable by city/area?

## 4. Pricing, fees and discounts ⚙️

All amounts come from the database or settings. Never from code.

- **Currency (client, 2026-10-29):** one active currency for the whole site, chosen by the admin in
  Admin → Settings ⚙️. Changing it does not convert existing prices. [Offered: AED, AUD, CAD, EUR, GBP,
  INR, SGD, USD; all 2-decimal.] `TODO(client)`
- *Built in B-16.* The order total is: tests total − coupon discount + home collection fee. Tax is either
  included in that amount or added on top. Orders keep each figure as charged.
- **Collection fee (client, 2026-10-30, our recommendation):** each service area has its own fee ⚙️ (empty
  = free). It is free when the tests total after any coupon reaches `free_collection_above` ⚙️.
  [Compared after the coupon.] `TODO(client)`
- **Tax (client, 2026-10-30, our recommendation):** one rate `tax_rate_basis_points` ⚙️, applied to the tests
  after the discount plus the fee. Admins choose whether prices include it (shown as "Includes ₹X tax") or
  it is added at checkout (`tax_inclusive` ⚙️). [Included by default; no rate = no tax.] `TODO(client)`
  It is rounded half-up to the minor unit.
- **Minimum order (client, 2026-10-30):** `minimum_order_amount` ⚙️, compared with the tests total before
  any coupon. Empty means no minimum.
- **Coupons (client, 2026-10-30):** Admin → Coupons.
  - A code (not case-sensitive) gives a percent off (with an optional cap) or a fixed amount off the tests
    total, never more than the total.
  - Optional: start and end dates (inclusive, site timezone), a total use limit, a per-customer limit,
    a minimum tests total, and an on/off switch.
  - Customers apply one code at checkout. [Cancelled or refunded orders give their use back.]
    `TODO(client)` Used coupons can't be deleted, only switched off.
- Money is stored as integer minor units (e.g. paise/cents). [convention, see CONVENTIONS.md]

## 5. Ordering flow

[Assumed v1 flow, confirm:] `TODO(client)`
1. Add tests/packages to cart.
2. Choose or create the patient(s) for each item.
3. Choose collection type: **home collection** or **lab visit**.
4. Choose address (home) or lab branch (visit), then a date and time slot.
5. Review, then pay.
6. Order confirmed, then notifications are sent.

- **Cart (client, 2026-10-29):** guests can build a cart; logging in or registering moves it to their
  account, and an account is needed only to check out. *Built in B-07.* The cart reads prices live from
  the catalogue (no price is stored until an order is placed). Items an admin unpublishes stay in the
  cart, marked unavailable, and are left out of the total. A test that's also inside a package in the
  cart is flagged, not blocked. [Each item appears once; quantities per patient come with patient
  profiles.] `TODO(client)`
- **OPEN:** How long guest carts are kept before being cleaned up.
- **Checkout (client, 2026-10-29):** *Built in B-11.*
  - One order can cover several patients: each cart item is booked for one or more of the customer's
    complete patient profiles, and the price is per patient. One address and one slot per order (one
    home visit).
  - Home collection or a lab visit at a branch (B-17). Lab visits have no collection fee and no collector.
  - **Prescriptions:** admins flag tests that need one; checkout requires an upload when the order
    contains such a test (or a package that includes one). Stored privately; only the customer and
    admins can download it. [PDF, JPG or PNG up to 10 MB.] `TODO(client)`
  - The total follows §4 (coupon, collection fee, tax).
  - The cart is emptied when the order is placed. Orders keep snapshots of what was booked (names,
    prices, package contents, patient details, address).

## 6. Collection scheduling ⚙️

- **Serviceable areas (client, 2026-10-29):** admins define areas in all three ways; an address is
  serviceable if any published area covers it. *Built in B-09.*
  - **Postcode list** (per country). Matching ignores case, spaces and dashes; [exact match only, no
    prefixes or wildcards.] `TODO(client)`
  - **Radius:** a centre on the map plus km. **Drawn zone:** a polygon on the map. Both apply only to
    addresses with a map pin, which is optional for customers.
- **Addresses (client, 2026-10-29):** generic international format: label (optional), line 1, line 2
  (optional), city, region (optional), postcode, country (ISO code), optional map pin.
- **Maps (client, 2026-10-29):** free option: Leaflet with OpenStreetMap tiles, no geocoding. The tile server
  is set in `.env` (`MAP_TILE_URL`); OpenStreetMap's own servers are for light use only, so use a tile
  provider in production. [Default map centre is world view.] `TODO(client)`: centre on the launch city.
- **Lab branches (client, 2026-10-30):** *Built in B-17.* Admin → Branches.
  - Each branch has a name, an address with a map pin, optional phone, email, directions and a photo
    (stored publicly; needs `php artisan storage:link`), and a published switch.
  - It has its own schedule, set up like a service area's. Every branch takes every test.
  - Customers choose "Home collection" or "Visit a lab" at checkout, then a branch and one of its slots.
    The option appears only when a published branch has a schedule.
  - Closures can apply to one area, one branch, or everywhere.
- **Collection schedule (client, 2026-10-29): per service area** ⚙️. *Built in B-10.* Each area has:
  - slot length (minutes), collections per slot (capacity), lead time (earliest bookable slot, counted
    from now) and booking window (days, starting today);
  - weekly opening hours, with one or more time ranges per weekday. Days without a range are closed.
  All values are entered by admins (Admin → Service areas → Schedule); an area without them offers no slots.
- **Closures (client, 2026-10-29):** whole days (one date or a range) or part of one day (from–to), for
  one area or for all areas, with an optional reason shown to customers. Admin → Closures.
- Slots use the site timezone (Admin → Settings ⚙️). Slots that can't be booked (full, closed, too
  soon) are shown disabled, not hidden.
- Capacity counts confirmed bookings once orders exist (B-11).

## 7. Payments

*Built in B-12.*

- **Gateways (client, 2026-10-29):** Stripe and Razorpay are both built; the admin picks the active one in
  Admin → Settings ⚙️, or turns online payment off. API keys live in `.env` (server config, not settings);
  a gateway can be chosen only once its keys are set. Both use the gateway's hosted payment page (Stripe
  Checkout, Razorpay Payment Links); payments are confirmed on return and by signed webhooks
  (`/webhooks/payments/{stripe|razorpay}`).
- **Payment method (client, 2026-10-29):** while a gateway is set, customers always choose between
  paying online and paying at collection. Pay-at-collection orders are confirmed at once.
- **Unpaid online orders (client, 2026-10-29):** start as `pending_payment` and hold their slot for
  `payment_hold_minutes` ⚙️ [default 15] `TODO(client)`. The customer can retry payment from the order
  page during the hold. When the hold passes, the slot is released and the order is cancelled.
  - A payment that arrives late still confirms the order if the slot is free; otherwise it is refunded in full.
  - A second payment for an already-confirmed order is also refunded in full.
- **Refunds (client, 2026-10-29):** cancelling a paid order refunds what is left in full, automatically.
  Admins can also refund any amount up to what is left, with an optional reason. A cancelled order with
  nothing left to refund becomes `refunded`. [Admin cancellations also refund in full.] `TODO(client)`
  A refund the gateway rejects is recorded as failed, and an admin can retry it.
- **OPEN:** Tracking refund outcomes after the gateway accepts them (refund webhooks). Refunds still
  processing are shown as such.

## 8. Order lifecycle

[Assumed statuses, confirm:] `TODO(client)`
`pending_payment → confirmed → collector_assigned → sample_collected → received_at_lab → report_ready → completed`
plus `cancelled` and `refunded`.

- Pay-at-collection orders start as `confirmed`; online orders start as `pending_payment` (§7).
- Lab staff and admins move orders on to `sample_collected` → `received_at_lab`, and to `report_ready` once
  all reports are uploaded (§9).
- Assigning a collector moves an order to `collector_assigned`; removing them moves it back to `confirmed`
  (§2). `completed` is not used yet (OPEN).
- **Cancel and reschedule (client, 2026-10-29):** customers can, before collection, until a cutoff of
  N hours before the slot, set in Admin → Settings ⚙️. Until it is set, only admins can. Admins can
  always, before collection.
- Each order uses one place in its slot's capacity (one home visit, whatever the number of patients).
- **OPEN:** Is there a reschedule limit ⚙️?

## 9. Reports

*Built in B-14.*

- **Lab staff (client, 2026-10-29):** a `lab_staff` role (granted with `php artisan user:role {email} lab_staff`).
  - The lab area (Admin → Lab) shows orders from confirmed onwards, with patients and tests only: no address
    or payment.
  - Lab staff also have read-only access to the full admin order pages; cancel, reschedule and refund stay
    admin-only. Admins can do all lab work too.
- **Steps (client, 2026-10-29):** without a collector role, lab staff and admins mark "sample collected" and
  then "received at lab", one step at a time. Each step records when it happened.
- **Reports (client, 2026-10-29):** one PDF per booked item per patient, uploaded once the sample is at the
  lab, and replaceable for corrections. [PDF up to 20 MB.] `TODO(client)`
  - When every item has a report, the order becomes `report_ready` and the customer is told once.
    [Corrections after that don't notify again.] `TODO(client)`
  - [Customers can download each report as soon as it is uploaded.] `TODO(client)`
- **Delivery (client, 2026-10-29):** both ways.
  - The "Reports ready" email attaches the PDFs when they total no more than [10 MB] `TODO(client)`.
    Otherwise it links to the order.
  - The SMS links to the order. Downloads in the account need sign-in.
- **OPEN:** Report retention period; when an order becomes `completed`.
- Reports are sensitive health data. Access is limited to the owning customer and authorised staff, via signed or authorised URLs only. [non-negotiable default]

## 10. Notifications

*Built in B-13.*

- **Channels (client, 2026-10-29):** email and SMS. WhatsApp is wanted but deferred, because business-initiated
  WhatsApp messages need pre-approved templates.
  - Admins switch customer emails on or off and pick the SMS driver in Admin → Settings ⚙️: off, "log" (testing)
    or Twilio. Twilio's keys go in `.env`, and more SMS providers can be added as drivers.
  - Customers can opt out of SMS on their account page.
  - SMS go to the phone on the customer's "Self" profile, only when it includes the country code (+…).
    [default] `TODO(client)`
- **Customer events (client, 2026-10-29):**
  - Order confirmed: pay-at-collection orders, when placed.
  - Payment received: online orders, when paid. This confirms the order, so it is one message, not two.
  - Refund issued: automatic or by an admin.
  - Order cancelled: by the customer or an admin.
  - Payment time ran out.
  - Collection rescheduled.
  - Collection reminder: `reminder_hours_before` ⚙️ [24] `TODO(client)` hours before the slot. Sent once,
    and again after a reschedule. Orders booked inside that window get none.
  - Reports ready (B-14): with the PDFs attached (§9).
  - Collector assigned (B-15): the collector's first name.
- **Admin alerts (client, 2026-10-29):** emailed to `admin_notification_email` ⚙️ for new confirmed orders,
  cancellations, refunds the gateway rejected and failed home visits (B-15).
- **Wording (client, 2026-10-29):** edited by admins per event and channel in Admin → Notifications, with
  placeholders such as `{order_number}` and `{slot}`. Each message can be switched off. Default wording is
  added by the migration. [SMS up to 640 characters; over 160 is sent as several messages.] `TODO(client)`
- Notifications are queued (`QUEUE_CONNECTION=database`; run a queue worker) and sent after the order is saved.
- **OPEN:** Notification history in the admin.

## 11. Admin settings (⚙️ index)

Every value below is read from settings or the DB. Keep this list in sync as features land.

| Key | Meaning | Default |
|-----|---------|---------|
| `currency` | Site currency (ISO code); prices are shown and charged in it | none; must be set by an admin |
| `timezone` | Site timezone for collection hours, slots and closures | `config('app.timezone')` until set |
| `cancellation_cutoff_hours` | Customers can cancel/reschedule until this many hours before the slot | none; only admins can until set |
| `payment_gateway` | Online payment gateway (`stripe`, `razorpay`), or off; keys come from `.env` | off |
| `payment_hold_minutes` | How long an unpaid online order holds its slot | [15] `TODO(client)` |
| `free_collection_above` | Home collection is free from this tests total (after any coupon) | none; fee always charged |
| `minimum_order_amount` | Smallest tests total that can be checked out | none |
| `tax_rate_basis_points` | Tax rate (1800 = 18%) | none; no tax |
| `tax_inclusive` | Prices include tax (true) or tax is added at checkout | [true] `TODO(client)` |
| Per service area (DB) | Home collection fee | 0 (free) |
| Per branch (DB) | Address, contact, directions, photo; slot length, capacity, lead time, window, weekly hours | none; no slots until set |
| `coupons` (DB) | Promo codes and their rules | none |
| `notify_by_email` | Email customers about their orders | on |
| `sms_driver` | SMS driver (`log`, `twilio`), or off; Twilio keys come from `.env` | off |
| `reminder_hours_before` | Hours before the slot to send the collection reminder; 0 = off | [24] `TODO(client)` |
| `admin_notification_email` | Where admin alerts are emailed | none; alerts off |
| `notification_templates` (DB) | Subject/body per event and channel, on/off | default wording from the migration |
| Per test (DB) | Requires a prescription | off |
| Per service area (DB) | Slot length, capacity per slot, lead time, booking window, weekly hours | none; no slots until set |

## 12. Compliance & data

- **OPEN:** Applicable regulations (e.g. HIPAA, GDPR, India DPDP Act)? Data residency?
- **OPEN:** Audit log requirements.
- **OPEN:** Languages/locales [English only] `TODO(client)`, timezone [single, from settings ⚙️].

## 13. Out of scope for v1

- **OPEN:** confirm with client.

## 14. Decision log

| Date | Decision | By |
|------|----------|----|
| 2026-10-29 | Stack: Laravel 12, React 19 + TS via Inertia 2, Tailwind 4, MySQL 8 (PostgreSQL-compatible) | Client |
| 2026-10-30 | Lab branches (B-17): branches with address, pin, contact, directions, photo; own slot schedule; lab visits at checkout; all tests at every branch | Client |
| 2026-10-30 | Fees, tax & coupons (B-16): per-area fee, free above a set value; one tax rate, included or added; coupons with %/fixed, dates, limits, minimum; minimum order | Client (fee and tax on our recommendation) |
| 2026-10-30 | Collectors (B-15): per-area collectors; admins assign and collectors take visits; collected, failed visit, payment taken; customer told the collector's name | Client |
| 2026-10-29 | Lab & reports (B-14): lab staff role with lab area + read-only orders; lab staff/admins mark collected and received; one PDF per item per patient; report ready by email (attached) and link | Client |
| 2026-10-29 | Notifications (B-13): email + SMS (WhatsApp later); SMS drivers, admin picks; admin-editable wording; customer SMS opt-out; admin alerts incl. refund failures | Client |
| 2026-10-29 | Payments (B-12): Stripe and Razorpay, admin picks; customer chooses online or at collection; slot held for an admin-set time; automatic full refund on cancel plus admin partial refunds | Client |
| 2026-10-29 | Checkout (B-11): patients per item; prescriptions for flagged tests; confirm + pay at collection; customer cancel/reschedule until an admin-set cutoff | Client |
| 2026-10-29 | Slots (B-10): schedule per service area; whole or part-day closures; site timezone setting | Client |
| 2026-10-29 | Addresses & areas (B-09): generic international address; postcode, radius and zone areas; free OSM maps | Client |
| 2026-10-29 | Patients (B-08): profiles with DOB, sex, relationship, phone, email; "Self" profile created on sign-up | Client |
| 2026-10-29 | Cart (B-07): guests may build a cart; login required only at checkout | Client |
| 2026-10-29 | Catalogue (B-06): one admin-chosen currency; packages priced fixed or as tests' total minus a discount, per package | Client |
| 2026-10-29 | Roles (B-04): customer + admin only; collector/lab staff wait on client; admins promoted via CLI | Dev |
| 2026-10-29 | Auth (B-03): email + password with email verification, built on the [default] pending client confirmation | Dev |
