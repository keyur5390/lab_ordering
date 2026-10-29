import { cn } from '@/lib/utils';
import type { SelectHTMLAttributes } from 'react';

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    invalid?: boolean;
}

export function Select({ className, invalid = false, ...props }: SelectProps) {
    return (
        <select
            aria-invalid={invalid || undefined}
            className={cn(
                'block h-11 w-full rounded-control border border-border bg-surface px-3 text-base text-text sm:text-sm',
                'focus-visible:border-primary-600 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-primary-100',
                invalid && 'border-danger-600',
                className,
            )}
            {...props}
        />
    );
}
