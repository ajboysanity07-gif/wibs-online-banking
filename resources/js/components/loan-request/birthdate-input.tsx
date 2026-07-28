import { useEffect, useRef, useState } from 'react';
import { PatternFormat } from 'react-number-format';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type BirthdateInputProps = {
    id?: string;
    name?: string;
    value: string;
    onValueChange: (value: string) => void;
    onBlur?: () => void;
    className?: string;
    required?: boolean;
    readOnly?: boolean;
    disabled?: boolean;
};

const isoToDisplay = (iso: string): string => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);

    if (!match) {
        return '';
    }

    const [, year, month, day] = match;

    return `${month}/${day}/${year}`;
};

const digitsToIso = (digits: string): string => {
    if (digits.length !== 8) {
        return '';
    }

    const month = Number(digits.slice(0, 2));
    const day = Number(digits.slice(2, 4));
    const year = Number(digits.slice(4, 8));
    const date = new Date(year, month - 1, day);

    const isRealDate =
        date.getFullYear() === year &&
        date.getMonth() === month - 1 &&
        date.getDate() === day;

    if (!isRealDate) {
        return '';
    }

    return `${digits.slice(4, 8)}-${digits.slice(0, 2)}-${digits.slice(2, 4)}`;
};

// Native <input type="date"> segment-jumps and scrambles fast/pasted "MM/DD/YYYY"
// typing. This masked text input accepts typed slashes directly and only emits
// a value once the digits form a real calendar date.
export function BirthdateInput({
    id,
    name,
    value,
    onValueChange,
    onBlur,
    className,
    required,
    readOnly,
    disabled,
}: BirthdateInputProps) {
    const [displayValue, setDisplayValue] = useState(() => isoToDisplay(value));
    const lastEmittedValue = useRef(value);

    useEffect(() => {
        if (value !== lastEmittedValue.current) {
            setDisplayValue(isoToDisplay(value));
            lastEmittedValue.current = value;
        }
    }, [value]);

    return (
        <PatternFormat
            id={id}
            name={name}
            format="##/##/####"
            mask="_"
            placeholder="MM/DD/YYYY"
            value={displayValue}
            onValueChange={(values) => {
                setDisplayValue(values.formattedValue);

                const iso = digitsToIso(values.value);

                lastEmittedValue.current = iso;
                onValueChange(iso);
            }}
            onBlur={onBlur}
            className={cn('mt-1 block w-full', className)}
            customInput={Input}
            readOnly={readOnly}
            required={required}
            disabled={disabled}
            inputMode="numeric"
        />
    );
}
