import { Price } from '@/components/catalogue/price';
import { Badge } from '@/components/ui/badge';
import { formatTurnaround } from '@/lib/format';
import type { LabPackage } from '@/types';
import { Link } from '@inertiajs/react';
import { Clock, FlaskConical } from 'lucide-react';

/** Package card (DESIGN.md §6). TODO: "Add" button arrives with the cart. */
export function PackageCard({ pkg }: { pkg: LabPackage }) {
    return (
        <article className="flex h-full flex-col rounded-card border border-border bg-surface p-4 md:p-6">
            <h3 className="text-base font-semibold">
                <Link href={route('catalogue.packages.show', pkg.slug)} className="hover:text-primary-700">
                    {pkg.name}
                </Link>
            </h3>
            {pkg.description && <p className="mt-1 line-clamp-2 text-sm text-muted">{pkg.description}</p>}

            <div className="mt-3 flex flex-wrap gap-2">
                <Badge tone="info">
                    <FlaskConical className="size-3.5" aria-hidden="true" />
                    {pkg.tests_count} tests
                </Badge>
                <Badge>
                    <Clock className="size-3.5" aria-hidden="true" />
                    Reports in {formatTurnaround(pkg.turnaround_hours)}
                </Badge>
            </div>

            <div className="mt-auto pt-4">
                <Price amount={pkg.price_amount} originalAmount={pkg.tests_total_amount} className="text-lg" />
            </div>
        </article>
    );
}
