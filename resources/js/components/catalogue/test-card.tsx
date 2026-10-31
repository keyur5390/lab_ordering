import { AddToCartButton } from '@/components/catalogue/add-to-cart-button';
import { Price } from '@/components/catalogue/price';
import { Badge } from '@/components/ui/badge';
import { formatTurnaround } from '@/lib/format';
import type { LabTest } from '@/types';
import { Link } from '@inertiajs/react';
import { Clock, Droplet, Info } from 'lucide-react';

/** Test card (DESIGN.md §6). */
export function TestCard({ test }: { test: LabTest }) {
    return (
        <article className="flex h-full flex-col rounded-card border border-border bg-surface p-4 md:p-6">
            <h3 className="text-base font-semibold">
                <Link href={route('catalogue.tests.show', test.slug)} className="hover:text-primary-700">
                    {test.name}
                </Link>
            </h3>
            {test.description && <p className="mt-1 line-clamp-2 text-sm text-muted">{test.description}</p>}

            <div className="mt-3 flex flex-wrap gap-2">
                <Badge>
                    <Droplet className="size-3.5" aria-hidden="true" />
                    {test.sample_type}
                </Badge>
                <Badge>
                    <Clock className="size-3.5" aria-hidden="true" />
                    Reports in {formatTurnaround(test.turnaround_hours)}
                </Badge>
                {test.requires_prescription && <Badge tone="warning">Prescription needed</Badge>}
                {test.preparation && (
                    <Badge tone="warning">
                        <Info className="size-3.5" aria-hidden="true" />
                        Preparation needed
                    </Badge>
                )}
            </div>

            <div className="mt-auto flex items-center justify-between gap-3 pt-4">
                <Price amount={test.price_amount} className="text-lg" />
                <AddToCartButton type="test" id={test.id} />
            </div>
        </article>
    );
}
