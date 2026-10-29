import { cn } from '@/lib/utils';
import type { InputHTMLAttributes } from 'react';

export function Checkbox({ className, ...props }: Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>) {
    return (
        <input
            type="checkbox"
            className={cn(
                'size-4 rounded border-border accent-primary-600',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                className,
            )}
            {...props}
        />
    );
}
