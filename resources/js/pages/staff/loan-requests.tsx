import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import { LoanRequestQueuePage } from '@/components/loan-request/loan-request-queue-page';
import { buildStaffLoanRequestQueueStatusOptions } from '@/lib/loan-request-queue';
import { index as requestsIndex, show as requestsShow } from '@/routes/staff/loan-requests';
import type { Auth, BreadcrumbItem } from '@/types';

type PageProps = {
    auth: Auth;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Loan Workflow',
        href: requestsIndex().url,
    },
];

export default function StaffLoanRequestsPage() {
    const { auth } = usePage<PageProps>().props;
    const statusOptions = useMemo(
        () =>
            buildStaffLoanRequestQueueStatusOptions(
                auth.loanWorkflowRoles,
                auth.isAdmin,
            ),
        [auth.isAdmin, auth.loanWorkflowRoles],
    );

    return (
        <LoanRequestQueuePage
            workspace="staff"
            breadcrumbs={breadcrumbs}
            headTitle="Loan Workflow"
            heroKicker="Loan Workflow"
            heroTitle="Staff Review Queue"
            heroDescription="Review the loan requests assigned to your workflow role and continue each request through the RBAC approval stages."
            statusOptions={statusOptions}
            showRequestHref={(requestId) => requestsShow(requestId).url}
            summaryHelperText="Status cards reflect the current results page for the workflow stages available to your role."
        />
    );
}
