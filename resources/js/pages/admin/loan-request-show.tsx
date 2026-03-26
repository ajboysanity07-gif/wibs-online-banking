import { Head } from '@inertiajs/react';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import AppLayout from '@/layouts/app-layout';
import {
    index as requestsIndex,
    pdf as requestsPdf,
    print as requestsPrint,
    show as requestsShow,
} from '@/routes/admin/requests';
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
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Requests', href: requestsIndex().url },
        { title: 'Loan request', href: requestsShow(loanRequest.id).url },
    ];
    const pdfHref = requestsPdf(loanRequest.id, {
        query: { download: 1 },
    }).url;
    const printHref = requestsPrint(loanRequest.id).url;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <LoanRequestDetailPage
                loanRequest={loanRequest}
                applicant={applicant}
                coMakerOne={coMakerOne}
                coMakerTwo={coMakerTwo}
                backHref={requestsIndex().url}
                backLabel="Back to requests"
                pdfHref={pdfHref}
                printHref={printHref}
            />
        </AppLayout>
    );
}
