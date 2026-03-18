import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { MemberDetailPageHeader } from '@/components/member-detail-page-header';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

type MemberLoanDetailHeaderProps = {
    title: string;
    subtitle: string;
    meta: ReactNode;
    currentView: 'schedule' | 'payments';
    scheduleHref: string | null;
    paymentsHref: string | null;
    canNavigate: boolean;
    backToLoansHref: string | null;
    backToProfileHref: string | null;
};

export function MemberLoanDetailHeader({
    title,
    subtitle,
    meta,
    currentView,
    scheduleHref,
    paymentsHref,
    canNavigate,
    backToLoansHref,
    backToProfileHref,
}: MemberLoanDetailHeaderProps) {
    return (
        <MemberDetailPageHeader
            title={title}
            subtitle={subtitle}
            meta={meta}
            actions={
                <>
                    <ToggleGroup
                        type="single"
                        value={currentView}
                        variant="outline"
                        size="sm"
                        className="rounded-md bg-muted/40 p-1"
                        aria-label="Loan detail views"
                    >
                        {canNavigate && scheduleHref ? (
                            <ToggleGroupItem
                                value="schedule"
                                asChild
                                className="data-[state=on]:font-semibold"
                            >
                                <Link
                                    href={scheduleHref}
                                    aria-current={
                                        currentView === 'schedule'
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    Schedule
                                    {currentView === 'schedule' ? (
                                        <span className="sr-only">
                                            {' '}
                                            (current)
                                        </span>
                                    ) : null}
                                </Link>
                            </ToggleGroupItem>
                        ) : (
                            <ToggleGroupItem
                                value="schedule"
                                disabled
                                className="data-[state=on]:font-semibold"
                            >
                                Schedule
                            </ToggleGroupItem>
                        )}
                        {canNavigate && paymentsHref ? (
                            <ToggleGroupItem
                                value="payments"
                                asChild
                                className="data-[state=on]:font-semibold"
                            >
                                <Link
                                    href={paymentsHref}
                                    aria-current={
                                        currentView === 'payments'
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    Payments
                                    {currentView === 'payments' ? (
                                        <span className="sr-only">
                                            {' '}
                                            (current)
                                        </span>
                                    ) : null}
                                </Link>
                            </ToggleGroupItem>
                        ) : (
                            <ToggleGroupItem
                                value="payments"
                                disabled
                                className="data-[state=on]:font-semibold"
                            >
                                Payments
                            </ToggleGroupItem>
                        )}
                    </ToggleGroup>
                    {backToLoansHref ? (
                        <Button asChild variant="outline" size="sm">
                            <Link href={backToLoansHref}>Back to loans</Link>
                        </Button>
                    ) : null}
                    {backToProfileHref ? (
                        <Button asChild variant="ghost" size="sm">
                            <Link href={backToProfileHref}>
                                Back to profile
                            </Link>
                        </Button>
                    ) : null}
                </>
            }
        />
    );
}
