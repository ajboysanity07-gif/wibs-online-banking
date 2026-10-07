import { ReportedRequestsPage } from '@/components/loan-request/reported-requests-page';
import { useReportedRequests } from '@/hooks/admin/use-reported-requests';
import {
    index as requestsIndex,
    reported as requestsReported,
    show as requestsShow,
} from '@/routes/admin/requests';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Requests',
        href: requestsIndex().url,
    },
    {
        title: 'Reported Requests',
        href: requestsReported().url,
    },
];

const requestHref = (requestId: number) => requestsShow(requestId).url;

export default function AdminReportedRequestsPage() {
    return (
        <ReportedRequestsPage
            breadcrumbs={breadcrumbs}
            useReportedList={useReportedRequests}
            requestHref={requestHref}
            backHref={requestsIndex().url}
        />
    );
}
