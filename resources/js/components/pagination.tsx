import type { Paginated } from '@/types';
import { Link } from '@inertiajs/react';

const linkClass =
    'inline-flex h-11 items-center rounded-control border border-border bg-surface px-4 text-sm font-medium text-text hover:bg-canvas';

/** Previous/next links for a paginated resource collection. Renders nothing for a single page. */
export function Pagination({ meta, links }: Pick<Paginated<unknown>, 'meta' | 'links'>) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <nav aria-label="Pagination" className="flex items-center justify-between gap-4">
            <p className="text-sm text-muted">
                {meta.from}–{meta.to} of {meta.total}
            </p>
            <div className="flex gap-2">
                {links.prev && (
                    <Link href={links.prev} className={linkClass} preserveScroll>
                        Previous
                    </Link>
                )}
                {links.next && (
                    <Link href={links.next} className={linkClass} preserveScroll>
                        Next
                    </Link>
                )}
            </div>
        </nav>
    );
}
