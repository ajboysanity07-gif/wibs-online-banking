import { Head } from '@inertiajs/react';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import AppLayout from '@/layouts/app-layout';
import { dashboard as clientDashboard } from '@/routes/client';
import {
    index as loanRequestsIndex,
    pdf as loanRequestPdf,
    print as loanRequestPrint,
    show as loanRequestShow,
} from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type { LoanRequestDetail, LoanRequestPersonData } from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
};

export default function LoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
}: Props) {
    const loanRequestsIndexHref = loanRequestsIndex().url;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: clientDashboard().url },
        { title: 'Loan Requests', href: loanRequestsIndexHref },
        {
            title: 'Loan request',
            href: loanRequestShow(loanRequest.id).url,
        },
    ];

    const pdfHref = loanRequestPdf(loanRequest.id, {
        query: { download: 1 },
    }).url;
    const printHref = loanRequestPrint(loanRequest.id).url;
    const correctedRequestHref =
        loanRequest.corrected_request_id !== null
            ? loanRequestShow(loanRequest.corrected_request_id).url
            : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <LoanRequestDetailPage
                loanRequest={loanRequest}
                applicant={applicant}
                coMakerOne={coMakerOne}
                coMakerTwo={coMakerTwo}
                backHref={loanRequestsIndexHref}
                backLabel="Back to loan requests"
                pdfHref={pdfHref}
                printHref={printHref}
                correctedRequestHref={correctedRequestHref}
            />
        </AppLayout>
    );
}
