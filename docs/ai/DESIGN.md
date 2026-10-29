# DESIGN: the look

> Brand values below are **placeholders** until the client supplies a brand kit.
> `TODO(client):` logo, brand colours, typeface.

## 1. Principles

- **Trustworthy and calm.** This is healthcare. Use clean surfaces, generous whitespace and no visual noise.
- **Clear over clever.** Prices, preparation instructions (e.g. fasting) and slot times are always easy to see.
- **Mobile-first.** Most customers order from a phone. Design at 360px first, then scale up.
- **Accessible.** WCAG 2.2 AA: contrast ≥ 4.5:1 for text, visible focus, full keyboard use, labelled inputs.

## 2. Tokens (Tailwind 4 `@theme` in `resources/css/app.css`)

Use only these tokens. No arbitrary hex values or one-off spacing in components.

```css
@theme {
  /* Brand (placeholder — TODO(client)) */
  --color-primary-50:  #eff8ff;
  --color-primary-100: #dbeefe;
  --color-primary-500: #2e90fa;
  --color-primary-600: #1570ef;
  --color-primary-700: #175cd3;

  /* Neutrals */
  --color-surface:   #ffffff;
  --color-canvas:    #f8fafc;
  --color-border:    #e2e8f0;
  --color-muted:     #64748b;
  --color-text:      #0f172a;

  /* Status */
  --color-success-600: #079455;
  --color-warning-600: #dc6803;
  --color-danger-600:  #d92d20;
  --color-info-600:    #1570ef;

  --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif; /* TODO(client) */
  --radius-card: 0.75rem;
  --radius-control: 0.5rem;
}
```

- **Dark mode:** not in v1 [default] `TODO(client)`.

## 3. Typography

| Use | Class |
|-----|-------|
| Page title | `text-2xl md:text-3xl font-semibold text-text` |
| Section title | `text-lg font-semibold` |
| Body | `text-base text-text` |
| Secondary / help | `text-sm text-muted` |
| Price | `font-semibold tabular-nums` |

## 4. Layout

- Container: `mx-auto max-w-7xl px-4 sm:px-6 lg:px-8`.
- Spacing scale: 4 / 8 / 12 / 16 / 24 / 32 / 48 px (Tailwind `1 2 3 4 6 8 12`).
- Breakpoints: Tailwind defaults (`sm 640`, `md 768`, `lg 1024`, `xl 1280`).
- **Customer layout:** top bar (logo, search, cart, account). On mobile, a sticky bottom action bar for checkout steps.
- **Admin layout:** collapsible left sidebar, top bar, content on `bg-canvas`, data in cards and tables.

## 5. Components (`resources/js/components/ui`)

Build these once and reuse them everywhere.

- **Button:** variants `primary | secondary | ghost | danger`; sizes `sm | md | lg`. Minimum touch target 44px on mobile. Shows a spinner and is disabled while processing.
- **Input / Select / Textarea / Checkbox / Radio:** always have a visible label. Errors appear below in `text-danger-600 text-sm`, linked via `aria-describedby`.
- **Card:** `bg-surface border border-border rounded-card p-4 md:p-6`.
- **Badge:** order statuses map one-to-one to a colour, defined once next to the `OrderStatus` type.
- **Table:** on mobile, becomes stacked cards.
- **Modal / Drawer:** focus is trapped, `Esc` closes, and there is a confirm step for destructive actions.
- **Toast:** for flash messages from the backend (success/error).
- **Stepper:** for the checkout steps (Cart → Patient → Collection → Slot → Pay).

## 6. Patterns

- **Test card:** name, short description, a preparation chip (e.g. "Fasting required"), turnaround, price, and an "Add" button.
- **Slot picker:** horizontal date strip, then a grid of time slots. Unavailable slots are visibly disabled, not hidden.
- **Prices:** always formatted by `formatMoney`. Show the currency. If a discount applies, strike through the original price.

## 7. States (every screen)

- **Loading:** skeletons for content, spinner in buttons. No layout jump.
- **Empty:** icon, one line of text, and a primary action (e.g. "Browse tests").
- **Error:** a plain-language message and a retry action. Never show stack traces.
- **Error pages** (403, 404, 419, 429, 500, 503): `pages/error.tsx`, rendered by the exception handler
  in `bootstrap/app.php`. 500/503 show Laravel's debug page while `APP_DEBUG=true`. Copy lives in the
  page's `copy` map; to cover a new status, add it there and to the handler's list.
- **Validation:** inline per field, plus a summary at the top for long forms.

## 8. Icons & imagery

- Icons: `lucide-react`, 20px inline, 24px standalone, `stroke-[1.75]`.
- Imagery: minimal. Prefer icons and illustrations over stock photos [default] `TODO(client)`.

## 9. Copy & tone

- Friendly, plain and reassuring. Use sentence case for buttons and headings.
- Copy that PROJECT.md marks ⚙️ comes from settings or translations, never from JSX.
