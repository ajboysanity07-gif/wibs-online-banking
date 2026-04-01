import { Head } from '@inertiajs/react';
import { MemberAccountAlert } from '@/features/member-accounts/components/member-account-alert';
import { MemberLoanDetailHeader } from '@/components/member-loan-detail-header';
import { MemberLoanScheduleSections } from '@/components/member-loan-schedule-sections';
import { PageShell } from '@/components/page-shell';
import AppLayout from '@/layouts/app-layout';
import {
    dashboard as clientDashboard,
    loanPayments,
    loanSchedule,
    loans as clientLoans,
} from '@/routes/client';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberLoan,
    MemberLoanScheduleResponse,
    MemberLoanSummary,
} from '@/types/admin';

type MemberSummary = {
    member_name: string | null;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    loan: MemberLoan;
    summary: MemberLoanSummary;
    schedule: MemberLoanScheduleResponse;
};

export default function LoanSchedule({
    member,
    loan,
    summary,
    schedule,
}: Props) {
    const loanNumber = loan.lnnumber ?? null;
    const items = schedule.items ?? [];
    const canNavigate = Boolean(member.acctno && loanNumber);
    const scheduleHref = loanNumber ? loanSchedule(loanNumber).url : null;
    const paymentsHref = loanNumber ? loanPayments(loanNumber).url : null;
    const backToLoansHref = clientLoans().url;
    const backToProfileHref = clientDashboard().url;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Member profile', href: clientDashboard().url },
        { title: 'Loans', href: clientLoans().url },
        { title: 'Schedule', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan Schedule" />
            <PageShell>
                <MemberLoanDetailHeader
                    title="Loan Schedule"
                    subtitle={`${member.member_name ?? 'Member'} - Loan ${loan.lnnumber ?? '--'}`}
                    meta={`Account No: ${member.acctno ?? '--'} | Loan Type: ${loan.lntype ?? '--'}`}
                    currentView="schedule"
                    scheduleHref={scheduleHref}
                    paymentsHref={paymentsHref}
                    canNavigate={canNavigate}
                    backToLoansHref={backToLoansHref}
                    backToProfileHref={backToProfileHref}
                />

                {!member.acctno || !loanNumber ? (
                    <MemberAccountAlert
                        title="Loan not available"
                        description="This member needs a valid loan number and account number before the schedule can be displayed."
                    />
                ) : null}

                <MemberLoanScheduleSections items={items} summary={summary} />
            </PageShell>
        </AppLayout>
    );
}
