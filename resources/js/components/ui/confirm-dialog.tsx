import { Button } from '@/components/ui/button';
import { useEffect, useId, useRef, type ReactNode } from 'react';

interface ConfirmDialogProps {
    open: boolean;
    title: string;
    children?: ReactNode;
    confirmLabel: string;
    processing?: boolean;
    onConfirm: () => void;
    onClose: () => void;
}

/**
 * Confirmation step for destructive actions (DESIGN.md §5). Uses the native modal <dialog>,
 * which keeps focus inside it and closes on Esc.
 */
export function ConfirmDialog({
    open,
    title,
    children,
    confirmLabel,
    processing,
    onConfirm,
    onClose,
}: ConfirmDialogProps) {
    const ref = useRef<HTMLDialogElement>(null);
    const titleId = useId();

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;

        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            aria-labelledby={titleId}
            className="m-auto w-[calc(100%-2rem)] max-w-md rounded-card border border-border bg-surface p-6 text-left text-text backdrop:bg-text/40"
        >
            <h2 id={titleId} className="text-lg font-semibold">
                {title}
            </h2>
            {children && <div className="mt-2 text-sm text-muted">{children}</div>}

            <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="secondary" onClick={onClose} autoFocus>
                    Cancel
                </Button>
                <Button variant="danger" onClick={onConfirm} processing={processing}>
                    {confirmLabel}
                </Button>
            </div>
        </dialog>
    );
}
