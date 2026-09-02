import { usePage } from '@inertiajs/react';
import { LoanRequestQueuePage } from '@/components/loan-request/loan-request-queue-page';
import { buildStaffLoanRequestQueueStatusOptions } from '@/lib/loan-request-queue';
import {
    index as requestsIndex,
    show as requestsShow,
} from '@/routes/staff/loan-requests';
import { index as reportedRequestsIndex } from '@/routes/staff/reported-requests';
import type { Auth, BreadcrumbItem } from '@/types';

type PageProps = {
    auth: Auth;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Loan Workflow',
        href: requestsIndex().url,
    },
    {
        title: 'Reported Requests',
        href: reportedRequestsIndex().url,
    },
];

export default function StaffReportedRequestsPage() {
    const { auth } = usePage<PageProps>().props;
    const statusOptions = buildStaffLoanRequestQueueStatusOptions(
        auth.loanWorkflowRoles,
    );

    return (
        <LoanRequestQueuePage
            workspace="staff"
            breadcrumbs={breadcrumbs}
            headTitle="Reported Requests"
            heroKicker="Loan Workflow"
            heroTitle="Reported Requests"
            heroDescription="Loan requests with open correction reports that need your attention."
            statusOptions={statusOptions}
            initialStatusFilter="reported"
            showRequestHref={(requestId) => requestsShow(requestId).url}
            summaryHelperText="Status cards reflect the current results page for the workflow stages available to your role."
        />
    );
}
