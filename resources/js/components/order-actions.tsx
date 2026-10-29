import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import type { Order } from '@/types';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface OrderActionsProps {
    order: Order;
    cancelUrl: string;
    rescheduleUrl: string;
}

/** Reschedule and cancel (with confirmation), shown only when the viewer may. */
export function OrderActions({ order, cancelUrl, rescheduleUrl }: OrderActionsProps) {
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    if (!order.can_cancel && !order.can_reschedule) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {order.can_reschedule && (
                <Link
                    href={rescheduleUrl}
                    className="inline-flex h-11 items-center rounded-control border border-border bg-surface px-4 text-sm font-medium hover:bg-canvas"
                >
                    Reschedule
                </Link>
            )}
            {order.can_cancel && (
                <>
                    <Button variant="ghost" className="text-danger-600" onClick={() => setConfirming(true)}>
                        Cancel order
                    </Button>
                    <ConfirmDialog
                        open={confirming}
                        title="Cancel this order?"
                        confirmLabel="Cancel order"
                        processing={processing}
                        onClose={() => setConfirming(false)}
                        onConfirm={() =>
                            router.post(
                                cancelUrl,
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setProcessing(true),
                                    onFinish: () => {
                                        setProcessing(false);
                                        setConfirming(false);
                                    },
                                },
                            )
                        }
                    >
                        The collection slot will be released. This can't be undone.
                    </ConfirmDialog>
                </>
            )}
        </div>
    );
}
