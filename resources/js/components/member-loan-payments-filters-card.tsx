import type { ReactNode } from 'react';
import { Filter } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatCurrency } from '@/lib/formatters';
import type { MemberLoanPaymentsFilters } from '@/types/admin';

type PaymentRangePreset = {
    value: MemberLoanPaymentsFilters['range'];
    label: string;
};

type MemberLoanPaymentsFiltersCardProps = {
    filters: MemberLoanPaymentsFilters;
    presets: PaymentRangePreset[];
    isUpdating?: boolean;
    description: string;
    openingBalance?: number | null;
    closingBalance?: number | null;
    onRangeChange: (range: MemberLoanPaymentsFilters['range']) => void;
    onStartChange: (value: string) => void;
    onEndChange: (value: string) => void;
    footer?: ReactNode;
};

export function MemberLoanPaymentsFiltersCard({
    filters,
    presets,
    isUpdating = false,
    description,
    openingBalance,
    closingBalance,
    onRangeChange,
    onStartChange,
    onEndChange,
    footer,
}: MemberLoanPaymentsFiltersCardProps) {
    const filtersReady =
        filters.range !== 'custom' ||
        (Boolean(filters.start) && Boolean(filters.end));

    return (
        <Card>
            <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Payment Filters</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                {isUpdating ? (
                    <span className="text-xs text-muted-foreground">
                        Updating...
                    </span>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Filter className="h-4 w-4 text-muted-foreground" />
                    {presets.map((preset) => (
                        <Button
                            key={preset.value}
                            type="button"
                            size="sm"
                            variant={
                                filters.range === preset.value
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => onRangeChange(preset.value)}
                        >
                            {preset.label}
                        </Button>
                    ))}
                </div>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div className="space-y-2">
                        <p className="text-xs font-medium text-muted-foreground">
                            Start date
                        </p>
                        <Input
                            type="date"
                            value={filters.start ?? ''}
                            onChange={(event) =>
                                onStartChange(event.target.value)
                            }
                            disabled={filters.range !== 'custom'}
                        />
                    </div>
                    <div className="space-y-2">
                        <p className="text-xs font-medium text-muted-foreground">
                            End date
                        </p>
                        <Input
                            type="date"
                            value={filters.end ?? ''}
                            onChange={(event) =>
                                onEndChange(event.target.value)
                            }
                            disabled={filters.range !== 'custom'}
                        />
                    </div>
                    <div className="rounded-md border border-border/60 bg-muted/40 p-3">
                        <p className="text-xs text-muted-foreground">
                            Opening / Closing
                        </p>
                        <p className="text-sm font-medium tabular-nums">
                            {formatCurrency(openingBalance)} /{' '}
                            {formatCurrency(closingBalance)}
                        </p>
                    </div>
                </div>
                {filters.range === 'custom' && !filtersReady ? (
                    <p className="text-xs text-muted-foreground">
                        Select a start and end date to apply the custom range.
                    </p>
                ) : null}
                {footer ? (
                    <div className="flex flex-wrap items-center gap-2">
                        {footer}
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}
