import { Head, Link, router } from '@inertiajs/react';
import { Clock, PiggyBank } from 'lucide-react';
import { useMemo, useState } from 'react';
import { MemberAccountAlert } from '@/components/member-account-alert';
import { MemberDetailPageHeader } from '@/components/member-detail-page-header';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import { MemberSavingsLedgerCard } from '@/components/member-savings-ledger-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { dashboard as clientDashboard, savings as clientSavings } from '@/routes/client';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberAccountsSummary,
    MemberSavingsLedgerResponse,
    PaginationMeta,
} from '@/types/admin';

type MemberSummary = {
    name: string;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    summary: MemberAccountsSummary | null;
    summaryError?: string | null;
    savings: MemberSavingsLedgerResponse | null;
    savingsError?: string | null;
};

const fallbackMeta: PaginationMeta = {
    page: 1,
    perPage: 10,
    total: 0,
    lastPage: 1,
};

export default function MemberSavings({
    member,
    summary,
    savings,
    savingsError = null,
}: Props) {
    const [loading, setLoading] = useState(false);
    const items = useMemo(() => savings?.items ?? [], [savings]);
    const meta = savings?.meta ?? fallbackMeta;
    const summaryValue = summary ?? null;
    const isLoading = loading || (savings === null && !savingsError);
    const savingsEmptyMessage = isLoading
        ? 'Loading savings...'
        : 'No savings transactions found.';

    const savingsNumbers = useMemo(() => {
        const uniqueNumbers = new Set<string>();

        items.forEach((item) => {
            if (item.svnumber === null || item.svnumber === '') {
                return;
            }

            uniqueNumbers.add(String(item.svnumber));
        });

        return Array.from(uniqueNumbers);
    }, [items]);

    const reloadPage = (nextPage: number) => {
        setLoading(true);
        router.get(
            clientSavings().url,
            { page: nextPage },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setLoading(false);
                },
            },
        );
    };

    const handlePageChange = (nextPage: number) => {
        if (nextPage === meta.page) {
            return;
        }

        reloadPage(nextPage);
    };

    const handleRetry = () => {
        reloadPage(meta.page);
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Member profile', href: clientDashboard().url },
        { title: 'Savings', href: clientSavings().url },
    ];
    const currentSavings = formatCurrency(summaryValue?.currentPersonalSavings);
    const lastSavingsTransaction = formatDate(
        summaryValue?.lastSavingsTransactionDate,
    );
    const savingsNumberMeta =
        savingsNumbers.length === 0 ? (
            <span>--</span>
        ) : (
            <span className="inline-flex flex-wrap items-center gap-2">
                {savingsNumbers.map((number) => (
                    <Badge key={number} variant="outline">
                        {number}
                    </Badge>
                ))}
            </span>
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Savings" />
            <div className="flex flex-col gap-6 p-4">
                <MemberDetailPageHeader
                    title="Savings"
                    subtitle="Your savings ledger activity."
                    meta={
                        <span className="inline-flex flex-wrap items-center gap-2">
                            <span>Account No: {member.acctno ?? '--'}</span>
                            <span aria-hidden="true">|</span>
                            <span className="inline-flex flex-wrap items-center gap-2">
                                <span>Savings No:</span>
                                {savingsNumberMeta}
                            </span>
                        </span>
                    }
                    actions={
                        <Button asChild variant="ghost" size="sm">
                            <Link href={clientDashboard().url}>
                                Back to profile
                            </Link>
                        </Button>
                    }
                />

                {!member.acctno ? (
                    <MemberAccountAlert
                        title="Account number missing"
                        description="Add an account number to view savings details."
                    />
                ) : null}

                <div className="grid gap-4 md:grid-cols-2">
                    <MemberDetailPrimaryCard
                        title="Personal Savings Balance"
                        value={currentSavings}
                        helper="Latest personal savings balance."
                        icon={PiggyBank}
                        accent="accent"
                    />
                    <MemberDetailSupportingCard
                        title="Last Savings Transaction"
                        description="Most recent savings activity date."
                        value={lastSavingsTransaction}
                        icon={Clock}
                        accent="accent"
                    />
                </div>

                <MemberSavingsLedgerCard
                    items={items}
                    meta={meta}
                    isUpdating={isLoading}
                    error={savingsError}
                    onRetry={handleRetry}
                    onPageChange={handlePageChange}
                    emptyMessage={savingsEmptyMessage}
                />
            </div>
        </AppLayout>
    );
}
