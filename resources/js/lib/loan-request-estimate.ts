/** Published rates; the processor confirms the real figures before release. */
export const ESTIMATE_RATES = {
    serviceCharge: 0.02,
    loanSecurity: 0.02,
    documentaryStamp: 0.0075,
    monthlyInterest: 0.015,
} as const;

const centavos = (value: number): number => Math.round(value * 100) / 100;

export type LoanRequestEstimate = {
    serviceCharge: number;
    loanSecurity: number;
    documentaryStamp: number;
    netProceeds: number;
    interest: number;
    totalRepayable: number;
    perPayment: number;
};

/** Flat-rate estimate (insurance excluded, set by the provider). */
export function estimateLoanRequest(
    amount: number,
    termMonths: number,
): LoanRequestEstimate {
    const serviceCharge = centavos(amount * ESTIMATE_RATES.serviceCharge);
    const loanSecurity = centavos(amount * ESTIMATE_RATES.loanSecurity);
    const documentaryStamp = centavos(amount * ESTIMATE_RATES.documentaryStamp);
    const interest = centavos(
        amount * ESTIMATE_RATES.monthlyInterest * termMonths,
    );
    const totalRepayable = centavos(amount + interest);

    return {
        serviceCharge,
        loanSecurity,
        documentaryStamp,
        netProceeds: centavos(
            amount - serviceCharge - loanSecurity - documentaryStamp,
        ),
        interest,
        totalRepayable,
        perPayment: centavos(totalRepayable / termMonths),
    };
}
