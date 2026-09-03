/**
 * Mirror of App\Services\LoanRequests\InstitutionalEmployerCategoryResolver.
 * Keep in sync with that class -- it's the source of truth; this copy only
 * exists so the loan request wizard and profile Bank tab can restrict the
 * "Salary Deduction" payment option client-side before submit, using data
 * already collected earlier in the same form. The backend re-validates
 * independently via the PHP resolver, so drift here only affects UX, not
 * enforcement.
 */
export type InstitutionalEmployerCategory =
    | 'blgu'
    | 'lgu'
    | 'mrdinc'
    | 'healthcare';

/**
 * Mirrors LoanInstitutionalEmployerCategory::isInstitutionalPayrollCategory() --
 * the explicit institutional_employer_category values that route to Authority
 * to Deduct / Salary Deduction, as opposed to 'deped'/'ched' (Waiver document)
 * or no category at all.
 */
export const INSTITUTIONAL_PAYROLL_CATEGORIES = new Set([
    'blgu',
    'lgu',
    'mrdinc',
    'healthcare',
]);

export function isBarangayEmployer(
    employerBusinessName: string | null | undefined,
    employmentType: string | null | undefined,
    natureOfBusiness: string | null | undefined,
): boolean {
    if (!employerBusinessName) {
        return false;
    }

    const needle = employerBusinessName.toLowerCase();

    if (needle.includes('barangay')) {
        return true;
    }

    const isGovernmentSector =
        employmentType === 'Government' && natureOfBusiness === 'Government';

    if (!isGovernmentSector) {
        return false;
    }

    return needle.includes('brgy') || needle.includes('bgy');
}

export function resolveInstitutionalEmployerCategory(
    employerBusinessName: string | null | undefined,
    employmentType: string | null | undefined,
    natureOfBusiness: string | null | undefined,
): InstitutionalEmployerCategory | null {
    if (
        isBarangayEmployer(
            employerBusinessName,
            employmentType,
            natureOfBusiness,
        )
    ) {
        return 'blgu';
    }

    const needle = employerBusinessName
        ? employerBusinessName.toLowerCase()
        : '';

    if (needle !== '' && needle.includes('mrdinc')) {
        return 'mrdinc';
    }

    if (
        natureOfBusiness === 'Healthcare' ||
        (needle !== '' &&
            (needle.includes('ldh') ||
                needle.includes('hospital') ||
                needle.includes('medical') ||
                needle.includes('clinic')))
    ) {
        return 'healthcare';
    }

    const isGovernmentSector =
        employmentType === 'Government' && natureOfBusiness === 'Government';

    if (
        isGovernmentSector ||
        (needle !== '' &&
            (needle.includes('lgu') ||
                needle.includes('municipal government') ||
                needle.includes('city government') ||
                needle.includes('provincial government')))
    ) {
        return 'lgu';
    }

    return null;
}
