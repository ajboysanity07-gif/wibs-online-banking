import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import FullCalendar from '@fullcalendar/react';
import { Banknote, CalendarDays, Clock, List } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import {
    MemberMobileCard,
    MemberMobileCardSkeleton,
} from '@/components/member-mobile-card';
import { MemberRecordsCard } from '@/components/member-records-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import { useIsMobile } from '@/hooks/use-mobile';
import { formatCurrency, formatDate } from '@/lib/formatters';
import type { MemberLoanScheduleEntry, MemberLoanSummary } from '@/types/admin';

type MemberLoanScheduleSectionsProps = {
    items: MemberLoanScheduleEntry[];
    summary: MemberLoanSummary;
    isUpdating?: boolean;
    error?: string | null;
    onRetry?: () => void;
};

const scheduleTableSkeletonColumns = [
    { headerClassName: 'w-24', cellClassName: 'w-24' },
    { headerClassName: 'w-24', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
];

const SummarySkeleton = () => (
    <div className="grid gap-4 md:grid-cols-3">
        {Array.from({ length: 3 }).map((_, index) => (
            <Card key={`loan-summary-skeleton-${index}`}>
                <CardContent className="space-y-3 p-6">
                    <Skeleton className="h-3 w-24" />
                    <Skeleton className="h-8 w-32" />
                    <Skeleton className="h-3 w-28" />
                </CardContent>
            </Card>
        ))}
    </div>
);

const CalendarSkeleton = () => (
    <div className="space-y-4">
        <div className="flex items-center justify-between gap-3">
            <Skeleton className="h-5 w-32" />
            <Skeleton className="h-8 w-40" />
        </div>
        <Skeleton className="h-72 w-full" />
    </div>
);

const MobileScheduleCardSkeletonList = ({ rows = 3 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MemberMobileCardSkeleton key={`schedule-card-${index}`} />
        ))}
    </div>
);

const MobileScheduleCard = ({ entry }: { entry: MemberLoanScheduleEntry }) => (
    <MemberMobileCard
        title={formatDate(entry.date_pay)}
        subtitle={`Control No: ${entry.control_no ?? '--'}`}
        valueLabel="Amortization"
        value={formatCurrency(entry.amortization)}
        meta={[
            { label: 'Interest', value: formatCurrency(entry.interest) },
            { label: 'Balance', value: formatCurrency(entry.balance) },
        ]}
    />
);

export function MemberLoanScheduleSections({
    items,
    summary,
    isUpdating = false,
    error = null,
    onRetry,
}: MemberLoanScheduleSectionsProps) {
    const isMobile = useIsMobile();
    const calendarRef = useRef<FullCalendar | null>(null);
    const [selectedEntry, setSelectedEntry] =
        useState<MemberLoanScheduleEntry | null>(null);

    useEffect(() => {
        const calendarApi = calendarRef.current?.getApi();

        if (!calendarApi) {
            return;
        }

        calendarApi.changeView(isMobile ? 'listMonth' : 'dayGridMonth');
    }, [isMobile]);

    const events = useMemo(
        () =>
            items
                .filter((entry) => Boolean(entry.date_pay))
                .map((entry, index) => ({
                    id: entry.control_no
                        ? String(entry.control_no)
                        : `schedule-${index}`,
                    title: `Amortization ${formatCurrency(entry.amortization)}`,
                    start: entry.date_pay ?? undefined,
                    allDay: true,
                    extendedProps: {
                        schedule: entry,
                        interest: entry.interest,
                        balance: entry.balance,
                        controlNo: entry.control_no,
                    },
                })),
        [items],
    );

    const balanceValue = formatCurrency(summary.balance);
    const nextPayment = summary.next_payment_date
        ? formatDate(summary.next_payment_date)
        : 'No upcoming schedule';
    const lastPayment = summary.last_payment_date
        ? formatDate(summary.last_payment_date)
        : 'No payment recorded yet';
    const showSkeleton = isUpdating && items.length === 0;

    return (
        <>
            {showSkeleton ? (
                <SummarySkeleton />
            ) : (
                <div className="grid gap-4 md:grid-cols-3">
                    <MemberDetailPrimaryCard
                        title="Outstanding Loan Balance"
                        value={balanceValue}
                        helper="Current balance for this loan."
                        icon={Banknote}
                        accent="primary"
                    />
                    <MemberDetailSupportingCard
                        title="Next Payment Date"
                        description="Nearest scheduled payment date."
                        value={nextPayment}
                        icon={CalendarDays}
                        accent="primary"
                    />
                    <MemberDetailSupportingCard
                        title="Last Payment Date"
                        description="Most recent payment recorded."
                        value={lastPayment}
                        icon={Clock}
                        accent="accent"
                    />
                </div>
            )}

            <MemberRecordsCard
                title="Schedule Calendar"
                description="Visual timeline of upcoming amortizations."
                isUpdating={isUpdating}
                error={error}
                errorTitle="Unable to load schedule"
                onRetry={onRetry}
                showSkeleton={showSkeleton}
                skeletonBody={<CalendarSkeleton />}
                body={
                    <>
                        {items.length === 0 ? (
                            <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                No schedule entries found for this loan.
                            </div>
                        ) : (
                            <FullCalendar
                                ref={calendarRef}
                                plugins={[
                                    dayGridPlugin,
                                    listPlugin,
                                    interactionPlugin,
                                ]}
                                initialView={
                                    isMobile ? 'listMonth' : 'dayGridMonth'
                                }
                                headerToolbar={{
                                    left: 'prev,next today',
                                    center: 'title',
                                    right: 'dayGridMonth,listMonth',
                                }}
                                buttonText={{
                                    dayGridMonth: 'Calendar',
                                }}
                                height="auto"
                                events={events}
                                eventClick={(info) => {
                                    const scheduleEntry = info.event
                                        .extendedProps.schedule as
                                        | MemberLoanScheduleEntry
                                        | undefined;
                                    setSelectedEntry(scheduleEntry ?? null);
                                }}
                                eventDidMount={(info) => {
                                    const entry = info.event.extendedProps
                                        .schedule as
                                        | MemberLoanScheduleEntry
                                        | undefined;
                                    if (!entry) {
                                        return;
                                    }
                                    info.el.setAttribute(
                                        'title',
                                        `Amortization: ${formatCurrency(entry.amortization)} | Interest: ${formatCurrency(entry.interest)} | Balance: ${formatCurrency(entry.balance)}`,
                                    );
                                }}
                            />
                        )}
                        {selectedEntry ? (
                            <div className="rounded-lg border border-border bg-card p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="space-y-1">
                                        <p className="text-sm font-semibold">
                                            Selected payment
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Due{' '}
                                            {formatDate(selectedEntry.date_pay)}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setSelectedEntry(null)}
                                    >
                                        Clear
                                    </Button>
                                </div>
                                <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-md border border-border/60 bg-muted/40 px-3 py-2">
                                        <p className="text-xs text-muted-foreground">
                                            Amortization
                                        </p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatCurrency(
                                                selectedEntry.amortization,
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-md border border-border/60 bg-muted/40 px-3 py-2">
                                        <p className="text-xs text-muted-foreground">
                                            Interest
                                        </p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatCurrency(
                                                selectedEntry.interest,
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-md border border-border/60 bg-muted/40 px-3 py-2">
                                        <p className="text-xs text-muted-foreground">
                                            Balance
                                        </p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatCurrency(
                                                selectedEntry.balance,
                                            )}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        ) : null}
                    </>
                }
            />

            <MemberRecordsCard
                title="Schedule Details"
                description="Exact amortization entries for this loan."
                headerAccessory={
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <List className="h-4 w-4" />
                        <span>{items.length} entries</span>
                    </div>
                }
                showSkeleton={showSkeleton}
                skeletonMobile={<MobileScheduleCardSkeletonList rows={3} />}
                skeletonDesktop={
                    <TableSkeleton
                        columns={scheduleTableSkeletonColumns}
                        rows={6}
                        className="rounded-md border"
                        tableClassName="min-w-[720px]"
                    />
                }
                mobileWrapperClassName="space-y-3"
                desktopWrapperClassName="rounded-md border"
                mobileContent={
                    items.length === 0 ? (
                        <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                            No schedule entries available yet.
                        </div>
                    ) : (
                        items.map((entry, index) => (
                            <MobileScheduleCard
                                key={entry.control_no ?? `schedule-${index}`}
                                entry={entry}
                            />
                        ))
                    )
                }
                desktopContent={
                    <Table className="min-w-[720px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Due date</TableHead>
                                <TableHead>Amortization</TableHead>
                                <TableHead>Interest</TableHead>
                                <TableHead>Balance</TableHead>
                                <TableHead>Control No</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="h-24 text-center text-sm text-muted-foreground"
                                    >
                                        No schedule entries available yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                items.map((entry, index) => (
                                    <TableRow
                                        key={
                                            entry.control_no ??
                                            `schedule-${index}`
                                        }
                                    >
                                        <TableCell className="font-medium">
                                            {formatDate(entry.date_pay)}
                                        </TableCell>
                                        <TableCell>
                                            {formatCurrency(entry.amortization)}
                                        </TableCell>
                                        <TableCell>
                                            {formatCurrency(entry.interest)}
                                        </TableCell>
                                        <TableCell>
                                            {formatCurrency(entry.balance)}
                                        </TableCell>
                                        <TableCell>
                                            {entry.control_no ?? '--'}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                }
            />
        </>
    );
}
