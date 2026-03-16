import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import { Head, Link } from '@inertiajs/react';
import { Banknote, CalendarDays, Clock, List } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useMemberLoanSchedule } from '@/hooks/admin/use-member-loan-schedule';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { dashboard } from '@/routes/admin';
import {
    loanPayments,
    loanSchedule,
    loans as memberLoans,
    show as showMember,
} from '@/routes/admin/members';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberLoan,
    MemberLoanScheduleEntry,
    MemberLoanScheduleResponse,
    MemberLoanSummary,
} from '@/types/admin';

type MemberSummary = {
    user_id: number;
    member_name: string | null;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    loan: MemberLoan;
    summary: MemberLoanSummary;
    schedule: MemberLoanScheduleResponse;
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

const MobileScheduleCardSkeleton = () => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-3 w-20" />
            </div>
            <div className="space-y-2 text-right">
                <Skeleton className="ml-auto h-3 w-16" />
                <Skeleton className="ml-auto h-6 w-20" />
            </div>
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3">
            {Array.from({ length: 3 }).map((_, index) => (
                <div
                    key={`schedule-card-meta-${index}`}
                    className="flex items-center justify-between"
                >
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
    </div>
);

const MobileScheduleCardSkeletonList = ({ rows = 3 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MobileScheduleCardSkeleton key={`schedule-card-${index}`} />
        ))}
    </div>
);

const MobileScheduleCard = ({ entry }: { entry: MemberLoanScheduleEntry }) => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">
                    {formatDate(entry.date_pay)}
                </p>
                <p className="text-xs text-muted-foreground">
                    Control No: {entry.control_no ?? '--'}
                </p>
            </div>
            <div className="text-right">
                <p className="text-xs text-muted-foreground">Amortization</p>
                <p className="text-lg font-semibold tabular-nums">
                    {formatCurrency(entry.amortization)}
                </p>
            </div>
        </div>
        <div className="mt-3 rounded-md border border-border/60 bg-muted/40 p-3">
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Interest</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(entry.interest)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Balance</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(entry.balance)}
                </span>
            </div>
        </div>
    </div>
);

export default function MemberLoanSchedule({
    member,
    loan,
    summary,
    schedule,
}: Props) {
    const isMobile = useIsMobile();
    const loanNumber = loan.lnnumber ?? null;
    const calendarRef = useRef<FullCalendar | null>(null);
    const [selectedEntry, setSelectedEntry] =
        useState<MemberLoanScheduleEntry | null>(null);

    const { items, loading, error, refresh } = useMemberLoanSchedule(
        member.user_id,
        loanNumber,
        {
            initial: schedule,
            enabled: Boolean(member.acctno && loanNumber),
        },
    );

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

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin Dashboard', href: dashboard().url },
        { title: 'Member profile', href: showMember(member.user_id).url },
        { title: 'Loans', href: memberLoans(member.user_id).url },
        { title: 'Schedule', href: '#' },
    ];

    const balanceValue = formatCurrency(summary.balance);
    const nextPayment = summary.next_payment_date
        ? formatDate(summary.next_payment_date)
        : 'No upcoming schedule';
    const lastPayment = summary.last_payment_date
        ? formatDate(summary.last_payment_date)
        : 'No payment recorded yet';
    const showSkeleton = loading && items.length === 0;
    const canNavigate = Boolean(member.acctno && loanNumber);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan Schedule" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">
                            Loan Schedule
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {member.member_name ?? 'Member'} - Loan{' '}
                            {loan.lnnumber ?? '--'}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Account No: {member.acctno ?? '--'} | Loan Type:{' '}
                            {loan.lntype ?? '--'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <ToggleGroup
                            type="single"
                            value="schedule"
                            variant="outline"
                            size="sm"
                            className="rounded-md bg-muted/40 p-1"
                            aria-label="Loan detail views"
                        >
                            {canNavigate ? (
                                <ToggleGroupItem
                                    value="schedule"
                                    asChild
                                    className="data-[state=on]:font-semibold"
                                >
                                    <Link
                                        href={
                                            loanSchedule({
                                                user: member.user_id,
                                                loanNumber: loanNumber ?? '',
                                            }).url
                                        }
                                        aria-current="page"
                                    >
                                        Schedule
                                        <span className="sr-only">
                                            {' '}
                                            (current)
                                        </span>
                                    </Link>
                                </ToggleGroupItem>
                            ) : (
                                <ToggleGroupItem
                                    value="schedule"
                                    disabled
                                    className="data-[state=on]:font-semibold"
                                >
                                    Schedule
                                </ToggleGroupItem>
                            )}
                            {canNavigate ? (
                                <ToggleGroupItem
                                    value="payments"
                                    asChild
                                    className="data-[state=on]:font-semibold"
                                >
                                    <Link
                                        href={
                                            loanPayments({
                                                user: member.user_id,
                                                loanNumber: loanNumber ?? '',
                                            }).url
                                        }
                                    >
                                        Payments
                                    </Link>
                                </ToggleGroupItem>
                            ) : (
                                <ToggleGroupItem
                                    value="payments"
                                    disabled
                                    className="data-[state=on]:font-semibold"
                                >
                                    Payments
                                </ToggleGroupItem>
                            )}
                        </ToggleGroup>
                        <Button asChild variant="outline" size="sm">
                            <Link href={memberLoans(member.user_id).url}>
                                Back to loans
                            </Link>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={showMember(member.user_id).url}>
                                Back to profile
                            </Link>
                        </Button>
                    </div>
                </div>

                {!member.acctno || !loanNumber ? (
                    <Alert>
                        <AlertTitle>Loan not available</AlertTitle>
                        <AlertDescription>
                            This member needs a valid loan number and account
                            number before the schedule can be displayed.
                        </AlertDescription>
                    </Alert>
                ) : null}

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

                <Card>
                    <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Schedule Calendar</CardTitle>
                            <CardDescription>
                                Visual timeline of upcoming amortizations.
                            </CardDescription>
                        </div>
                        {loading ? (
                            <span className="text-xs text-muted-foreground">
                                Updating...
                            </span>
                        ) : null}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {error ? (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Unable to load schedule
                                </AlertTitle>
                                <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span>{error}</span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => void refresh()}
                                    >
                                        Retry
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        ) : null}
                        {showSkeleton ? (
                            <CalendarSkeleton />
                        ) : items.length === 0 ? (
                            <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                No schedule entries found for this loan.
                            </div>
                        ) : (
                            <FullCalendar
                                ref={calendarRef}
                                plugins={[
                                    dayGridPlugin,
                                    timeGridPlugin,
                                    listPlugin,
                                    interactionPlugin,
                                ]}
                                initialView={isMobile ? 'listMonth' : 'dayGridMonth'}
                                headerToolbar={{
                                    left: 'prev,next today',
                                    center: 'title',
                                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
                                }}
                                height="auto"
                                events={events}
                                eventClick={(info) => {
                                    const scheduleEntry =
                                        info.event.extendedProps
                                            .schedule as MemberLoanScheduleEntry | undefined;
                                    setSelectedEntry(scheduleEntry ?? null);
                                }}
                                eventDidMount={(info) => {
                                    const entry = info.event.extendedProps
                                        .schedule as MemberLoanScheduleEntry | undefined;
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
                                            Due {formatDate(selectedEntry.date_pay)}
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
                                            {formatCurrency(selectedEntry.amortization)}
                                        </p>
                                    </div>
                                    <div className="rounded-md border border-border/60 bg-muted/40 px-3 py-2">
                                        <p className="text-xs text-muted-foreground">
                                            Interest
                                        </p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatCurrency(selectedEntry.interest)}
                                        </p>
                                    </div>
                                    <div className="rounded-md border border-border/60 bg-muted/40 px-3 py-2">
                                        <p className="text-xs text-muted-foreground">
                                            Balance
                                        </p>
                                        <p className="text-sm font-semibold tabular-nums">
                                            {formatCurrency(selectedEntry.balance)}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Schedule Details</CardTitle>
                            <CardDescription>
                                Exact amortization entries for this loan.
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <List className="h-4 w-4" />
                            <span>{items.length} entries</span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {showSkeleton ? (
                            <>
                                <div className="md:hidden" aria-busy="true">
                                    <MobileScheduleCardSkeletonList rows={3} />
                                </div>
                                <div className="hidden md:block" aria-busy="true">
                                    <TableSkeleton
                                        columns={scheduleTableSkeletonColumns}
                                        rows={6}
                                        className="rounded-md border"
                                        tableClassName="min-w-[720px]"
                                    />
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="space-y-3 md:hidden">
                                    {items.length === 0 ? (
                                        <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                            No schedule entries available yet.
                                        </div>
                                    ) : (
                                        items.map((entry, index) => (
                                            <MobileScheduleCard
                                                key={
                                                    entry.control_no ??
                                                    `schedule-${index}`
                                                }
                                                entry={entry}
                                            />
                                        ))
                                    )}
                                </div>
                                <div className="hidden rounded-md border md:block">
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
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
