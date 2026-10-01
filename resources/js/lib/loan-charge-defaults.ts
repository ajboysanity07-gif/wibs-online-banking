// Institutional defaults a loan processor starts from. Shared by the staff
// processing panel and the member loan calculator so both feed the same
// rates into the server-side LoanFiguresCalculator.

// Mirrors WIBS desktop's loanpay.SCT: loansec = IIF(typc='01', prn*.02, prn*.05).
// Typecode '01' ("Other Loan") is hardcoded there to 2% Loan Security; every
// other loan type is hardcoded to 5%. There is no rate field on the WIBS
// side -- just this typecode branch -- so it's reproduced here as the
// suggested default (still staff-editable, same as before).
const OTHER_LOAN_TYPECODE = '01';

export const resolveDefaultLoanSecurityRate = (
    typecode: string | null | undefined,
): number => (typecode === OTHER_LOAN_TYPECODE ? 0.02 : 0.05);

// Mirrors LoanFiguresCalculator::insuranceTerm(): insurance covers the loan
// term up to 12 months; a term under two months carries no insurance.
export const INSURANCE_TERM_MAX_MONTHS = 12;

export const resolveInsuranceTerm = (term: number): number =>
    Number.isFinite(term) && term >= 2
        ? Math.min(Math.trunc(term), INSURANCE_TERM_MAX_MONTHS)
        : 0;

// Insurer's senior-age insurance rate bands (from the loan processors'
// reference table). Only these two bands are currently known; applicants
// outside them are locked to a fixed rate of 1 (see withProcessingChargeDefaults).
// These are per-mille rates (pesos per ₱1,000 of principal per month), NOT
// percentages -- insurance_rate feeds insurance_premium = (amount/1000) *
// insurance_term * insurance_rate, so the raw table value (e.g. 2.05) must
// be stored as-is, unlike the other *_rate fields which are true percentages.
export const INSURANCE_RATE_AGE_BANDS: {
    minAge: number;
    maxAge: number;
    rate: number;
}[] = [
    { minAge: 66, maxAge: 70, rate: 2.05 },
    { minAge: 71, maxAge: 75, rate: 3.95 },
];

export const calculateAgeFromBirthdate = (
    birthdate: string | null,
): number | null => {
    if (!birthdate) {
        return null;
    }

    const parsed = new Date(birthdate);

    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    const today = new Date();
    let age = today.getFullYear() - parsed.getFullYear();
    const hasNotHadBirthdayThisYear =
        today.getMonth() < parsed.getMonth() ||
        (today.getMonth() === parsed.getMonth() &&
            today.getDate() < parsed.getDate());

    if (hasNotHadBirthdayThisYear) {
        age -= 1;
    }

    return age;
};

export const resolveAgeBandedInsuranceRate = (
    birthdate: string | null,
): number | null => {
    const age = calculateAgeFromBirthdate(birthdate);

    if (age === null) {
        return null;
    }

    const band = INSURANCE_RATE_AGE_BANDS.find(
        ({ minAge, maxAge }) => age >= minAge && age <= maxAge,
    );

    return band?.rate ?? null;
};
