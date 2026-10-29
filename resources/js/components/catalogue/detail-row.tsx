import type { ReactNode } from 'react';

/** One label/value row in a details list (<dl>). */
export function DetailRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-1 py-3 sm:flex-row sm:gap-4">
            <dt className="text-sm text-muted sm:w-48 sm:shrink-0">{label}</dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}
