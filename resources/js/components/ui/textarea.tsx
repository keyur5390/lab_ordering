import { cn } from '@/lib/utils';
import type { TextareaHTMLAttributes } from 'react';

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    invalid?: boolean;
}

export function Textarea({ className, invalid = false, rows = 4, ...props }: TextareaProps) {
    return (
        <textarea
            rows={rows}
            aria-invalid={invalid || undefined}
            className={cn(
                'block w-full rounded-control border border-border bg-surface px-3 py-2 text-base text-text sm:text-sm',
                'placeholder:text-muted',
                'focus-visible:border-primary-600 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-primary-100',
                invalid && 'border-danger-600',
                className,
            )}
            {...props}
        />
    );
}
