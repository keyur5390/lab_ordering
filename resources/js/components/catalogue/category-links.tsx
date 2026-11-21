import type { Category } from '@/types';
import { Link } from '@inertiajs/react';
import { t } from '@/lib/i18n';

/** The categories an item is in, each linking to the filtered catalogue (B-19). */
export function CategoryLinks({ categories }: { categories?: Category[] }) {
    if (!categories || categories.length === 0) {
        return null;
    }

    return (
        <ul aria-label={t('Categories')} className="flex flex-wrap gap-2">
            {categories.map((category) => (
                <li key={category.slug}>
                    <Link
                        href={route('catalogue.index', { category: category.slug })}
                        className="inline-flex min-h-11 items-center rounded-full border border-border bg-surface px-3 text-sm hover:bg-canvas sm:min-h-8"
                    >
                        {category.name}
                    </Link>
                </li>
            ))}
        </ul>
    );
}
