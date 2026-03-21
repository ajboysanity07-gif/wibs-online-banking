import { Head, Link } from '@inertiajs/react';
import { Calendar, Download, Printer } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { loans as clientLoans } from '@/routes/client';
import {
    pdf as loanRequestPdf,
    show as loanRequestShow,
} from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestDetail,
    LoanRequestPersonData,
} from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
};

const statusLabel = (status?: string | null): string => {
    if (status === 'submitted') {
        return 'Submitted';
    }

    if (status === 'under_review') {
        return 'Under review';
    }

    if (status === 'approved') {
        return 'Approved';
    }

    if (status === 'declined') {
        return 'Declined';
    }

    if (status === 'cancelled') {
        return 'Cancelled';
    }

    return status ?? 'Unknown';
};

const statusVariant = (status?: string | null) => {
    if (status === 'approved') {
        return 'default';
    }

    if (status === 'declined') {
        return 'destructive';
    }

    if (status === 'under_review') {
        return 'secondary';
    }

    return 'outline';
};

const personName = (person?: LoanRequestPersonData | null): string => {
    if (!person) {
        return '--';
    }

    return [person.first_name, person.middle_name, person.last_name]
        .filter((value) => Boolean(value && value.trim()))
        .join(' ');
};

export default function LoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Loans', href: clientLoans().url },
        {
            title: 'Loan request',
            href: loanRequestShow(loanRequest.id).url,
        },
    ];

    const submittedAt = formatDate(loanRequest.submitted_at);
    const amount = formatCurrency(
        loanRequest.requested_amount !== null &&
            loanRequest.requested_amount !== undefined
            ? Number(loanRequest.requested_amount)
            : null,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">
                            Loan request submitted
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Calendar className="h-4 w-4" />
                            <span>Submitted {submittedAt}</span>
                            <Badge variant={statusVariant(loanRequest.status)}>
                                {statusLabel(loanRequest.status)}
                            </Badge>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={loanRequestPdf(loanRequest.id).url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Printer />
                                Print application
                            </a>
                        </Button>
                        <Button asChild size="sm">
                            <a
                                href={
                                    loanRequestPdf(loanRequest.id, {
                                        query: { download: 1 },
                                    }).url
                                }
                            >
                                <Download />
                                Download PDF
                            </a>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={clientLoans().url}>
                                Back to loans
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Loan details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Loan type
                            </p>
                            <p className="text-sm font-medium">
                                {loanRequest.loan_type_label_snapshot ?? '--'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Requested amount
                            </p>
                            <p className="text-sm font-medium">{amount}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Requested term
                            </p>
                            <p className="text-sm font-medium">
                                {loanRequest.requested_term
                                    ? `${loanRequest.requested_term} months`
                                    : '--'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Availment status
                            </p>
                            <p className="text-sm font-medium">
                                {loanRequest.availment_status ?? '--'}
                            </p>
                        </div>
                        <div className="md:col-span-2">
                            <p className="text-xs text-muted-foreground">
                                Loan purpose
                            </p>
                            <p className="text-sm font-medium">
                                {loanRequest.loan_purpose ?? '--'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Applicant
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Name
                                </p>
                                <p className="font-medium">
                                    {personName(applicant)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Cell no.
                                </p>
                                <p className="font-medium">
                                    {applicant?.cell_no ?? '--'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Co-maker 1
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Name
                                </p>
                                <p className="font-medium">
                                    {personName(coMakerOne)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Cell no.
                                </p>
                                <p className="font-medium">
                                    {coMakerOne?.cell_no ?? '--'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Co-maker 2
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Name
                                </p>
                                <p className="font-medium">
                                    {personName(coMakerTwo)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Cell no.
                                </p>
                                <p className="font-medium">
                                    {coMakerTwo?.cell_no ?? '--'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
