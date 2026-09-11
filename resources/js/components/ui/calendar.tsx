import { ChevronLeft, ChevronRight } from 'lucide-react';
import * as React from 'react';
import { DayPicker } from 'react-day-picker';

import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type CalendarProps = React.ComponentProps<typeof DayPicker>;

function Calendar({
    className,
    classNames,
    showOutsideDays = true,
    captionLayout = 'dropdown',
    ...props
}: CalendarProps) {
    return (
        <DayPicker
            showOutsideDays={showOutsideDays}
            captionLayout={captionLayout}
            className={cn('p-3', className)}
            classNames={{
                months: 'flex flex-col sm:flex-row gap-2',
                month: 'flex flex-col gap-4',
                month_caption: 'flex justify-center pt-1 relative items-center w-full gap-1',
                caption_label: 'text-sm font-medium',
                dropdowns: 'flex items-center justify-center gap-1 text-sm font-medium',
                dropdown_root: 'relative border rounded-md',
                dropdown: 'absolute inset-0 opacity-0 cursor-pointer',
                months_dropdown: 'px-2 py-1',
                years_dropdown: 'px-2 py-1',
                nav: 'flex items-center justify-between absolute inset-x-0 top-0',
                button_previous: cn(
                    buttonVariants({ variant: 'outline' }),
                    'size-7 bg-transparent p-0 opacity-50 hover:opacity-100',
                ),
                button_next: cn(
                    buttonVariants({ variant: 'outline' }),
                    'size-7 bg-transparent p-0 opacity-50 hover:opacity-100',
                ),
                month_grid: 'w-full border-collapse space-x-1',
                weekdays: 'flex',
                weekday: 'text-muted-foreground rounded-md w-8 font-normal text-[0.8rem]',
                week: 'flex w-full mt-2',
                day: 'relative p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([aria-selected])]:bg-accent',
                day_button: cn(
                    buttonVariants({ variant: 'ghost' }),
                    'size-8 p-0 font-normal aria-selected:opacity-100',
                ),
                range_start: 'day-range-start',
                range_end: 'day-range-end',
                selected: 'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground rounded-md',
                today: 'bg-accent text-accent-foreground rounded-md',
                outside: 'day-outside text-muted-foreground aria-selected:text-muted-foreground opacity-50',
                disabled: 'text-muted-foreground opacity-50',
                hidden: 'invisible',
                ...classNames,
            }}
            components={{
                Chevron: ({ orientation, ...chevronProps }) =>
                    orientation === 'left' ? (
                        <ChevronLeft className="size-4" {...chevronProps} />
                    ) : (
                        <ChevronRight className="size-4" {...chevronProps} />
                    ),
            }}
            {...props}
        />
    );
}

export { Calendar };
