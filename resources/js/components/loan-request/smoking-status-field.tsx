import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

const SMOKING_STATUS_OPTIONS = [
    { value: 'none', label: 'None' },
    { value: 'light', label: 'Light (less than 10/day)' },
    { value: 'heavy', label: 'Heavy (more than 10/day)' },
];

type SmokingStatusFieldProps = {
    id: string;
    value: unknown;
    onChange: (value: string | null) => void;
    disabled?: boolean;
    'aria-label'?: string;
};

export function SmokingStatusField({
    id,
    value,
    onChange,
    disabled,
    'aria-label': ariaLabel,
}: SmokingStatusFieldProps) {
    return (
        <ToggleGroup
            id={id}
            type="single"
            variant="outline"
            value={typeof value === 'string' ? value : ''}
            onValueChange={(nextValue: string) =>
                onChange(nextValue === '' ? null : nextValue)
            }
            disabled={disabled}
            aria-label={ariaLabel}
            className="w-fit flex-wrap"
        >
            {SMOKING_STATUS_OPTIONS.map((option) => (
                <ToggleGroupItem key={option.value} value={option.value} aria-label={option.label}>
                    {option.label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
