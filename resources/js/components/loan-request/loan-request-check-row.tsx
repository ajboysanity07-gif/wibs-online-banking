import type { ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    children: ReactNode;
    invalid?: boolean;
    ariaLabel?: string;
};

/** Tappable, tinted confirmation row with a 24px checkbox. */
export function LoanRequestCheckRow({
    id,
    checked,
    onCheckedChange,
    children,
    invalid,
    ariaLabel,
}: Props) {
    return (
        <label
            htmlFor={id}
            className={cn(
                'flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border border-border bg-secondary/40 p-4 text-sm leading-snug transition-colors hover:bg-secondary/70 has-data-[state=checked]:border-primary/50 has-data-[state=checked]:bg-secondary',
                invalid && 'border-destructive',
            )}
        >
            <Checkbox
                id={id}
                checked={checked}
                aria-label={ariaLabel}
                aria-invalid={invalid}
                onCheckedChange={(value) => onCheckedChange(value === true)}
                className="mt-px size-6 rounded-md [&_svg]:size-4"
            />
            <span className="min-w-0 flex-1">{children}</span>
        </label>
    );
}
