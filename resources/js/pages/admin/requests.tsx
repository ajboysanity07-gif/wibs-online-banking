import { LoanRequestQueuePage } from '@/components/loan-request/loan-request-queue-page';
import { adminLoanRequestQueueStatusOptions } from '@/lib/loan-request-queue';
import { index as requestsIndex, show as requestsShow } from '@/routes/admin/requests';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Requests',
        href: requestsIndex().url,
    },
];

export default function RequestsPage() {
    return (
        <LoanRequestQueuePage
            workspace="admin"
            breadcrumbs={breadcrumbs}
            headTitle="Requests"
            heroKicker="Requests"
            heroTitle="Loan Requests"
            heroDescription="Monitor review-ready submissions and open full request details for printing or PDF export."
            statusOptions={adminLoanRequestQueueStatusOptions}
            showRequestHref={(requestId) => requestsShow(requestId).url}
            summaryHelperText="Status cards reflect the current results page. Open correction reports shows the current system-wide open report count."
            showReportedSummary
        />
    );
}
