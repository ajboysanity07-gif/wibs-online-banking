import { Download, Printer, RotateCcw } from 'lucide-react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { MemberLoanPaymentsFilters } from '@/types/admin';

type PaymentRangePreset = {
    value: MemberLoanPaymentsFilters['range'];
    label: string;
};

type PaymentFiltersCardProps = {
    filters: MemberLoanPaymentsFilters;
    presets: readonly PaymentRangePreset[];
    minAmount: string;
    maxAmount: string;
    filtersReady: boolean;
    onRangeChange: (range: MemberLoanPaymentsFilters['range']) => void;
    onStartChange: (value: string) => void;
    onEndChange: (value: string) => void;
    onMinAmountChange: (value: string) => void;
    onMaxAmountChange: (value: string) => void;
    onReset: () => void;
    onDownload: () => void;
    printUrl: string | null;
    onPrint: () => void;
    canExport: boolean;
};

export function PaymentFiltersCard({
    filters,
    presets,
    minAmount,
    maxAmount,
    filtersReady,
    onRangeChange,
    onStartChange,
    onEndChange,
    onMinAmountChange,
    onMaxAmountChange,
    onReset,
    onDownload,
    printUrl,
    onPrint,
    canExport,
}: PaymentFiltersCardProps) {
    const isCustom = filters.range === 'custom';

    return (
        <SurfaceCard variant="default" padding="md" className="space-y-5">
            <SectionHeader
                title="Payment filters"
                description="Filter and download payment records."
                actions={
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={onReset}
                    >
                        <RotateCcw aria-hidden="true" />
                        Reset
                    </Button>
                }
                titleClassName="text-base font-semibold"
            />

            <div className="flex flex-col gap-4">
                <div
                    role="group"
                    aria-label="Period"
                    className="flex flex-col gap-2"
                >
                    <p className="text-[12.5px] font-bold tracking-[0.06em] text-muted-foreground uppercase">
                        Period
                    </p>
                    <div className="-mx-1 flex flex-wrap gap-2 px-1 pb-1">
                        {presets.map((preset) => {
                            const active = filters.range === preset.value;

                            return (
                                <Button
                                    key={preset.value}
                                    type="button"
                                    variant="ghost"
                                    aria-pressed={active}
                                    onClick={() => onRangeChange(preset.value)}
                                    className={cn(
                                        'h-auto min-h-11 gap-1.5 rounded-full border px-4 text-[13.5px] font-semibold md:h-auto',
                                        active
                                            ? 'border-primary/30 bg-primary/10 text-primary hover:bg-primary/10 hover:text-primary'
                                            : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-muted-foreground',
                                    )}
                                >
                                    {preset.label}
                                </Button>
                            );
                        })}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <Label
                            htmlFor="payment-start-date"
                            className="text-[12.5px] leading-normal font-semibold tracking-[0.05em] text-muted-foreground uppercase"
                        >
                            Start date
                        </Label>
                        <Input
                            id="payment-start-date"
                            type="date"
                            value={filters.start ?? ''}
                            onChange={(event) =>
                                onStartChange(event.target.value)
                            }
                            disabled={!isCustom}
                        />
                    </div>
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <Label
                            htmlFor="payment-end-date"
                            className="text-[12.5px] leading-normal font-semibold tracking-[0.05em] text-muted-foreground uppercase"
                        >
                            End date
                        </Label>
                        <Input
                            id="payment-end-date"
                            type="date"
                            value={filters.end ?? ''}
                            onChange={(event) =>
                                onEndChange(event.target.value)
                            }
                            disabled={!isCustom}
                        />
                    </div>
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <Label
                            htmlFor="payment-min-amount"
                            className="text-[12.5px] leading-normal font-semibold tracking-[0.05em] text-muted-foreground uppercase"
                        >
                            Amount from
                        </Label>
                        <Input
                            id="payment-min-amount"
                            type="number"
                            inputMode="numeric"
                            min={0}
                            step={100}
                            placeholder="0"
                            value={minAmount}
                            onChange={(event) =>
                                onMinAmountChange(event.target.value)
                            }
                        />
                    </div>
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <Label
                            htmlFor="payment-max-amount"
                            className="text-[12.5px] leading-normal font-semibold tracking-[0.05em] text-muted-foreground uppercase"
                        >
                            Amount to
                        </Label>
                        <Input
                            id="payment-max-amount"
                            type="number"
                            inputMode="numeric"
                            min={0}
                            step={100}
                            placeholder="Any"
                            value={maxAmount}
                            onChange={(event) =>
                                onMaxAmountChange(event.target.value)
                            }
                        />
                    </div>
                </div>

                {isCustom && !filtersReady ? (
                    <p className="text-xs text-muted-foreground">
                        Select a start and end date to apply the custom range.
                    </p>
                ) : null}

                <div className="flex flex-wrap items-center gap-2">
                    <span className="flex-1" aria-hidden="true" />
                    <Button
                        type="button"
                        onClick={onDownload}
                        disabled={!canExport}
                    >
                        <Download aria-hidden="true" />
                        Download
                    </Button>
                    {printUrl ? (
                        <Button asChild variant="outline">
                            <a
                                href={printUrl}
                                target="_blank"
                                rel="noreferrer"
                                onClick={onPrint}
                            >
                                <Printer aria-hidden="true" />
                                Print
                            </a>
                        </Button>
                    ) : (
                        <Button type="button" variant="outline" disabled>
                            <Printer aria-hidden="true" />
                            Print
                        </Button>
                    )}
                </div>
            </div>
        </SurfaceCard>
    );
}
