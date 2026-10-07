import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent,
} from 'react';
import { toast } from 'sonner';
import { DocumentsTab } from '@/components/loans/documents-tab';
import {
    formatLoanNumber,
    loanTypeLabel,
} from '@/components/loans/loan-presentation';
import { PaymentFiltersCard } from '@/components/loans/payment-filters-card';
import {
    PaymentLedgerCard,
    PaymentRetryState,
} from '@/components/loans/payment-ledger-card';
import {
    downloadTextFile,
    filterPaymentsByAmount,
    paymentsCsv,
} from '@/components/loans/payment-presentation';
import { PaymentSchedulePanel } from '@/components/loans/payment-schedule-panel';
import { PaymentSummaryStats } from '@/components/loans/payment-summary-stats';
import { TransactionSummaryDialog } from '@/components/loans/transaction-summary-dialog';
import { MemberDetailPageHeader } from '@/components/member-detail-page-header';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { MemberAccountAlert } from '@/features/member-accounts/components/member-account-alert';
import AppLayout from '@/layouts/app-layout';
import { loanPayments, loans as clientLoans } from '@/routes/client';
import loanPaymentsRoutes from '@/routes/client/loan-payments';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberLoan,
    MemberLoanPayment,
    MemberLoanPaymentsFilters,
    MemberLoanPaymentsResponse,
    MemberLoanScheduleEntry,
    MemberLoanSummary,
} from '@/types/admin';

type MemberSummary = {
    member_name: string | null;
    acctno: string | null;
    signatureUrl?: string | null;
};

type LoanManagerSummary = {
    name: string | null;
    role: string | null;
    signatureUrl?: string | null;
};

type Props = {
    member: MemberSummary;
    loanManager?: LoanManagerSummary | null;
    loan: MemberLoan;
    summary: MemberLoanSummary;
    payments: MemberLoanPaymentsResponse | null;
    schedule: { items: MemberLoanScheduleEntry[] } | null;
    documents?: {
        soaMonths: Array<{ month: string; transactions: number }>;
        certificateEligible: boolean;
    } | null;
    securityBalance?: number;
};

const presetRanges: Array<{
    value: MemberLoanPaymentsFilters['range'];
    label: string;
}> = [
    { value: 'all', label: 'All transactions' },
    { value: 'current_month', label: 'Current month' },
    { value: 'current_year', label: 'Current year' },
    { value: 'last_30_days', label: 'Last 30 days' },
    { value: 'custom', label: 'Custom range' },
];

const tabs = [
    { value: 'history', label: 'Payment history' },
    { value: 'schedule', label: 'Repayment schedule' },
    { value: 'documents', label: 'Documents' },
];

const tabFromHash = (): string => {
    if (typeof window === 'undefined') {
        return 'history';
    }

    const hash = window.location.hash.replace(/^#/, '');

    return tabs.some((tab) => tab.value === hash) ? hash : 'history';
};

export default function LoanPayments({
    member,
    loanManager = null,
    loan,
    summary,
    payments,
    schedule,
    documents = null,
    securityBalance = 0,
}: Props) {
    const loanNumber = loan.lnnumber ?? null;
    const perPage = payments?.meta.perPage ?? 10;
    const tabListRef = useRef<HTMLDivElement>(null);

    const [tab, setTabState] = useState(() => tabFromHash());
    const [filters, setFilters] = useState<MemberLoanPaymentsFilters>(
        payments?.filters ?? { range: 'all', start: null, end: null },
    );

    const setTab = (next: string) => {
        setTabState(next);

        if (typeof window !== 'undefined') {
            window.history.replaceState(null, '', `#${next}`);
        }
    };

    useEffect(() => {
        const onHashChange = () => setTabState(tabFromHash());

        window.addEventListener('hashchange', onHashChange);

        return () => window.removeEventListener('hashchange', onHashChange);
    }, []);
    const [minAmount, setMinAmount] = useState('');
    const [maxAmount, setMaxAmount] = useState('');
    const [selectedPayment, setSelectedPayment] =
        useState<MemberLoanPayment | null>(null);
    const [loading, setLoading] = useState(false);

    const filtersReady =
        filters.range !== 'custom' ||
        (Boolean(filters.start) && Boolean(filters.end));

    const items = useMemo(() => payments?.items ?? [], [payments]);
    const meta = payments?.meta ?? {
        page: 1,
        perPage: 10,
        total: 0,
        lastPage: 1,
    };
    const showSkeleton = loading && items.length === 0;
    const paymentsUnavailable = payments === null;

    const amountFilter = useMemo(
        () => ({
            min: minAmount === '' ? null : Number(minAmount),
            max: maxAmount === '' ? null : Number(maxAmount),
        }),
        [minAmount, maxAmount],
    );

    const visiblePayments = useMemo(
        () => filterPaymentsByAmount(items, amountFilter),
        [items, amountFilter],
    );

    const amountFilterActive =
        amountFilter.min !== null || amountFilter.max !== null;
    const loanNumberLabel = formatLoanNumber(loan.lnnumber);
    const loanType = loanTypeLabel(loan.lntype);
    const canViewLoan = Boolean(member.acctno && loanNumber);

    const notify = (message: string) => {
        toast(message, { position: 'bottom-center' });
    };

    const reloadPayments = (
        nextPage: number,
        nextFilters: MemberLoanPaymentsFilters,
    ) => {
        if (!loanNumber) {
            return;
        }

        setLoading(true);
        router.get(
            loanPayments(loanNumber).url,
            {
                page: nextPage,
                perPage,
                range: nextFilters.range,
                start: nextFilters.start ?? undefined,
                end: nextFilters.end ?? undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setLoading(false);
                },
            },
        );
    };

    const handlePageChange = (nextPage: number) => {
        if (nextPage === meta.page || !filtersReady) {
            return;
        }

        reloadPayments(nextPage, filters);
    };

    const updateRange = (range: MemberLoanPaymentsFilters['range']) => {
        const nextFilters = {
            range,
            start: range === 'custom' ? filters.start : null,
            end: range === 'custom' ? filters.end : null,
        };

        setFilters(nextFilters);
        setSelectedPayment(null);

        if (range !== 'custom' || (nextFilters.start && nextFilters.end)) {
            reloadPayments(1, nextFilters);
            notify(`Showing payments for ${rangeLabel(range)}.`);
        }
    };

    const updateStart = (value: string) => {
        const nextFilters = { ...filters, start: value || null };

        setFilters(nextFilters);

        if (
            filters.range !== 'custom' ||
            (nextFilters.start && nextFilters.end)
        ) {
            reloadPayments(1, nextFilters);
        }
    };

    const updateEnd = (value: string) => {
        const nextFilters = { ...filters, end: value || null };

        setFilters(nextFilters);

        if (
            filters.range !== 'custom' ||
            (nextFilters.start && nextFilters.end)
        ) {
            reloadPayments(1, nextFilters);
        }
    };

    const handleReset = () => {
        const nextFilters = { range: 'all' as const, start: null, end: null };

        setFilters(nextFilters);
        setMinAmount('');
        setMaxAmount('');
        reloadPayments(1, nextFilters);
        notify('Filters reset.');
    };

    const handleDownload = () => {
        if (visiblePayments.length === 0) {
            notify('No payments to download for this period.');

            return;
        }

        downloadTextFile(
            `${loanNumberLabel}-payments.csv`,
            paymentsCsv(visiblePayments),
        );
        notify('Payment records downloaded.');
    };

    const handleTabKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
            return;
        }

        event.preventDefault();

        const currentIndex = tabs.findIndex((item) => item.value === tab);
        const nextIndex =
            event.key === 'Home'
                ? 0
                : event.key === 'End'
                  ? tabs.length - 1
                  : (currentIndex +
                        (event.key === 'ArrowRight' ? 1 : -1) +
                        tabs.length) %
                    tabs.length;
        const nextTab = tabs[nextIndex];

        setTab(nextTab.value);

        tabListRef.current
            ?.querySelectorAll<HTMLButtonElement>('[role="tab"]')
            [nextIndex]?.focus();
    };

    const printUrl = loanNumber
        ? loanPaymentsRoutes.print(
              { loanNumber: loanNumber.toString() },
              {
                  query: {
                      range: filters.range,
                      start: filters.start ?? undefined,
                      end: filters.end ?? undefined,
                  },
              },
          ).url
        : null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Loans', href: clientLoans().url },
        {
            title: 'Payments',
            href: loanNumber ? loanPayments(loanNumber).url : clientLoans().url,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan Payments" />
            <PageShell>
                <MemberDetailPageHeader
                    title="Loan Payments"
                    subtitle={`Client ID ${member.acctno ?? '--'} · Loan ${loanNumberLabel}`}
                    meta={
                        <Badge
                            variant="outline"
                            className="bg-card font-semibold tabular-nums"
                        >
                            Account {member.acctno ?? '--'} · {loanType}
                        </Badge>
                    }
                    actions={
                        <Button asChild variant="outline">
                            <Link href={clientLoans().url}>
                                <ArrowLeft aria-hidden="true" />
                                Back to loans
                            </Link>
                        </Button>
                    }
                />

                {!canViewLoan ? (
                    <MemberAccountAlert
                        title="Account number missing"
                        description="Add an account number to view this loan's payment records."
                    />
                ) : null}

                <PaymentSummaryStats
                    loan={loan}
                    summary={summary}
                    securityBalance={securityBalance}
                    isLoading={showSkeleton}
                />

                <Tabs
                    value={tab}
                    onValueChange={setTab}
                    className="flex w-full flex-col"
                >
                    <div
                        ref={tabListRef}
                        onKeyDown={handleTabKeyDown}
                        className="w-full sm:w-auto"
                    >
                        <TabsList className="w-full rounded-[10px] border border-border bg-muted/60 p-1 sm:w-auto">
                            {tabs.map((item) => (
                                <TabsTrigger
                                    key={item.value}
                                    value={item.value}
                                    className="min-h-10 flex-1 rounded-[8px] px-4 text-[14.5px] font-semibold data-[state=active]:bg-card data-[state=active]:shadow-card"
                                >
                                    {item.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </div>

                    <TabsContent
                        value="history"
                        className="mt-4 flex flex-col gap-4"
                    >
                        <PaymentFiltersCard
                            filters={filters}
                            presets={presetRanges}
                            minAmount={minAmount}
                            maxAmount={maxAmount}
                            filtersReady={filtersReady}
                            canExport={canViewLoan && filtersReady}
                            printUrl={printUrl}
                            onRangeChange={updateRange}
                            onStartChange={updateStart}
                            onEndChange={updateEnd}
                            onMinAmountChange={setMinAmount}
                            onMaxAmountChange={setMaxAmount}
                            onReset={handleReset}
                            onDownload={handleDownload}
                            onPrint={() =>
                                notify('Opening the printable payment records.')
                            }
                        />

                        {paymentsUnavailable ? (
                            <PaymentRetryState
                                onRetry={() => router.reload()}
                            />
                        ) : (
                            <PaymentLedgerCard
                                payments={visiblePayments}
                                meta={meta}
                                loanType={loan.lntype}
                                showSkeleton={showSkeleton}
                                amountFilterActive={amountFilterActive}
                                onPageChange={handlePageChange}
                                onSelectPayment={setSelectedPayment}
                            />
                        )}
                    </TabsContent>

                    <TabsContent value="schedule" className="mt-4">
                        <PaymentSchedulePanel
                            loan={loan}
                            schedule={schedule?.items ?? []}
                        />
                    </TabsContent>

                    <TabsContent value="documents" className="mt-4">
                        <DocumentsTab
                            loan={loan}
                            documents={
                                documents ?? {
                                    soaMonths: [],
                                    certificateEligible: false,
                                }
                            }
                            schedule={schedule?.items ?? []}
                            memberName={member.member_name ?? '--'}
                            memberSignatureUrl={member.signatureUrl ?? null}
                            loanManager={loanManager}
                            accountNo={member.acctno}
                        />
                    </TabsContent>
                </Tabs>

                <TransactionSummaryDialog
                    payment={selectedPayment}
                    loan={loan}
                    memberName={member.member_name}
                    open={selectedPayment !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setSelectedPayment(null);
                        }
                    }}
                />
            </PageShell>
        </AppLayout>
    );
}

function rangeLabel(range: MemberLoanPaymentsFilters['range']): string {
    return (
        presetRanges
            .find((preset) => preset.value === range)
            ?.label.toLowerCase() ?? range
    );
}
