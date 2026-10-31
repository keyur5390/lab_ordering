import { cn } from '@/lib/utils';

export function InputError({ id, message, className }: { id?: string; message?: string; className?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} className={cn('text-sm text-danger-600', className)}>
            {message}
        </p>
    );
}
