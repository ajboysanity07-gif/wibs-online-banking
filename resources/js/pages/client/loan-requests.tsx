import { Head } from '@inertiajs/react';
import { LoanRequestRecordsCard } from '@/components/loan-request/loan-request-records-card';
import { MemberDetailPageHeader } from '@/components/member-detail-page-header';
import { PageShell } from '@/components/page-shell';
import AppLayout from '@/layouts/app-layout';
import { dashboard as clientDashboard } from '@/routes/client';
import { index as loanRequestsIndex } from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type { LoanRequestListResponse } from '@/types/loan-requests';

type Props = {
    loanRequests: LoanRequestListResponse | null;
    loanRequestsError?: string | null;
};

export default function LoanRequestsPage({
    loanRequests,
    loanRequestsError = null,
}: Props) {
    const items = loanRequests?.items ?? [];
    const isRequestsLoading =
        loanRequests === null && loanRequestsError === null;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: clientDashboard().url },
        { title: 'Loan Requests', href: loanRequestsIndex().url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan Requests" />
            <PageShell>
                <MemberDetailPageHeader
                    title="Loan Requests"
                    subtitle="Track your draft, submitted, approved, declined, and cancelled loan applications."
                />

                <section id="loan-requests" className="scroll-mt-24">
                    <LoanRequestRecordsCard
                        items={items}
                        isUpdating={isRequestsLoading}
                        error={loanRequestsError}
                    />
                </section>
            </PageShell>
        </AppLayout>
    );
}
