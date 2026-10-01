import { useEffect, useState } from 'react';
import client from '@/lib/api/client';
import {
    calculateAgeFromBirthdate,
    resolveAgeBandedInsuranceRate,
} from '@/lib/loan-charge-defaults';
import type { LoanEstimate } from '@/types/loan-requests';

const FREQUENCIES = [
    'Daily',
    'Due date',
    'Monthly',
    'Quincenal',
    'Semi-annual',
    'Weekly',
    'Yearly',
];

type Params = {
    amount: string;
    term: string;
    maxTerm: number;
    typecode: string;
    /** requested_payment_frequency, else the applicant's payday. */
    paymentFrequency: string;
    applicantBirthdate: string | null;
};

/**
 * Debounced (250ms) call to the server-side LoanFiguresCalculator -- the same
 * formula staff preview and documents use. Returns null until amount and
 * term are valid.
 */
export function useLoanEstimate({
    amount,
    term,
    maxTerm,
    typecode,
    paymentFrequency,
    applicantBirthdate,
}: Params): { estimate: LoanEstimate | null; isLoading: boolean } {
    const [estimate, setEstimate] = useState<LoanEstimate | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const amountValue = Number(amount);
    const termValue = Number(term);
    const isValid =
        amountValue >= 1 &&
        Number.isInteger(termValue) &&
        termValue >= 1 &&
        termValue <= maxTerm;
    // Same age-banded rate the staff processing panel defaults to.
    const insuranceRate =
        calculateAgeFromBirthdate(applicantBirthdate) !== null
            ? (resolveAgeBandedInsuranceRate(applicantBirthdate) ?? 1)
            : null;
    const frequency = FREQUENCIES.includes(paymentFrequency)
        ? paymentFrequency
        : null;

    useEffect(() => {
        if (!isValid) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setIsLoading(true);

            try {
                const response = await client.post<{ data: LoanEstimate }>(
                    '/client/loans/request/estimate',
                    {
                        amount: amountValue,
                        term: termValue,
                        typecode: typecode || null,
                        payment_frequency: frequency,
                        insurance_rate: insuranceRate,
                    },
                    { signal: controller.signal },
                );
                setEstimate(response.data.data);
            } catch {
                // Aborted or failed: keep the last figures rather than flash
                // an error on every keystroke.
            } finally {
                if (!controller.signal.aborted) {
                    setIsLoading(false);
                }
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [isValid, amountValue, termValue, typecode, frequency, insuranceRate]);

    return { estimate: isValid ? estimate : null, isLoading };
}
