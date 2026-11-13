import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useId } from 'react';

/** Language picker (B-32); hidden where there is no choice (staff screens). */
export function LanguageSwitcher({ className }: { className?: string }) {
    const { i18n } = usePage<SharedData>().props;
    const id = useId();

    if (i18n.locales.length < 2) {
        return null;
    }

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <Languages className="size-4 text-muted" aria-hidden="true" />
            <label htmlFor={id} className="sr-only">
                {t('Language')}
            </label>
            <select
                id={id}
                value={i18n.locale}
                onChange={(e) =>
                    // A fresh page state, so form errors in the old language go too.
                    router.post(
                        route('locale.update'),
                        { locale: e.target.value },
                        { preserveScroll: true, preserveState: false },
                    )
                }
                className="min-h-11 rounded-control border border-border bg-surface px-2 text-sm text-text"
            >
                {i18n.locales.map((option) => (
                    <option key={option.code} value={option.code} lang={option.code}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}
