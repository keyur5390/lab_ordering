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
| Phlebotomist / collector | See assigned collections, update collection status | **OPEN**: in v1? |
| Lab staff | Mark samples received, upload reports | **OPEN** |
| Admin | Manage catalogue, prices, settings, orders, users | [assumed] |

- Roles are built as `customer` and `admin` (B-04). Admins are not created via sign-up: an existing
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
- **OPEN:** Whether prices include tax. Tax rate(s) ⚙️.
- **OPEN:** Home collection fee? Flat, by area, or waived above an order value ⚙️?
- **OPEN:** Minimum order value ⚙️?
- **OPEN:** Coupons/promo codes in v1? Rules (percentage/flat, expiry, usage limits)?
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
- **OPEN:** Can one order have multiple patients?
- **OPEN:** Is a prescription upload required or optional for any tests?

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
- Lab branches (for lab visits) are a separate task.
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

- **OPEN:** Payment gateway (Stripe, Razorpay, other)?
- **OPEN:** Pay-at-collection / cash allowed?
- **OPEN:** Refund rules on cancellation.

## 8. Order lifecycle

[Assumed statuses, confirm:] `TODO(client)`
`pending_payment → confirmed → collector_assigned → sample_collected → received_at_lab → report_ready → completed`
plus `cancelled` and `refunded`.

- **OPEN:** Who can cancel or reschedule, and until when (cutoff) ⚙️?
- **OPEN:** Is there a reschedule limit ⚙️?

## 9. Reports

- [Lab staff upload a PDF per order item; the customer downloads it from their account.] `TODO(client)`
- **OPEN:** Report retention period, and whether reports are emailed as attachments or links.
- Reports are sensitive health data. Access is limited to the owning customer and authorised staff, via signed or authorised URLs only. [non-negotiable default]

## 10. Notifications

- **OPEN:** Channels (email, SMS, WhatsApp) and which events trigger each.
- All notification copy is editable ⚙️ or kept in translation files. **OPEN:** which one.

## 11. Admin settings (⚙️ index)

Every value below is read from settings or the DB. Keep this list in sync as features land.

| Key | Meaning | Default |
|-----|---------|---------|
| `currency` | Site currency (ISO code); prices are shown and charged in it | none; must be set by an admin |
| `timezone` | Site timezone for collection hours, slots and closures | `config('app.timezone')` until set |
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
| 2026-10-29 | Slots (B-10): schedule per service area; whole or part-day closures; site timezone setting | Client |
| 2026-10-29 | Addresses & areas (B-09): generic international address; postcode, radius and zone areas; free OSM maps | Client |
| 2026-10-29 | Patients (B-08): profiles with DOB, sex, relationship, phone, email; "Self" profile created on sign-up | Client |
| 2026-10-29 | Cart (B-07): guests may build a cart; login required only at checkout | Client |
| 2026-10-29 | Catalogue (B-06): one admin-chosen currency; packages priced fixed or as tests' total minus a discount, per package | Client |
| 2026-10-29 | Roles (B-04): customer + admin only; collector/lab staff wait on client; admins promoted via CLI | Dev |
| 2026-10-29 | Auth (B-03): email + password with email verification, built on the [default] pending client confirmation | Dev |
