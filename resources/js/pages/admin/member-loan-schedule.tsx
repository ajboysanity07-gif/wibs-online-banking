import { Head } from '@inertiajs/react';
import { MemberAccountAlert } from '@/components/member-account-alert';
import { MemberLoanDetailHeader } from '@/components/member-loan-detail-header';
import { MemberLoanScheduleSections } from '@/components/member-loan-schedule-sections';
import { PageShell } from '@/components/page-shell';
import { useMemberLoanSchedule } from '@/hooks/admin/use-member-loan-schedule';
import AppLayout from '@/layouts/app-layout';
import {
    loanPayments,
    loanSchedule,
    loans as memberLoans,
    show as showMember,
} from '@/routes/admin/members';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberLoan,
    MemberLoanScheduleResponse,
    MemberLoanSummary,
} from '@/types/admin';

type MemberSummary = {
    user_id: number;
    member_name: string | null;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    loan: MemberLoan;
    summary: MemberLoanSummary;
    schedule: MemberLoanScheduleResponse;
};

export default function MemberLoanSchedule({
    member,
    loan,
    summary,
    schedule,
}: Props) {
    const loanNumber = loan.lnnumber ?? null;

    const { items, loading, error, refresh } = useMemberLoanSchedule(
        member.user_id,
        loanNumber,
        {
            initial: schedule,
            enabled: Boolean(member.acctno && loanNumber),
        },
    );

    const canNavigate = Boolean(member.acctno && loanNumber);
    const scheduleHref = loanNumber
        ? loanSchedule({
              user: member.user_id,
              loanNumber,
          }).url
        : null;
    const paymentsHref = loanNumber
        ? loanPayments({
              user: member.user_id,
              loanNumber,
          }).url
        : null;
    const backToLoansHref = memberLoans(member.user_id).url;
    const backToProfileHref = showMember(member.user_id).url;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Members', href: membersIndex().url },
        { title: 'Member profile', href: showMember(member.user_id).url },
        { title: 'Loans', href: memberLoans(member.user_id).url },
        { title: 'Schedule', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan Schedule" />
            <PageShell size="wide">
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

                <MemberLoanScheduleSections
                    items={items}
                    summary={summary}
                    isUpdating={loading}
                    error={error}
                    onRetry={() => void refresh()}
                />
            </PageShell>
        </AppLayout>
    );
}
