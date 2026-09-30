import {
    displayCurrency,
    displayText,
    displayValue,
    personName,
} from '@/components/loan-request/loan-request-detail-page';
import { LoanRequestFactGrid } from '@/components/loan-request/loan-request-fact-grid';
import type { LoanRequestPersonData } from '@/types/loan-requests';

type Props = {
    applicant: LoanRequestPersonData | null;
    /** Phase 3 turns this into a jump to the Applicant tab. */
    onFullProfile: () => void;
};

export function LoanRequestApplicantSnapshot({
    applicant,
    onFullProfile,
}: Props) {
    return (
        <div className="rounded-xl border border-border bg-card px-5 py-[18px] shadow-card">
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="text-[17px] font-semibold">
                    Applicant snapshot
                </h2>
                <button
                    type="button"
                    className="min-h-11 text-[13px] font-bold text-primary underline-offset-4 hover:underline lg:min-h-0"
                    onClick={onFullProfile}
                >
                    Full profile
                </button>
            </div>
            <LoanRequestFactGrid
                facts={[
                    { label: 'Name', value: personName(applicant) },
                    {
                        label: 'Employer',
                        value: displayText(applicant?.employer_business_name),
                    },
                    {
                        label: 'Position',
                        value: displayText(applicant?.current_position),
                    },
                    {
                        label: 'Gross income',
                        value: displayCurrency(applicant?.gross_monthly_income),
                    },
                    { label: 'Payday', value: displayValue(applicant?.payday) },
                    {
                        label: 'Cell no.',
                        value: displayValue(applicant?.cell_no),
                    },
                ]}
            />
        </div>
    );
}
