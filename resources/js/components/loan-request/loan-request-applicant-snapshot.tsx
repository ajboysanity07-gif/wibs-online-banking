import {
    displayCurrency,
    displayText,
    displayValue,
} from '@/components/loan-request/loan-request-detail-page';
import { LoanRequestFactGrid } from '@/components/loan-request/loan-request-fact-grid';
import { Button } from '@/components/ui/button';
import { calculateAge, formatPayday } from '@/lib/formatters';
import type { LoanRequestPersonData } from '@/types/loan-requests';

type Props = {
    applicant: LoanRequestPersonData | null;
    /** Selects the Applicant section. */
    onFullProfile: () => void;
};

export function LoanRequestApplicantSnapshot({
    applicant,
    onFullProfile,
}: Props) {
    return (
        <section
            className="rounded-xl border border-border bg-card px-5 py-[18px] shadow-card"
            aria-label="Applicant snapshot"
        >
            <div className="mb-3.5 flex flex-wrap items-center justify-between gap-2.5">
                <div className="min-w-0">
                    <h2 className="text-[17px] font-semibold">
                        Applicant snapshot
                    </h2>
                    <p className="text-[13px] text-muted-foreground">
                        Enough to process; full profile under Applicant.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="min-h-11 lg:min-h-9"
                    onClick={onFullProfile}
                >
                    Open profile
                </Button>
            </div>
            <LoanRequestFactGrid
                facts={[
                    {
                        label: 'Employer',
                        value: displayText(applicant?.employer_business_name),
                    },
                    {
                        label: 'Position',
                        value: displayText(applicant?.current_position),
                    },
                    {
                        label: 'Gross monthly income',
                        value: displayCurrency(applicant?.gross_monthly_income),
                    },
                    {
                        label: 'Payday',
                        value: applicant?.payday
                            ? formatPayday(applicant.payday)
                            : '--',
                    },
                    {
                        label: 'Age',
                        value: displayValue(calculateAge(applicant?.birthdate)),
                    },
                    {
                        label: 'Cell no.',
                        value: displayValue(applicant?.cell_no),
                    },
                ]}
            />
        </section>
    );
}
