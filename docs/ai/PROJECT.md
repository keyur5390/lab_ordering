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

- **OPEN:** Can a customer order for family members (multiple patient profiles per account)? [Yes, patient profiles] `TODO(client)`
- **OPEN:** Login method: email + password, phone + OTP, or both? [Email + password, with email verification] `TODO(client)`. *Built with this default in B-03; no 2FA.*
- **OPEN:** Can a guest check out without an account? [No] `TODO(client)`

## 3. Catalogue

- Items are **tests** (single) and **packages** (bundles of tests). [assumed]
- Each test: name, code, description, sample type, preparation instructions (e.g. fasting), turnaround time ⚙️, price ⚙️. [assumed fields] `TODO(client)`
- **OPEN:** Categories/organ groupings? Search? Filters?
- **OPEN:** Is a package price independent, or derived from its tests minus a discount?
- **OPEN:** Is a catalogue item ever unavailable by city/area?

## 4. Pricing, fees and discounts ⚙️

All amounts come from the database or settings. Never from code.

- **OPEN:** Currency, and whether prices include tax. Tax rate(s) ⚙️.
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

- **OPEN:** Can one order have multiple patients?
- **OPEN:** Is a prescription upload required or optional for any tests?

## 6. Collection scheduling ⚙️

- **OPEN:** Serviceable areas (pincode/postcode list, radius, or zones)?
- **OPEN:** Slot length, daily hours, capacity per slot, days of operation ⚙️.
- **OPEN:** Earliest bookable slot (lead time) and furthest bookable date ⚙️.
- **OPEN:** Holidays/blackout dates ⚙️.

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
| _(none yet)_ | | |

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
| 2026-10-29 | Auth (B-03): email + password with email verification, built on the [default] pending client confirmation | Dev |
