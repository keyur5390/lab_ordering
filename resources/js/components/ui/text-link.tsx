import { cn } from '@/lib/utils';
import { Link, type InertiaLinkProps } from '@inertiajs/react';

export function TextLink({ className, ...props }: InertiaLinkProps) {
    return (
        <Link
            className={cn(
                'font-medium text-primary-700 underline-offset-4 hover:underline',
                'focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                className,
            )}
            {...props}
        />
    );
}
