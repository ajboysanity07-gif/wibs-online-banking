import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { cn } from '@/lib/utils';

type FormErrorSummaryProps = {
    errors: Record<string, string | undefined>;
    idResolver?: (key: string) => string;
    className?: string;
    // When provided, called instead of focusing the field directly -- used
    // when the erroring field may live on a different wizard step, so the
    // caller needs to navigate there first before focusing.
    onEntryClick?: (key: string) => void;
};

export const defaultIdResolver = (key: string) => key.replace(/\./g, '_');

export const focusField = (
    key: string,
    idResolver: (key: string) => string = defaultIdResolver,
) => {
    const id = idResolver(key);
    const target =
        document.getElementById(id) ??
        document.querySelector<HTMLElement>(`[name="${key}"]`);

    if (!target) {
        return;
    }

    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.focus({ preventScroll: true });
};

export function FormErrorSummary({
    errors,
    idResolver = defaultIdResolver,
    className,
    onEntryClick,
}: FormErrorSummaryProps) {
    const entries = Object.entries(errors).filter(
        (entry): entry is [string, string] => Boolean(entry[1]),
    );

    if (entries.length === 0) {
        return null;
    }

    return (
        <Alert
            variant="destructive"
            className={cn(
                'border-destructive/40 bg-destructive/5',
                className,
            )}
        >
            <AlertCircle />
            <AlertTitle>
                {entries.length === 1
                    ? 'Please fix 1 error'
                    : `Please fix ${entries.length} errors`}
            </AlertTitle>
            <AlertDescription>
                <ul className="list-disc space-y-1 pl-4">
                    {entries.map(([key, message]) => (
                        <li key={key}>
                            <button
                                type="button"
                                className="text-left underline-offset-2 hover:underline"
                                onClick={() =>
                                    onEntryClick
                                        ? onEntryClick(key)
                                        : focusField(key, idResolver)
                                }
                            >
                                {message}
                            </button>
                        </li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
