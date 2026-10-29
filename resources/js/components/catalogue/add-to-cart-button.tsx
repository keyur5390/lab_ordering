import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { CartItemType, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';

interface AddToCartButtonProps {
    type: CartItemType;
    id: number;
    className?: string;
}

/** "Add" for items not in the cart; a link to the cart for items already in it. */
export function AddToCartButton({ type, id, className }: AddToCartButtonProps) {
    const { cart } = usePage<SharedData>().props;
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const inCart = (type === 'test' ? cart.test_ids : cart.package_ids).includes(id);

    if (inCart) {
        return (
            <Link
                href={route('cart.show')}
                className={cn(
                    'inline-flex h-11 items-center justify-center gap-2 rounded-control border border-primary-600 px-4 text-sm font-medium whitespace-nowrap text-primary-700 hover:bg-primary-50',
                    className,
                )}
            >
                <Check className="size-4" aria-hidden="true" />
                In cart
            </Link>
        );
    }

    return (
        <span className="inline-flex flex-col items-end gap-1">
            <Button
                className={className}
                processing={processing}
                onClick={() =>
                    router.post(
                        route('cart.items.store'),
                        { type, id },
                        {
                            preserveScroll: true,
                            onStart: () => {
                                setProcessing(true);
                                setError(null);
                            },
                            onError: (errors) => setError(errors.id ?? errors.type ?? 'Could not add this item.'),
                            onFinish: () => setProcessing(false),
                        },
                    )
                }
            >
                Add
            </Button>
            {error && (
                <span role="alert" className="text-sm text-danger-600">
                    {error}
                </span>
            )}
        </span>
    );
}
