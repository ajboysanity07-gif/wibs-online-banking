import type { ChangeEvent } from 'react';
import {
    DEPENDENT_CATEGORIES,
    slotFieldKey,
} from '@/components/dependents/dependent-category-section';
import { normalizeMobileNumberInput } from '@/lib/phone';
import { edit } from '@/routes/profile';
import type { BreadcrumbItem } from '@/types';

export const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

export type AdminProfileSummary = {
    fullname: string | null;
    profilePicUrl: string | null;
};

export type MemberRecord = {
    bname: string | null;
    fname: string | null;
    lname: string | null;
    mname: string | null;
    birthplace: string | null;
    birthplace_city: string | null;
    birthplace_province: string | null;
    birthday: string | null;
    address: string | null;
    address1: string | null;
    barangay: string | null;
    barangay_raw: string | null;
    address2: string | null;
    address2_raw: string | null;
    address3: string | null;
    address3_raw: string | null;
    zip_code: string | null;
    display_address: string | null;
    civilstat: string | null;
    occupation: string | null;
    spouse_name: string | null;
    housing_status: string | null;
    number_of_children: string | null;
    hasStructuredName: boolean;
};

export type MemberApplicationProfileData = {
    nickname: string | null;
    birthplace: string | null;
    birthplace_city: string | null;
    birthplace_province: string | null;
    birthplace_barangay: string | null;
    length_of_stay: string | null;
    home_address: string | null;
    home_address1: string | null;
    home_address_barangay: string | null;
    home_address2: string | null;
    home_address3: string | null;
    home_address_zip: string | null;
    number_of_children: number | null;
    civil_status: string | null;
    housing_status: string | null;
    spouse_name: string | null;
    educational_attainment: string | null;
    spouse_birthdate: string | null;
    spouse_cell_no: string | null;
    employment_type: string | null;
    employer_business_name: string | null;
    employer_business_address: string | null;
    employer_business_address1: string | null;
    employer_business_address_barangay: string | null;
    employer_business_address2: string | null;
    employer_business_address3: string | null;
    employer_business_address_zip: string | null;
    telephone_no: string | null;
    current_position: string | null;
    nature_of_business: string | null;
    years_in_work_business: string | null;
    gross_monthly_income: string | null;
    payday: string | null;
    payout_bank_name: string | null;
    payout_account_name: string | null;
    payout_account_number: string | null;
    payout_account_type: string | null;
    release_method: string | null;
    payout_atm_number: string | null;
    payout_bank_branch: string | null;
    payout_atm_holder_name: string | null;
    source_of_fund_wealth: string | null;
    id_type: string | null;
    id_type_other: string | null;
    id_number: string | null;
    height_cm: string | null;
    weight_kg: string | null;
    profile_completed_at: string | null;
};

export type ProfileCompletion = {
    isComplete: boolean;
    completedAt: string | null;
    missingFields: string[];
    missingFieldKeys: string[];
};

export type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    adminProfile?: AdminProfileSummary | null;
    memberRecord?: MemberRecord | null;
    memberApplicationProfile?: MemberApplicationProfileData | null;
    dependents?: Record<string, string | null> | null;
    profileCompletion?: ProfileCompletion | null;
    onboarding?: boolean;
};

export const WMASTER_VALUE_CLASS = 'border-ring/40 ring-1 ring-ring/20';
export const MISSING_FIELD_CLASS =
    'border-amber-400 ring-1 ring-amber-300 dark:border-amber-500/70 dark:ring-amber-500/30';

export function OriginalValueHint({ value }: { value: string | null }) {
    if (!value) {
        return null;
    }

    return (
        <p className="text-xs text-muted-foreground">
            On record: <span className="font-medium">{value}</span>
        </p>
    );
}

export const PROFILE_PHOTO_MAX_BYTES = 2 * 1024 * 1024;
export const PROFILE_PHOTO_ALLOWED_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
]);
export const PROFILE_PHOTO_OUTPUT_SIZE = 512;
export const PROFILE_PHOTO_OUTPUT_QUALITY = 0.92;
export const EDUCATIONAL_ATTAINMENT_OPTIONS = [
    'Elementary',
    'High School',
    'Vocational',
    'College',
    'Postgraduate',
];
export const PENSIONER_EMPLOYMENT_TYPE = 'Pensioner / Retired';
export const EMPLOYMENT_TYPE_OPTIONS = [
    'Regular',
    'Contract',
    'Self-Employed',
    PENSIONER_EMPLOYMENT_TYPE,
];
export const CIVIL_STATUS_OPTIONS = [
    'Single',
    'Married',
    'Separated',
    'Widowed',
] as const;
export const HOUSING_STATUS_OPTIONS = [
    { value: 'OWNED', label: 'Owned' },
    { value: 'RENT', label: 'Rent' },
] as const;
export const PAYDAY_OPTIONS = [
    'Daily',
    'Weekly',
    '15th',
    '30th',
    '15th & 30th',
    'Bi-Weekly',
    'Monthly',
] as const;
export const ID_TYPE_OTHER_VALUE = 'Others';
export const ID_TYPE_OPTIONS = [
    'SSS',
    'GSIS',
    'TIN',
    'Phil ID',
    ID_TYPE_OTHER_VALUE,
] as const;
export const RELEASE_METHOD_OPTIONS = [
    'ATM',
    'Bank Transfer',
    'Check',
    'Cash',
] as const;
export const NATURE_OF_BUSINESS_OTHER_VALUE = 'Other';
export const NATURE_OF_BUSINESS_OPTIONS = [
    'Retail',
    'Wholesale',
    'Manufacturing',
    'Transportation',
    'Construction',
    'Food & Beverage',
    'Agriculture',
    'Education',
    'Healthcare',
    'Finance',
    'Government',
    'Technology',
    'Services',
    NATURE_OF_BUSINESS_OTHER_VALUE,
];
export const PROFILE_TAB_ORDER = [
    'account',
    'personal',
    'work',
    'bank',
    'dependents',
] as const;
export type ProfileTab = (typeof PROFILE_TAB_ORDER)[number];
// Name/birthdate only -- cycle status/number are collected by the
// loan-request wizard, not Settings (see DependentCategorySection's
// showCycleFields prop).
export const DEPENDENT_FIELD_KEYS = DEPENDENT_CATEGORIES.flatMap((category) =>
    Array.from({ length: category.cap }, (_, index) => index + 1).flatMap(
        (slot) =>
            (['name', 'birthdate'] as const).map((attribute) =>
                slotFieldKey(category.key, slot, attribute),
            ),
    ),
);
export const PROFILE_TAB_FIELDS: Record<ProfileTab, string[]> = {
    account: ['profile_photo', 'fullname', 'username', 'email', 'phoneno'],
    personal: [
        'nickname',
        'birthplace_city',
        'birthplace_province',
        'birthplace',
        'length_of_stay',
        'home_address1',
        'home_address_barangay',
        'home_address2',
        'home_address3',
        'home_address_zip',
        'number_of_children',
        'civil_status',
        'housing_status',
        'spouse_name',
        'spouse_birthdate',
        'spouse_cell_no',
        'educational_attainment',
    ],
    work: [
        'employment_type',
        'employer_business_name',
        'employer_business_address1',
        'employer_business_address2',
        'employer_business_address3',
        'employer_business_address_zip',
        'telephone_no',
        'current_position',
        'nature_of_business',
        'years_in_work_business',
        'gross_monthly_income',
        'payday',
    ],
    bank: [
        'payout_bank_name',
        'payout_account_name',
        'payout_account_number',
        'payout_account_type',
        'release_method',
        'payout_atm_number',
        'payout_bank_branch',
        'payout_atm_holder_name',
        'source_of_fund_wealth',
        'id_type',
        'id_type_other',
        'id_number',
        'height_cm',
        'weight_kg',
    ],
    dependents: DEPENDENT_FIELD_KEYS,
};

export const tabForField = (field: string): ProfileTab | null => {
    for (const tab of PROFILE_TAB_ORDER) {
        if (PROFILE_TAB_FIELDS[tab].includes(field)) {
            return tab;
        }
    }

    return null;
};

export const findFirstTabWithErrors = (
    errors: Record<string, string>,
): ProfileTab | null => {
    const errorFields = new Set(Object.keys(errors));

    for (const tab of PROFILE_TAB_ORDER) {
        if (PROFILE_TAB_FIELDS[tab].some((field) => errorFields.has(field))) {
            return tab;
        }
    }

    return null;
};

export const findFirstInvalidField = (
    errors: Record<string, string>,
): string | null => {
    const errorFields = new Set(Object.keys(errors));

    for (const tab of PROFILE_TAB_ORDER) {
        for (const field of PROFILE_TAB_FIELDS[tab]) {
            if (errorFields.has(field)) {
                return field;
            }
        }
    }

    return null;
};

export const focusInvalidField = (fieldName: string | null): void => {
    if (!fieldName || typeof window === 'undefined') {
        return;
    }

    window.setTimeout(() => {
        const escaped =
            typeof CSS !== 'undefined' && 'escape' in CSS
                ? CSS.escape(fieldName)
                : fieldName;
        const element =
            document.getElementById(fieldName) ??
            document.querySelector(`[name="${escaped}"]:not([type="hidden"])`);

        if (!(element instanceof HTMLElement)) {
            return;
        }

        if (
            'disabled' in element &&
            typeof element.disabled === 'boolean' &&
            element.disabled
        ) {
            return;
        }

        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus({ preventScroll: true });
    }, 0);
};

export const calculateAge = (birthday: string | null): number | null => {
    if (!birthday) {
        return null;
    }

    const [year, month, day] = birthday.split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    const today = new Date();
    let age = today.getFullYear() - year;
    const hasHadBirthday =
        today.getMonth() + 1 > month ||
        (today.getMonth() + 1 === month && today.getDate() >= day);

    if (!hasHadBirthday) {
        age -= 1;
    }

    return age < 0 ? null : age;
};

export const hasWmasterValue = (
    value: string | number | null | undefined,
): boolean => {
    if (value === null || value === undefined) {
        return false;
    }

    if (typeof value === 'number') {
        return !Number.isNaN(value);
    }

    if (typeof value === 'string') {
        return value.trim() !== '';
    }

    return false;
};

export const normalizeCivilStatusValue = (value?: string | null): string => {
    const trimmed = value?.trim() ?? '';

    if (trimmed === '') {
        return '';
    }

    const upper = trimmed.toUpperCase();

    if (upper === 'SINGLE') {
        return 'Single';
    }

    if (upper === 'MARRIED') {
        return 'Married';
    }

    if (upper === 'SEPARATED') {
        return 'Separated';
    }

    if (upper === 'WIDOWED') {
        return 'Widowed';
    }

    return '';
};

/** Civil statuses with no active spouse -- spouse fields are hidden and optional for these. */
export const SPOUSE_NOT_APPLICABLE_STATUSES = [
    'Single',
    'Widowed',
    'Separated',
];

export const normalizePaydayValue = (value?: string | null): string => {
    const trimmed = value?.trim() ?? '';

    if (trimmed === '') {
        return '';
    }

    if (PAYDAY_OPTIONS.includes(trimmed as (typeof PAYDAY_OPTIONS)[number])) {
        return trimmed;
    }

    const upper = trimmed.toUpperCase();
    const compact = upper.replace(/[^0-9A-Z]/g, '');

    if (upper === 'WEEKLY') {
        return 'Weekly';
    }

    if (upper === 'MONTHLY') {
        return 'Monthly';
    }

    if (compact === 'BIWEEKLY') {
        return 'Bi-Weekly';
    }

    if (compact === '15') {
        return '15th';
    }

    if (compact === '30') {
        return '30th';
    }

    if (upper.includes('15') && upper.includes('30')) {
        return '15th & 30th';
    }

    return '';
};

export const handleMobileNumberInput = (
    event: ChangeEvent<HTMLInputElement>,
): void => {
    event.target.value = normalizeMobileNumberInput(event.target.value);
};
