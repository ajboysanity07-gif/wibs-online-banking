import { isPensionerType } from '@/lib/employment-type';
import type {
    LoanRequestFormData,
    LoanRequestPersonFormData,
} from '@/types/loan-requests';

/**
 * Per-step "can I click Next" gate for the client loan-request wizard.
 * Deliberately mirrors only the fields LoanRequestStoreRequest::rules()
 * actually marks required -- see personRules()/rules() there. Steps whose
 * required-ness already has a dedicated completeness flag (banking,
 * declarations) or a "confirm pre-filled data" checkbox gate
 * (applicant personal/work, dependents, health when prefilled from profile)
 * are NOT re-checked here -- the existing checkbox/`isXComplete` gates in
 * loan-request.tsx already cover them, and duplicating the logic here would
 * only risk drifting out of sync.
 *
 * Left deliberately ungated (never returns missing fields for these):
 * dependents' cycle-status fields (conditional on name/civil-status in a way
 * that's easy to mismatch), the 5 health_glapi questionnaire steps (all
 * `sometimes`/`nullable` server-side, nothing to require), and loan-details'
 * other_loan_type_name / kind_of_loan (their required-ness depends on a
 * wlntype label lookup not reliably available client-side).
 */

type PersonFieldCheck = {
    field: keyof LoanRequestPersonFormData;
    label: string;
};

const blank = (value: string | undefined | null): boolean =>
    value === undefined || value === null || value.trim() === '';

function checkPersonFields(
    values: LoanRequestPersonFormData,
    checks: PersonFieldCheck[],
): string[] {
    return checks
        .filter(({ field }) => blank(values[field] as string))
        .map(({ label }) => label);
}

const BASIC_FIELDS: PersonFieldCheck[] = [
    { field: 'first_name', label: 'First name' },
    { field: 'last_name', label: 'Last name' },
    { field: 'birthdate', label: 'Birthdate' },
    { field: 'birthplace_province', label: 'Birthplace province' },
    { field: 'birthplace_city', label: 'Birthplace city/municipality' },
];

const CONTACT_FIELDS_APPLICANT: PersonFieldCheck[] = [
    { field: 'address3', label: 'Province' },
    { field: 'address2', label: 'City/Municipality' },
    { field: 'address1', label: 'Address (street)' },
    { field: 'length_of_stay', label: 'Length of stay' },
    { field: 'cell_no', label: 'Cell no.' },
    { field: 'housing_status', label: 'Housing status' },
];

// Co-makers don't collect housing_status (includeCivilHousing is false for
// them), and educational_attainment renders in their "contact" section since
// they have no separate family section (!hasFamilySection).
const CONTACT_FIELDS_COMAKER: PersonFieldCheck[] = [
    { field: 'address3', label: 'Province' },
    { field: 'address2', label: 'City/Municipality' },
    { field: 'address1', label: 'Address (street)' },
    { field: 'length_of_stay', label: 'Length of stay' },
    { field: 'cell_no', label: 'Cell no.' },
    { field: 'educational_attainment', label: 'Educational attainment' },
];

const FAMILY_FIELDS: PersonFieldCheck[] = [
    { field: 'civil_status', label: 'Civil status' },
    { field: 'educational_attainment', label: 'Educational attainment' },
    { field: 'number_of_children', label: 'No. of children' },
];

function checkWorkFields(
    values: LoanRequestPersonFormData,
    isApplicant: boolean,
): string[] {
    if (isPensionerType(values.employment_type)) {
        return blank(values.employment_type) ? ['Employment'] : [];
    }

    const checks: PersonFieldCheck[] = [
        { field: 'employment_type', label: 'Employment' },
        { field: 'employer_business_name', label: 'Employer/Business name' },
        { field: 'current_position', label: 'Current position' },
        { field: 'nature_of_business', label: 'Nature of business' },
        {
            field: 'years_in_work_business',
            label: 'Total years in work/business',
        },
        { field: 'gross_monthly_income', label: 'Gross monthly income' },
        { field: 'payday', label: 'Payday' },
    ];

    if (isApplicant) {
        checks.push({
            field: 'employer_business_address1',
            label: 'Employer/Business address (street)',
        });
    }

    return checkPersonFields(values, checks);
}

type StepValidationContext = {
    applicantPrefilledFromProfile: boolean;
    applicantWorkIncomePrefilledFromProfile: boolean;
    healthPrefilledFromProfile: boolean;
};

/**
 * Returns the human-readable labels of fields still missing for the given
 * step, or [] if the step is either fully valid or not gated at all.
 */
export function getStepMissingFields(
    stepId: string,
    data: LoanRequestFormData,
    context: StepValidationContext,
): string[] {
    switch (stepId) {
        case 'loan-details': {
            const missing: string[] = [];
            if (blank(data.typecode)) missing.push('Loan type');
            if (
                blank(data.requested_amount) ||
                Number(data.requested_amount) <= 0
            ) {
                missing.push('Requested amount');
            }
            if (blank(data.requested_term) || Number(data.requested_term) < 1) {
                missing.push('Requested term');
            }
            if (blank(data.loan_purpose)) missing.push('Loan purpose');
            if (blank(data.availment_status)) missing.push('Availment status');
            return missing;
        }

        case 'personal-basic':
            if (context.applicantPrefilledFromProfile) return [];
            return checkPersonFields(data.applicant, BASIC_FIELDS);

        case 'personal-contact':
            if (context.applicantPrefilledFromProfile) return [];
            return checkPersonFields(data.applicant, CONTACT_FIELDS_APPLICANT);

        case 'personal-family':
            if (context.applicantPrefilledFromProfile) return [];
            return checkPersonFields(data.applicant, FAMILY_FIELDS);

        case 'work-employment':
            if (context.applicantWorkIncomePrefilledFromProfile) return [];
            return checkWorkFields(data.applicant, true);

        case 'co-maker-1-basic':
            return checkPersonFields(data.co_maker_1, BASIC_FIELDS);
        case 'co-maker-1-contact':
            return checkPersonFields(data.co_maker_1, CONTACT_FIELDS_COMAKER);
        case 'co-maker-1-employment':
            return checkWorkFields(data.co_maker_1, false);

        case 'co-maker-2-basic':
            return checkPersonFields(data.co_maker_2, BASIC_FIELDS);
        case 'co-maker-2-contact':
            return checkPersonFields(data.co_maker_2, CONTACT_FIELDS_COMAKER);
        case 'co-maker-2-employment':
            return checkWorkFields(data.co_maker_2, false);

        case 'health': {
            if (context.healthPrefilledFromProfile) return [];
            const missing: string[] = [];
            if (blank(String(data.health.health_smoking_status ?? ''))) {
                missing.push('Smoking status');
            }
            if (
                data.health.health_hypertension === null ||
                data.health.health_hypertension === undefined ||
                data.health.health_hypertension === ''
            ) {
                missing.push('Hypertension');
            }
            return missing;
        }

        // banking/declarations already have dedicated isBankingComplete /
        // isDeclarationsComplete checks wired into disablePrimary in
        // loan-request.tsx -- not duplicated here.
        default:
            return [];
    }
}
