import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import type { ReactNode } from 'react';

export interface FieldControlProps {
    id: string;
    invalid: boolean;
    'aria-describedby'?: string;
}

interface FieldProps {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    /** Rendered next to the label, e.g. a "Forgot password?" link. */
    labelAside?: ReactNode;
    /** Renders the control, wired to the label, hint and error (DESIGN.md §5). */
    children: (control: FieldControlProps) => ReactNode;
}

/** Label + control + hint + validation error, linked via aria-describedby. */
export function Field({ id, label, error, hint, labelAside, children }: FieldProps) {
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;
    const describedBy = [hint && hintId, error && errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between gap-2">
                <Label htmlFor={id}>{label}</Label>
                {labelAside}
            </div>
            {children({ id, invalid: !!error, 'aria-describedby': describedBy })}
            {hint && (
                <p id={hintId} className="text-sm text-muted">
                    {hint}
                </p>
            )}
            <InputError id={errorId} message={error} />
        </div>
    );
}
