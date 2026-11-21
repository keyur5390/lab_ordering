import { createElement, Fragment, type ReactNode } from 'react';

/**
 * Languages (B-32). Strings are written in English and looked up in the dictionary the server
 * shares for the visitor's language (lang/{locale}.json, the same file Laravel's __() uses).
 * Placeholders use Laravel's style, e.g. the text "Hello :name" with { name }.
 *
 * Always pass a string literal as the first argument: a test checks every one has a translation.
 */

export interface I18n {
    locale: string;
    /** Languages to choose from; empty on staff screens, which are English only. */
    locales: { code: string; label: string }[];
    /** null for English. */
    messages: Record<string, string> | null;
}

let current: I18n = { locale: 'en', locales: [], messages: null };

/** Called before each page renders (app.tsx), so t() is ready in page and component bodies. */
export function setI18n(i18n: I18n | undefined): void {
    if (!i18n) return;
    current = i18n;
    if (typeof document !== 'undefined') {
        document.documentElement.lang = i18n.locale;
    }
}

export function locale(): string {
    return current.locale;
}

export function t(text: string, replace?: Record<string, string | number>): string {
    let result = current.messages?.[text] || text;
    if (replace) {
        // Longest names first, so :amount_total isn't caught by :amount.
        for (const name of Object.keys(replace).sort((a, b) => b.length - a.length)) {
            result = result.replaceAll(`:${name}`, String(replace[name]));
        }
    }

    return result;
}

/**
 * "1 test" / "2 tests", with :count filled in. A translation of `many` may list forms separated by
 * "|" like Laravel's trans_choice: Spanish "one|other", Polish "one|few|many" (1 / 2–4 / 5+).
 */
export function plural(count: number, one: string, many: string): string {
    const translated = current.messages?.[many];
    if (!translated) {
        return t(count === 1 ? one : many, { count });
    }

    const forms = translated.split('|');
    const category = new Intl.PluralRules(current.locale).select(count);
    const index =
        forms.length === 3
            ? ({ one: 0, few: 1 }[category as 'one' | 'few'] ?? 2)
            : count === 1 || category === 'one'
              ? 0
              : forms.length - 1;

    return (forms[index] ?? forms[forms.length - 1]!).replaceAll(':count', String(count));
}

/**
 * Like t(), but placeholders can be React nodes, e.g. a price inside a sentence:
 * the text "The minimum order is :amount." with { amount: <Price amount={min} /> }.
 */
export function tx(text: string, replace: Record<string, ReactNode>): ReactNode {
    const translated = current.messages?.[text] || text;
    const names = Object.keys(replace).sort((a, b) => b.length - a.length);
    if (names.length === 0) {
        return translated;
    }
    const pattern = new RegExp(`:(${names.join('|')})`, 'g');
    const parts: ReactNode[] = [];
    let last = 0;
    for (const match of translated.matchAll(pattern)) {
        parts.push(translated.slice(last, match.index));
        parts.push(createElement(Fragment, { key: `${match.index}` }, replace[match[1]!]));
        last = match.index + match[0].length;
    }
    parts.push(translated.slice(last));

    return createElement(Fragment, null, ...parts);
}
