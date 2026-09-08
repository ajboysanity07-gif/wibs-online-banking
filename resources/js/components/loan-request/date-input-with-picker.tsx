import { DatePickerTrigger } from '@/components/ui/date-picker-trigger';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type DateInputWithPickerProps = {
    id?: string;
    name?: string;
    value: string;
    onChange: (value: string) => void;
    className?: string;
    required?: boolean;
    disabled?: boolean;
    'aria-invalid'?: boolean;
    'aria-label'?: string;
};

export function DateInputWithPicker({
    id,
    name,
    value,
    onChange,
    className,
    required,
    disabled,
    'aria-invalid': ariaInvalid,
    'aria-label': ariaLabel,
}: DateInputWithPickerProps) {
    return (
        <div className={cn('mt-1 flex gap-2', className)}>
            <Input
                id={id}
                name={name}
                type="date"
                value={value}
                className="block w-full"
                onChange={(event) => onChange(event.target.value)}
                required={required}
                disabled={disabled}
                aria-invalid={ariaInvalid}
            />
            <DatePickerTrigger
                value={value}
                onSelect={onChange}
                disabled={disabled}
                aria-label={ariaLabel ?? 'Choose date'}
            />
        </div>
    );
}
