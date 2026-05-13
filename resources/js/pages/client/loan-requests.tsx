import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { LoanRequestRecordsCard } from '@/components/loan-request/loan-request-records-card';
import { PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
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

const statusFilters: Array<{
    value: StatusFilter;
    label: string;
}> = [
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
                <section className="rounded-2xl border border-border/40 bg-card/60 p-6 shadow-sm sm:p-7">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold tracking-[0.24em] text-muted-foreground uppercase">
                                Loan applications
                            </p>
                            <h1 className="text-3xl font-semibold tracking-tight">
                                Loan Requests
                            </h1>
                            <p className="max-w-3xl text-sm text-muted-foreground">
                                Track your draft, submitted, approved,
                                declined, and cancelled loan applications.
                            </p>
                        </div>
                        <Button asChild className="self-start sm:self-auto">
                            <Link href={loanRequestCreate().url}>
                                Request loan
                            </Link>
                        </Button>
                    </div>
                </section>

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {[
                        {
                            label: 'Total',
                            value: summaryCounts.total,
                            emphasisClass: 'text-foreground',
                        },
                        {
                            label: 'Draft',
                            value: summaryCounts.draft,
                            emphasisClass: 'text-amber-600 dark:text-amber-400',
                        },
                        {
                            label: 'Under review',
                            value: summaryCounts.underReview,
                            emphasisClass: 'text-sky-600 dark:text-sky-400',
                        },
                        {
                            label: 'Approved',
                            value: summaryCounts.approved,
                            emphasisClass:
                                'text-emerald-600 dark:text-emerald-400',
                        },
                        {
                            label: 'Cancelled/Declined',
                            value: summaryCounts.declinedOrCancelled,
                            emphasisClass: 'text-rose-600 dark:text-rose-400',
                        },
                    ].map((item) => (
                        <div
                            key={item.label}
                            className="rounded-xl border border-border/40 bg-card/40 px-4 py-3"
                        >
                            <p className="text-xs font-medium text-muted-foreground">
                                {item.label}
                            </p>
                            <p className={cn('mt-1 text-2xl font-semibold', item.emphasisClass)}>
                                {item.value}
                            </p>
                        </div>
                    ))}
                </section>

                <section className="rounded-2xl border border-border/40 bg-card/60 p-4 shadow-sm sm:p-5">
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-wrap gap-2">
                            {statusFilters.map((filter) => (
                                <Button
                                    key={filter.value}
                                    type="button"
                                    size="sm"
                                    variant={
                                        statusFilter === filter.value
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() =>
                                        setStatusFilter(filter.value)
                                    }
                                >
                                    {filter.label}
                                </Button>
                            ))}
                        </div>
                        <div className="relative w-full sm:max-w-sm">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={searchQuery}
                                onChange={(event) =>
                                    setSearchQuery(event.target.value)
                                }
                                className="pl-9"
                                placeholder="Search by reference, loan type, or status"
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Showing {filteredItems.length} of {items.length}{' '}
                            requests.
                        </p>
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
