import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

type DatePickerTriggerProps = {
    value: string;
    onSelect: (iso: string) => void;
    disabled?: boolean;
    fromYear?: number;
    toDate?: Date;
    'aria-label'?: string;
};

const isoToLocalDate = (iso: string): Date | undefined => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);

    if (!match) {
        return undefined;
    }

    const [, year, month, day] = match;

    return new Date(Number(year), Number(month) - 1, Number(day));
};

const localDateToIso = (date: Date): string => {
    const year = String(date.getFullYear()).padStart(4, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

export function DatePickerTrigger({
    value,
    onSelect,
    disabled,
    fromYear,
    toDate,
    'aria-label': ariaLabel = 'Choose date',
}: DatePickerTriggerProps) {
    const [open, setOpen] = useState(false);
    const selected = isoToLocalDate(value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="shrink-0"
                    disabled={disabled}
                    aria-label={ariaLabel}
                >
                    <CalendarIcon className="size-4" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    selected={selected}
                    defaultMonth={selected}
                    startMonth={fromYear ? new Date(fromYear, 0) : undefined}
                    endMonth={toDate}
                    disabled={toDate ? { after: toDate } : undefined}
                    onSelect={(date) => {
                        if (date) {
                            onSelect(localDateToIso(date));
                        }
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
