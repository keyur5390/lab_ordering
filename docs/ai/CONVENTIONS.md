# CONVENTIONS: how we write code, test and finish a task

## 1. Stack

- **Backend:** Laravel 12, PHP 8.3+
- **Frontend:** React 19 + TypeScript (strict), Inertia.js 2, Vite
- **Styling:** Tailwind CSS 4 (CSS-first config via `@theme`, see DESIGN.md)
- **Database:** MySQL 8 in all environments, kept **PostgreSQL-compatible**
- **Auth:** Laravel Fortify (headless). Its screens are Inertia pages registered in
  `FortifyServiceProvider`; customisation goes in `app/Actions/Fortify`.
- **Tests:** Pest (PHP); Vitest + React Testing Library (TS) when component logic warrants it

## 2. Working agreement

- One task per ID (e.g. `B-03`). Do only that task.
- Plan before building: files to create or change, plus decisions, in under 15 lines.
- Reuse existing services, components and types. No parallel versions.
- Never invent a business rule. Check PROJECT.md. If it isn't there, ask, or use the `[bracketed]`
  default with `// TODO(client):` (`{/* TODO(client): */}` in JSX).
- Never hard-code prices, fees, times, limits or copy that PROJECT.md marks ⚙️.

## 3. Backend layout

```
app/
  Http/Controllers/        thin: validate → call service/action → return Inertia response
  Http/Requests/           one FormRequest per write endpoint (validation + authorize())
  Http/Resources/          shape data sent to Inertia props (never pass raw models)
  Models/                  relationships, casts, scopes, no business logic
  Services/                business logic, one class per domain (OrderService, SlotService…)
  Actions/                 single-purpose operations when a service would get too large
  Enums/                   backed enums for statuses/types (OrderStatus, CollectionType…)
  Policies/                all authorisation
  Settings/                typed access to ⚙️ settings (single source; cached)
```

- Controllers stay thin. Business logic lives in Services or Actions.
- Authorisation goes through Policies and `authorize()`, never inline role checks in controllers.
  Area-wide access uses gates (e.g. `access-admin` in `AppServiceProvider`) applied as `can:` route
  middleware. The frontend reads permission flags from `UserResource` (e.g. `can_access_admin`) and
  never decides access from the raw role.
- Admin routes live under `/admin`, are named `admin.*`, and use `AdminLayout`.
- Statuses and types are PHP backed enums, cast on the model. No magic strings.
- **Money:** integer minor units (`unsigned bigInteger`), named `*_amount` (e.g. `price_amount`).
  One `Money` helper formats it. Never use floats.
- **Settings:** read through the Settings layer only. Never call `config()` or `env()` for business values.
- Wrap multi-write operations in `DB::transaction()`.
- Queue notifications, emails and slow work.
- Files with health data (reports) go on a private disk. Serve them via authorised controller or temporary signed URL.

## 4. Database

- Migrations only. Never edit a migration that has been pushed; add a new one.
- **Portability (MySQL ⇄ PostgreSQL):**
  - Use the schema builder. No raw engine-specific SQL (`ENUM` columns, `UNSIGNED` in raw SQL, backticks, `IFNULL`, `GROUP_CONCAT`, MySQL-only JSON operators).
  - Store enums as `string` columns, validated by PHP enums.
  - Use `json` columns (not `jsonb`/MySQL-specific). Query via Eloquent JSON syntax only.
  - No reliance on case-insensitive collation. Normalise (e.g. lowercase emails) in code.
  - Timestamps in UTC. Display in the settings timezone.
- Foreign keys use `foreignId()->constrained()`, with explicit `cascadeOnDelete`/`restrictOnDelete`.
- Index every foreign key and every column used in a `where` or `orderBy` on lists.
- Every model has a factory. Seeders provide a usable demo dataset (`php artisan migrate:fresh --seed`).

## 5. Frontend layout

```
resources/js/
  pages/          Inertia pages, mirrors route names (pages/orders/show.tsx)
  layouts/        AppLayout, AdminLayout, GuestLayout
  components/ui/  design-system primitives (Button, Input, Card, Badge…)
  components/     feature components (CartSummary, SlotPicker…)
  hooks/          shared hooks
  lib/            utils (formatMoney, formatDate, cn)
  types/          shared TS types; index.d.ts holds PageProps & shared models
```

- TypeScript strict. No `any`. Prop types come from `types/`, matching the API Resources.
- Use Inertia `useForm` for forms and `router` for visits. No ad-hoc `fetch` for page data.
- Use named routes on both sides (Ziggy `route()`). No hard-coded URLs.
- Before creating a component, check `components/ui` and `components`.
- Format money and dates with the `lib` helpers only.
- Every list handles its empty, loading and error states (see DESIGN.md).

## 6. Naming

- PHP: PSR-12, enforced by Laravel Pint. Classes `PascalCase`, methods `camelCase`.
- DB: tables plural `snake_case`, columns `snake_case`, pivots alphabetical (`package_test`).
- Routes: resourceful; names dotted (`orders.show`, `admin.tests.index`).
- TS: components `PascalCase.tsx`, everything else `kebab-case.ts`. Pages follow route names.

## 7. Testing

- Every task ships with tests:
  - **Feature tests** (Pest) for every route: happy path, validation failure, authorisation (guest / wrong user / wrong role).
  - **Unit tests** for services with business logic (pricing, slots, status transitions).
- Use factories. Never depend on seeded data in tests.
- Run tests on SQLite in memory or MySQL. Portability-sensitive queries also get checked on PostgreSQL when available.
- Don't mock what you own. Fake external services (`Http::fake`, `Notification::fake`, `Storage::fake`, `Queue::fake`).

## 8. Git

- Branch per the session instructions. Commit messages: `B-03: short imperative summary`.
- One task = one or more commits that all carry the task ID.
- Never commit `.env`, secrets, `vendor/`, `node_modules/` or built assets.

## 9. Definition of Done

A task is done only when **all** of these hold:

1. Scope matches the task ID, no more and no less.
2. No invented business rules. Unknowns are asked or marked `TODO(client)`.
3. No hard-coded ⚙️ values. New settings are added to PROJECT.md §11.
4. Migrations run cleanly from scratch: `php artisan migrate:fresh --seed`.
5. Checks pass:
   - `./vendor/bin/pint --test`
   - `php artisan test`
   - `npm run types` (`tsc --noEmit`), `npm run lint` and `npm run format:check`
   - `npm run build`
   - CI (`.github/workflows/ci.yml`) is green on the PR. It runs all of the above, with the
     backend on both MySQL 8 and PostgreSQL 16.
6. Authorisation is covered by Policies and tested.
7. UI follows DESIGN.md: responsive at 360px and desktop, keyboard-usable, with empty/loading/error states.
8. PROJECT.md and these docs are updated if the task changed or clarified something.
9. Committed with the task ID and pushed.
10. Report back with:
    - **Files changed**
    - **Migrations added**
    - **How to try it in the browser** (URL, demo login, steps)
    - **Open questions** (including every `TODO(client)` added)
