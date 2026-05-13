import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    LoanRequestPageHero,
    LoanRequestSearchBox,
    LoanRequestStatusFilters,
    LoanRequestSummaryCards,
    type LoanRequestStatusFilterOption,
} from '@/components/loan-request/loan-request-page-sections';
import { LoanRequestRecordsCard } from '@/components/loan-request/loan-request-records-card';
import { PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard as clientDashboard } from '@/routes/client';
import {
    create as loanRequestCreate,
    index as loanRequestsIndex,
} from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestListItem,
    LoanRequestListResponse,
    LoanRequestStatusValue,
} from '@/types/loan-requests';

type Props = {
    loanRequests: LoanRequestListResponse | null;
    loanRequestsError?: string | null;
};

type StatusFilter =
    | 'all'
    | 'draft'
    | 'under_review'
    | 'approved'
    | 'declined'
    | 'cancelled';

const statusFilters: Array<LoanRequestStatusFilterOption<StatusFilter>> = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'under_review', label: 'Under review' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined' },
    { value: 'cancelled', label: 'Cancelled' },
];

const normalizeStatus = (
    status: LoanRequestStatusValue | null,
): LoanRequestStatusValue | null => {
    if (status === 'submitted') {
        return 'under_review';
    }

    return status;
};

const resolveStatusLabel = (status: LoanRequestStatusValue | null): string => {
    const normalizedStatus = normalizeStatus(status);

    return (
        statusFilters.find((filter) => filter.value === normalizedStatus)
            ?.label ?? 'Unknown'
    );
};

const matchesStatusFilter = (
    request: LoanRequestListItem,
    statusFilter: StatusFilter,
): boolean => {
    if (statusFilter === 'all') {
        return true;
    }

    const normalizedStatus = normalizeStatus(request.status);

    return normalizedStatus === statusFilter;
};

export default function LoanRequestsPage({
    loanRequests,
    loanRequestsError = null,
}: Props) {
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [searchQuery, setSearchQuery] = useState('');
    const items = useMemo(() => loanRequests?.items ?? [], [loanRequests]);
    const isRequestsLoading =
        loanRequests === null && loanRequestsError === null;
    const normalizedSearch = searchQuery.trim().toLowerCase();

    const summaryCounts = useMemo(
        () => ({
            total: items.length,
            draft: items.filter((item) => normalizeStatus(item.status) === 'draft')
                .length,
            underReview: items.filter(
                (item) => normalizeStatus(item.status) === 'under_review',
            ).length,
            approved: items.filter(
                (item) => normalizeStatus(item.status) === 'approved',
            ).length,
            declinedOrCancelled: items.filter((item) =>
                ['declined', 'cancelled'].includes(
                    normalizeStatus(item.status) ?? '',
                ),
            ).length,
        }),
        [items],
    );

    const filteredItems = useMemo(() => {
        return items.filter((request) => {
            if (!matchesStatusFilter(request, statusFilter)) {
                return false;
            }

            if (normalizedSearch === '') {
                return true;
            }

            const searchableValues = [
                request.reference ?? '',
                request.loan_type_label_snapshot ?? '',
                request.typecode ?? '',
                resolveStatusLabel(request.status),
            ]
                .join(' ')
                .toLowerCase();

            return searchableValues.includes(normalizedSearch);
        });
    }, [items, normalizedSearch, statusFilter]);

    const hasFilterState =
        statusFilter !== 'all' || normalizedSearch.length > 0;
    const hasNoFilterResults =
        !isRequestsLoading &&
        !loanRequestsError &&
        hasFilterState &&
        items.length > 0 &&
        filteredItems.length === 0;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: clientDashboard().url },
        { title: 'Loan Requests', href: loanRequestsIndex().url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan Requests" />
            <PageShell>
                <LoanRequestPageHero
                    kicker="Loan applications"
                    title="Loan Requests"
                    description="Track your draft, submitted, approved, declined, and cancelled loan applications."
                    cta={
                        <Button asChild>
                            <Link href={loanRequestCreate().url}>
                                Request loan
                            </Link>
                        </Button>
                    }
                />

                <LoanRequestSummaryCards
                    items={[
                        {
                            label: 'Total',
                            value: summaryCounts.total,
                        },
                        {
                            label: 'Draft',
                            value: summaryCounts.draft,
                            emphasisClassName:
                                'text-amber-600 dark:text-amber-400',
                        },
                        {
                            label: 'Under review',
                            value: summaryCounts.underReview,
                            emphasisClassName:
                                'text-sky-600 dark:text-sky-400',
                        },
                        {
                            label: 'Approved',
                            value: summaryCounts.approved,
                            emphasisClassName:
                                'text-emerald-600 dark:text-emerald-400',
                        },
                        {
                            label: 'Cancelled/Declined',
                            value: summaryCounts.declinedOrCancelled,
                            emphasisClassName:
                                'text-rose-600 dark:text-rose-400',
                        },
                    ]}
                />

                <section className="rounded-2xl border border-border/40 bg-card/60 p-4 shadow-sm sm:p-5">
                    <div className="flex flex-col gap-3">
                        <LoanRequestStatusFilters
                            options={statusFilters}
                            activeValue={statusFilter}
                            onChange={setStatusFilter}
                        />
                        <LoanRequestSearchBox
                            value={searchQuery}
                            onChange={setSearchQuery}
                            placeholder="Search by reference, loan type, or status"
                            resultsText={`Showing ${filteredItems.length} of ${items.length} requests.`}
                        />
                    </div>
                </section>

                <section id="loan-requests" className="scroll-mt-24">
                    {hasNoFilterResults ? (
                        <div className="rounded-xl border border-dashed border-border/50 bg-muted/20 px-6 py-8 text-center">
                            <p className="text-sm font-medium">
                                No matching loan requests found.
                            </p>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Try changing the status filter or search term.
                            </p>
                            <div className="mt-4 flex flex-wrap items-center justify-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setStatusFilter('all');
                                        setSearchQuery('');
                                    }}
                                >
                                    Clear filters
                                </Button>
                                <Button asChild>
                                    <Link href={loanRequestCreate().url}>
                                        Request loan
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <LoanRequestRecordsCard
                            items={filteredItems}
                            isUpdating={isRequestsLoading}
                            error={loanRequestsError}
                        />
                    )}
                </section>
            </PageShell>
        </AppLayout>
    );
}
