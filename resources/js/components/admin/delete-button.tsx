import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface DeleteButtonProps {
    /** URL to send the DELETE request to. */
    href: string;
    /** Name shown in the confirmation, e.g. "Complete Blood Count". */
    itemName: string;
    /** What is being deleted, e.g. "test". */
    itemType: string;
}

/** Delete action with a confirmation step (DESIGN.md §5). */
export function DeleteButton({ href, itemName, itemType }: DeleteButtonProps) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        router.delete(href, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });
    };

    return (
        <>
            <Button variant="ghost" size="sm" className="text-danger-600" onClick={() => setOpen(true)}>
                Delete
            </Button>
            <ConfirmDialog
                open={open}
                title={`Delete this ${itemType}?`}
                confirmLabel={`Delete ${itemType}`}
                processing={processing}
                onConfirm={confirm}
                onClose={() => setOpen(false)}
            >
                “{itemName}” will be removed permanently. To hide it from customers instead, unpublish it.
            </ConfirmDialog>
        </>
    );
}
