import { ReportedRequestsPage } from '@/components/loan-request/reported-requests-page';
import { useStaffReportedRequests } from '@/hooks/staff/use-reported-requests';
import {
    index as requestsIndex,
    show as requestsShow,
} from '@/routes/staff/loan-requests';
import { index as reportedRequestsIndex } from '@/routes/staff/reported-requests';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Requests',
        href: requestsIndex().url,
    },
    {
        title: 'Reported Requests',
        href: reportedRequestsIndex().url,
    },
];

const requestHref = (requestId: number) => requestsShow(requestId).url;

export default function StaffReportedRequestsPage() {
    return (
        <ReportedRequestsPage
            breadcrumbs={breadcrumbs}
            useReportedList={useStaffReportedRequests}
            requestHref={requestHref}
            backHref={requestsIndex().url}
        />
    );
}
