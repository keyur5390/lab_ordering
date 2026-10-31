import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

interface DeleteButtonProps {
    /** URL to send the DELETE request to. */
    href: string;
    /** Name shown in the confirmation, e.g. "Complete Blood Count". */
    itemName: string;
    /** What is being deleted, e.g. "test". */
    itemType: string;
    /** Extra explanation under the title, e.g. an alternative to deleting. */
    description?: ReactNode;
    /** Button and confirm wording; defaults to "Delete". */
    verb?: string;
}

/** Delete action with a confirmation step (DESIGN.md §5). */
export function DeleteButton({ href, itemName, itemType, description, verb = 'Delete' }: DeleteButtonProps) {
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
                {verb}
            </Button>
            <ConfirmDialog
                open={open}
                title={`${verb} this ${itemType}?`}
                confirmLabel={`${verb} ${itemType}`}
                processing={processing}
                onConfirm={confirm}
                onClose={() => setOpen(false)}
            >
                “{itemName}” will be removed permanently.{description && <> {description}</>}
            </ConfirmDialog>
        </>
    );
}
