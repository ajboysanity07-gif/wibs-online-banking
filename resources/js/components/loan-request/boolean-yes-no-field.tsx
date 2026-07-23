import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

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
};

export function BooleanYesNoField({
    id,
    value,
    onChange,
    disabled,
    'aria-label': ariaLabel,
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
            className="w-fit"
        >
            <ToggleGroupItem value="true" aria-label="Yes">
                Yes
            </ToggleGroupItem>
            <ToggleGroupItem value="false" aria-label="No">
                No
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
