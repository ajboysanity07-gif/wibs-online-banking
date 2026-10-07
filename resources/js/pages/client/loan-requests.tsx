import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import {
    LoanRequestFilterChips,
    LoanRequestPageHero,
    LoanRequestSummaryCards,
    type LoanRequestStatusFilterOption,
} from '@/components/loan-request/loan-request-page-sections';
import { LoanRequestRecordsCard } from '@/components/loan-request/loan-request-records-card';
import { PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard as clientDashboard } from '@/routes/client';
import {
    create as loanRequestCreate,
    index as loanRequestsIndex,
} from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestListItem,
    LoanRequestStatusValue,
} from '@/types/loan-requests';
import type { LoanRequestListResponse } from '@/types/loan-requests';

type Props = {
    loanRequests: LoanRequestListResponse | null;
    loanRequestsError?: string | null;
    accountNo?: string | null;
};

type StatusFilter =
    | 'all'
    | 'draft'
    | 'pending'
    | 'processing'
    | 'awaiting'
    | 'approved'
    | 'declined'
    | 'closed';

const statusFilters: Array<LoanRequestStatusFilterOption<StatusFilter>> = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'In processing' },
    { value: 'awaiting', label: 'Awaiting correction' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined' },
    { value: 'closed', label: 'Closed' },
];

// Maps the workflow statuses onto the member-facing filter groups.
const statusGroups: Record<
    Exclude<StatusFilter, 'all'>,
    LoanRequestStatusValue[]
> = {
    draft: ['draft'],
    pending: ['pending_review', 'submitted', 'pending_co_maker_signatures'],
    processing: ['under_review', 'recommended_for_approval'],
    awaiting: [
        'needs_revision',
        'awaiting_member_information',
        'awaiting_member_acceptance',
    ],
    approved: ['approved'],
    declined: ['declined', 'rejected', 'member_declined_terms'],
    closed: [
        'converted_to_loan',
        'declined',
        'rejected',
        'member_declined_terms',
        'cancelled',
    ],
};

const statusLabels: Partial<Record<LoanRequestStatusValue, string>> = {
    draft: 'Draft',
    pending_review: 'Pending Processing',
    submitted: 'Pending Processing',
    pending_co_maker_signatures: 'Pending Processing',
    under_review: 'In Processing',
    needs_revision: 'Awaiting Member Correction',
    awaiting_member_information: 'Awaiting Member Information',
    recommended_for_approval: 'For Loan Manager Review',
    awaiting_member_acceptance: 'Awaiting Member Acceptance',
    approved: 'Approved',
    declined: 'Declined by Loan Manager',
    member_declined_terms: 'Member Declined Terms',
    rejected: 'Rejected During Processing',
    converted_to_loan: 'Converted to Loan',
    cancelled: 'Cancelled',
};

const inGroup = (
    request: LoanRequestListItem,
    group: Exclude<StatusFilter, 'all'>,
): boolean =>
    request.status !== null && statusGroups[group].includes(request.status);

export default function LoanRequestsPage({
    loanRequests,
    loanRequestsError = null,
    accountNo = null,
}: Props) {
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [searchQuery, setSearchQuery] = useState('');
    const items = loanRequests?.items ?? [];
    const isRequestsLoading =
        loanRequests === null && loanRequestsError === null;
    const normalizedSearch = searchQuery.trim().toLowerCase();

    // Items arrive newest first, so the first match is the latest reference.
    const latestReference = (group: Exclude<StatusFilter, 'all'>) => {
        const matches = items.filter((item) => inGroup(item, group));

        return {
            count: matches.length,
            helper: matches[0]?.reference ?? 'None',
        };
    };

    const draft = latestReference('draft');
    const processing = latestReference('processing');
    const awaiting = latestReference('awaiting');
    const approved = latestReference('approved');
    const closed = latestReference('closed');

    const filteredItems = items.filter((request) => {
        if (statusFilter !== 'all' && !inGroup(request, statusFilter)) {
            return false;
        }

        if (normalizedSearch === '') {
            return true;
        }

        return [
            request.reference ?? '',
            request.loan_type_label_snapshot ?? '',
            request.typecode ?? '',
            request.assigned_officer?.name ?? '',
            request.status ? (statusLabels[request.status] ?? '') : '',
        ]
            .join(' ')
            .toLowerCase()
            .includes(normalizedSearch);
    });

    const hasNoFilterResults =
        !isRequestsLoading &&
        !loanRequestsError &&
        items.length > 0 &&
        filteredItems.length === 0;

    const clearFilters = () => {
        setStatusFilter('all');
        setSearchQuery('');
    };

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
                    title="Loan requests"
                    description="Track every application from draft to release."
                    badges={
                        accountNo ? (
                            <span className="inline-flex items-center rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold">
                                Account No {accountNo}
                            </span>
                        ) : null
                    }
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
                            value: items.length,
                            helper: 'All loan requests',
                        },
                        {
                            label: 'Draft',
                            value: draft.count,
                            helper: draft.helper,
                        },
                        {
                            label: 'In processing',
                            value: processing.count,
                            helper: processing.helper,
                        },
                        {
                            label: 'Awaiting member action',
                            value: awaiting.count,
                            helper: awaiting.helper,
                        },
                        {
                            label: 'Approved',
                            value: approved.count,
                            helper: approved.helper,
                        },
                        {
                            label: 'Closed',
                            value: closed.count,
                            helper: 'Converted, declined, closed',
                        },
                    ]}
                />

                <Card
                    className="gap-4 px-5 py-4"
                    aria-label="Filter loan requests"
                >
                    <LoanRequestFilterChips
                        label="Filter by status"
                        options={statusFilters}
                        value={statusFilter}
                        onChange={setStatusFilter}
                    />
                    <div className="grid gap-1">
                        <Label
                            htmlFor="loan-request-search"
                            className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase"
                        >
                            Search
                        </Label>
                        <Input
                            id="loan-request-search"
                            type="search"
                            value={searchQuery}
                            onChange={(event) =>
                                setSearchQuery(event.target.value)
                            }
                            placeholder="Search by reference, loan type, processor, or status"
                        />
                    </div>
                    <p role="status" className="text-xs text-muted-foreground">
                        Showing {filteredItems.length} of {items.length}{' '}
                        requests
                    </p>
                </Card>

                <section id="loan-requests" className="scroll-mt-24">
                    {hasNoFilterResults ? (
                        <div className="flex flex-col items-center gap-2 rounded-xl border border-border bg-card px-6 py-10 text-center">
                            <span className="grid size-12 place-items-center rounded-full bg-muted text-primary">
                                <Search className="size-5" aria-hidden="true" />
                            </span>
                            <h3 className="text-base font-bold">
                                No matching loan requests
                            </h3>
                            <p className="max-w-[46ch] text-sm text-muted-foreground">
                                Try a different reference, loan type, or status
                                — or clear the filters to see all {items.length}{' '}
                                requests.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={clearFilters}
                            >
                                Clear filters
                            </Button>
                        </div>
                    ) : (
                        <LoanRequestRecordsCard
                            items={filteredItems}
                            totalCount={items.length}
                            isUpdating={isRequestsLoading}
                            error={loanRequestsError}
                        />
                    )}
                </section>
            </PageShell>
        </AppLayout>
    );
}
