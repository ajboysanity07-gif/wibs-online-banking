import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

export const booleanToggleValue = (value: unknown): string => {
    if (value === true) {
        return 'true';
    }

    if (value === false) {
        return 'false';
    }

    return '';
};

type BooleanYesNoFieldProps = {
    id: string;
    value: unknown;
    onChange: (value: boolean | null) => void;
    disabled?: boolean;
    'aria-label'?: string;
    fullWidth?: boolean;
};

export function BooleanYesNoField({
    id,
    value,
    onChange,
    disabled,
    'aria-label': ariaLabel,
    fullWidth,
}: BooleanYesNoFieldProps) {
    return (
        <ToggleGroup
            id={id}
            type="single"
            variant="outline"
            value={booleanToggleValue(value)}
            onValueChange={(nextValue: string) =>
                onChange(nextValue === '' ? null : nextValue === 'true')
            }
            disabled={disabled}
            aria-label={ariaLabel}
            className={cn(fullWidth ? 'w-full' : 'w-fit')}
        >
            <ToggleGroupItem
                value="true"
                aria-label="Yes"
                className={cn(fullWidth && 'flex-1')}
            >
                Yes
            </ToggleGroupItem>
            <ToggleGroupItem
                value="false"
                aria-label="No"
                className={cn(fullWidth && 'flex-1')}
            >
                No
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
