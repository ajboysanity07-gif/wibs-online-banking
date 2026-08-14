import { NumericFormat } from 'react-number-format';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type AdornedNumberInputProps = {
    id?: string;
    value: string;
    onValueChange: (value: string) => void;
    onBlur?: () => void;
    className?: string;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
};

export function CurrencyInput({
    id,
    value,
    onValueChange,
    onBlur,
    className,
    placeholder = '0.00',
    disabled,
    required,
}: AdornedNumberInputProps) {
    return (
        <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground">
                ₱
            </span>
            <NumericFormat
                id={id}
                className={cn('mt-1 block w-full pl-8', className)}
                value={value}
                onValueChange={(values) => onValueChange(values.value)}
                onBlur={onBlur}
                thousandSeparator
                decimalScale={2}
                fixedDecimalScale
                allowNegative={false}
                placeholder={placeholder}
                inputMode="decimal"
                valueIsNumericString
                customInput={Input}
                disabled={disabled}
                required={required}
            />
        </div>
    );
}

// Rates are stored as decimal fractions everywhere outside this component (see LoanRequestDataService); conversion happens only here.
const PERCENT_DECIMAL_SCALE = 2;

const fractionToPercentDisplay = (fraction: string): string => {
    if (fraction.trim() === '') {
        return '';
    }

    const numeric = Number(fraction);

    if (Number.isNaN(numeric)) {
        return '';
    }

    return String(Number((numeric * 100).toFixed(PERCENT_DECIMAL_SCALE + 4)));
};

const percentToFraction = (percentValue: string): string => {
    if (percentValue.trim() === '') {
        return '';
    }

    const numeric = Number(percentValue);

    if (Number.isNaN(numeric)) {
        return '';
    }

    return String(Number((numeric / 100).toFixed(PERCENT_DECIMAL_SCALE + 4)));
};

export function PercentInput({
    id,
    value,
    onValueChange,
    onBlur,
    className,
    placeholder,
    disabled,
    required,
}: AdornedNumberInputProps) {
    return (
        <div className="relative">
            <NumericFormat
                id={id}
                className={cn('mt-1 block w-full pr-8', className)}
                value={fractionToPercentDisplay(value)}
                onValueChange={(values) =>
                    onValueChange(percentToFraction(values.value))
                }
                onBlur={onBlur}
                decimalScale={PERCENT_DECIMAL_SCALE}
                allowNegative={false}
                placeholder={placeholder}
                inputMode="decimal"
                valueIsNumericString
                customInput={Input}
                disabled={disabled}
                required={required}
            />
            <span className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-muted-foreground">
                %
            </span>
        </div>
    );
}

type MonthsInputProps = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    className?: string;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
};

export function MonthsInput({
    id,
    value,
    onChange,
    onBlur,
    className,
    placeholder,
    disabled,
    required,
}: MonthsInputProps) {
    return (
        <div className="relative">
            <NumericFormat
                id={id}
                className={cn('pr-16', className)}
                value={value}
                onValueChange={(values) => onChange(values.value)}
                onBlur={onBlur}
                decimalScale={0}
                allowNegative={false}
                placeholder={placeholder}
                inputMode="numeric"
                valueIsNumericString
                customInput={Input}
                disabled={disabled}
                required={required}
            />
            <span className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-muted-foreground">
                months
            </span>
        </div>
    );
}

type YearsInputProps = MonthsInputProps;

export function YearsInput({
    id,
    value,
    onChange,
    onBlur,
    className,
    placeholder,
    disabled,
    required,
}: YearsInputProps) {
    return (
        <div className="relative">
            <NumericFormat
                id={id}
                className={cn('pr-14', className)}
                value={value}
                onValueChange={(values) => onChange(values.value)}
                onBlur={onBlur}
                decimalScale={0}
                allowNegative={false}
                placeholder={placeholder}
                inputMode="numeric"
                valueIsNumericString
                customInput={Input}
                disabled={disabled}
                required={required}
            />
            <span className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-muted-foreground">
                years
            </span>
        </div>
    );
}
