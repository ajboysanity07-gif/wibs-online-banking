import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import LinkMembershipController from '@/actions/App/Http/Controllers/Settings/LinkMembershipController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import {
    DEPENDENT_CATEGORIES,
    DependentCategorySection,
    type DependentValues,
    slotFieldKey,
} from '@/components/dependents/dependent-category-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { BirthdateInput } from '@/components/loan-request/birthdate-input';
import { CurrencyInput } from '@/components/loan-request/numeric-adorned-inputs';
import { ReleaseAccountFields } from '@/components/loan-request/release-account-fields';
import { LocationCombobox } from '@/components/location-combobox';
import ProfileImageCropModal, {
    type ProfileImageCropResult,
} from '@/components/profile/profile-image-crop-modal';
import { SurfaceCard } from '@/components/surface-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useInitials } from '@/hooks/use-initials';
import { useLocationSearch } from '@/hooks/use-location-search';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import api from '@/lib/api';
import { createCroppedImageFile } from '@/lib/image-crop';
import { normalizeMobileNumberInput } from '@/lib/phone';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { barangays, cities, provinces, zip } from '@/routes/api/locations';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

type AdminProfileSummary = {
    fullname: string | null;
    profilePicUrl: string | null;
};

type MemberRecord = {
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

type MemberApplicationProfileData = {
    nickname: string | null;
    birthplace: string | null;
    birthplace_city: string | null;
    birthplace_province: string | null;
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
    release_uses_payout_account: boolean | null;
    release_bank_name: string | null;
    release_account_name: string | null;
    release_account_number: string | null;
    release_account_type: string | null;
    source_of_fund_wealth: string | null;
    id_type: string | null;
    id_type_other: string | null;
    id_number: string | null;
    height_cm: string | null;
    weight_kg: string | null;
    profile_completed_at: string | null;
};

type ProfileCompletion = {
    isComplete: boolean;
    completedAt: string | null;
    missingFields: string[];
    missingFieldKeys: string[];
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    adminProfile?: AdminProfileSummary | null;
    memberRecord?: MemberRecord | null;
    memberApplicationProfile?: MemberApplicationProfileData | null;
    dependents?: Record<string, string | null> | null;
    profileCompletion?: ProfileCompletion | null;
    onboarding?: boolean;
};
const WMASTER_VALUE_CLASS = 'border-ring/40 ring-1 ring-ring/20';
const MISSING_FIELD_CLASS =
    'border-amber-400 ring-1 ring-amber-300 dark:border-amber-500/70 dark:ring-amber-500/30';

function OriginalValueHint({ value }: { value: string | null }) {
    if (!value) {
        return null;
    }

    return (
        <p className="text-xs text-muted-foreground">
            On record: <span className="font-medium">{value}</span>
        </p>
    );
}
const PROFILE_PHOTO_MAX_BYTES = 2 * 1024 * 1024;
const PROFILE_PHOTO_ALLOWED_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
]);
const PROFILE_PHOTO_OUTPUT_SIZE = 512;
const PROFILE_PHOTO_OUTPUT_QUALITY = 0.92;
const EDUCATIONAL_ATTAINMENT_OPTIONS = [
    'Elementary',
    'High School',
    'Vocational',
    'College',
    'Postgraduate',
];
const PENSIONER_EMPLOYMENT_TYPE = 'Pensioner / Retired';
const EMPLOYMENT_TYPE_OPTIONS = [
    'Regular',
    'Contract',
    'Self-Employed',
    PENSIONER_EMPLOYMENT_TYPE,
];
const CIVIL_STATUS_OPTIONS = [
    'Single',
    'Married',
    'Separated',
    'Widowed',
] as const;
const HOUSING_STATUS_OPTIONS = [
    { value: 'OWNED', label: 'Owned' },
    { value: 'RENT', label: 'Rent' },
] as const;
const PAYDAY_OPTIONS = [
    'Daily',
    'Weekly',
    '15th',
    '30th',
    '15th & 30th',
    'Bi-Weekly',
    'Monthly',
] as const;
const ID_TYPE_OTHER_VALUE = 'Others';
const ID_TYPE_OPTIONS = [
    'SSS',
    'GSIS',
    'TIN',
    'Phil ID',
    ID_TYPE_OTHER_VALUE,
] as const;
const RELEASE_METHOD_OPTIONS = [
    'ATM',
    'Bank Transfer',
    'Check',
    'Cash',
] as const;
const NATURE_OF_BUSINESS_OTHER_VALUE = 'Other';
const NATURE_OF_BUSINESS_OPTIONS = [
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
const PROFILE_TAB_ORDER = [
    'account',
    'personal',
    'work',
    'bank',
    'dependents',
] as const;
type ProfileTab = (typeof PROFILE_TAB_ORDER)[number];
// Name/birthdate only -- cycle status/number are collected by the
// loan-request wizard, not Settings (see DependentCategorySection's
// showCycleFields prop).
const DEPENDENT_FIELD_KEYS = DEPENDENT_CATEGORIES.flatMap((category) =>
    Array.from({ length: category.cap }, (_, index) => index + 1).flatMap(
        (slot) =>
            (['name', 'birthdate'] as const).map((attribute) =>
                slotFieldKey(category.key, slot, attribute),
            ),
    ),
);
const PROFILE_TAB_FIELDS: Record<ProfileTab, string[]> = {
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
        'release_uses_payout_account',
        'release_bank_name',
        'release_account_name',
        'release_account_number',
        'release_account_type',
        'source_of_fund_wealth',
        'id_type',
        'id_type_other',
        'id_number',
        'height_cm',
        'weight_kg',
    ],
    dependents: DEPENDENT_FIELD_KEYS,
};

const tabForField = (field: string): ProfileTab | null => {
    for (const tab of PROFILE_TAB_ORDER) {
        if (PROFILE_TAB_FIELDS[tab].includes(field)) {
            return tab;
        }
    }

    return null;
};

const findFirstTabWithErrors = (
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

const findFirstInvalidField = (
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

const focusInvalidField = (fieldName: string | null): void => {
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

const calculateAge = (birthday: string | null): number | null => {
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

const hasWmasterValue = (
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

const normalizeCivilStatusValue = (value?: string | null): string => {
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

const normalizePaydayValue = (value?: string | null): string => {
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

const handleMobileNumberInput = (
    event: ChangeEvent<HTMLInputElement>,
): void => {
    event.target.value = normalizeMobileNumberInput(event.target.value);
};

export default function Profile({
    mustVerifyEmail,
    status,
    adminProfile = null,
    memberRecord = null,
    memberApplicationProfile = null,
    dependents = null,
    profileCompletion = null,
    onboarding = false,
}: Props) {
    const { auth } = usePage().props;
    const hasMemberAccess = auth.hasMemberAccess;
    const getInitials = useInitials();
    const profilePhotoInputRef = useRef<HTMLInputElement>(null);
    const profilePhotoDraftFileRef = useRef<File | null>(null);
    const [profilePhotoPreview, setProfilePhotoPreview] = useState<
        string | null
    >(null);
    const [profilePhotoFile, setProfilePhotoFile] = useState<File | null>(null);
    const [profilePhotoDraftPreview, setProfilePhotoDraftPreview] = useState<
        string | null
    >(null);
    const [profilePhotoDraftFile, setProfilePhotoDraftFile] =
        useState<File | null>(null);
    const [showProfilePhotoCropModal, setShowProfilePhotoCropModal] =
        useState<boolean>(false);
    const profilePhotoUrl =
        profilePhotoPreview ?? adminProfile?.profilePicUrl ?? auth.user.avatar;
    const displayName = adminProfile?.fullname ?? auth.user.name;
    const structuredMemberName = [
        memberRecord?.fname,
        memberRecord?.mname,
        memberRecord?.lname,
    ]
        .filter((value): value is string => Boolean(value && value.trim()))
        .join(' ');
    const memberDisplayName =
        structuredMemberName || memberRecord?.bname?.trim() || '';
    const hasStructuredName = Boolean(memberRecord?.hasStructuredName);
    const memberFirstName = memberRecord?.fname?.trim() ?? '';
    const memberMiddleName = memberRecord?.mname?.trim() ?? '';
    const memberLastName = memberRecord?.lname?.trim() ?? '';
    const memberAge = calculateAge(memberRecord?.birthday ?? null);
    const memberBirthplace = memberRecord?.birthplace?.trim() ?? '';
    const memberBirthplaceCity = memberRecord?.birthplace_city?.trim() ?? '';
    const memberBirthplaceProvince =
        memberRecord?.birthplace_province?.trim() ?? '';
    const memberAddressStreet = memberRecord?.address1?.trim() ?? '';
    const memberAddressCity = memberRecord?.address2?.trim() ?? '';
    const memberAddressProvince = memberRecord?.address3?.trim() ?? '';
    const memberAddressZip = memberRecord?.zip_code?.trim() ?? '';
    const memberCivilStatus = normalizeCivilStatusValue(
        memberRecord?.civilstat ?? '',
    );
    const numberOfChildrenValue =
        memberApplicationProfile?.number_of_children ??
        memberRecord?.number_of_children ??
        '';
    const memberOccupation = memberRecord?.occupation?.trim() ?? '';
    const memberCurrentPosition =
        memberApplicationProfile?.current_position?.trim() ?? '';
    const resolvedCurrentPosition =
        memberCurrentPosition !== '' ? memberCurrentPosition : memberOccupation;
    const isCurrentPositionFromWmaster =
        memberCurrentPosition === '' && hasWmasterValue(memberOccupation);
    const isBirthplaceLocked = hasWmasterValue(memberBirthplace);
    const isSpouseNameLocked = hasWmasterValue(memberRecord?.spouse_name);
    const isCivilStatusLocked = hasWmasterValue(memberCivilStatus);
    const isHousingStatusLocked = hasWmasterValue(memberRecord?.housing_status);
    const isProfileComplete = Boolean(profileCompletion?.isComplete);
    const missingProfileFields = profileCompletion?.missingFields ?? [];
    const missingFieldKeys = profileCompletion?.missingFieldKeys ?? [];
    const missingFieldKeySet = new Set(missingFieldKeys);
    const isFieldMissing = (field: string) => missingFieldKeySet.has(field);
    const jumpToField = (field: string) => {
        const tab = tabForField(field);

        if (tab && (hasMemberAccess || tab === 'account')) {
            setActiveTab(tab);
        }

        focusInvalidField(field);
    };
    const showOnboardingAlert =
        onboarding && hasMemberAccess && !isProfileComplete;
    const showMissingProfileFields =
        hasMemberAccess &&
        !isProfileComplete &&
        missingProfileFields.length > 0;
    const initialBirthplaceCity =
        memberApplicationProfile?.birthplace_city?.trim() ||
        memberBirthplaceCity;
    const initialBirthplaceProvince =
        memberApplicationProfile?.birthplace_province?.trim() ||
        memberBirthplaceProvince;
    const birthplaceProvinceSearch = useLocationSearch({
        initialQuery: initialBirthplaceProvince,
        searchUrl: provinces.url(),
    });
    const birthplaceCitySearch = useLocationSearch({
        initialQuery: initialBirthplaceCity,
        searchUrl: cities.url(),
        params: {
            province: birthplaceProvinceSearch.query || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
    const homeAddress1 =
        memberApplicationProfile?.home_address1?.trim() ||
        memberAddressStreet ||
        '';
    const homeAddressBarangay =
        memberApplicationProfile?.home_address_barangay?.trim() ||
        memberRecord?.barangay?.trim() ||
        '';
    const homeAddress2 =
        memberApplicationProfile?.home_address2?.trim() ||
        memberAddressCity ||
        '';
    const homeAddress3 =
        memberApplicationProfile?.home_address3?.trim() ||
        memberAddressProvince ||
        '';
    const homeAddressZip =
        memberApplicationProfile?.home_address_zip?.trim() ||
        memberAddressZip ||
        '';
    const [homeAddressZipValue, setHomeAddressZipValue] =
        useState<string>(homeAddressZip);
    const homeAddressBarangayRawHint =
        !memberApplicationProfile?.home_address_barangay?.trim() &&
        memberRecord?.barangay_raw &&
        memberRecord.barangay_raw.trim().toLowerCase() !==
            homeAddressBarangay.toLowerCase()
            ? memberRecord.barangay_raw.trim()
            : null;
    const homeAddress2RawHint =
        !memberApplicationProfile?.home_address2?.trim() &&
        memberRecord?.address2_raw &&
        memberRecord.address2_raw.trim().toLowerCase() !==
            homeAddress2.toLowerCase()
            ? memberRecord.address2_raw.trim()
            : null;
    const homeAddress3RawHint =
        !memberApplicationProfile?.home_address3?.trim() &&
        memberRecord?.address3_raw &&
        memberRecord.address3_raw.trim().toLowerCase() !==
            homeAddress3.toLowerCase()
            ? memberRecord.address3_raw.trim()
            : null;
    const homeProvinceSearch = useLocationSearch({
        initialQuery: homeAddress3,
        searchUrl: provinces.url(),
    });
    const homeCitySearch = useLocationSearch({
        initialQuery: homeAddress2,
        searchUrl: cities.url(),
        params: {
            province: homeProvinceSearch.query || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
    const homeBarangaySearch = useLocationSearch({
        initialQuery: homeAddressBarangay,
        searchUrl: barangays.url(),
        params: {
            municipality: homeCitySearch.selectedValue || undefined,
            province: homeProvinceSearch.query || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
    const employerBusinessAddress1 =
        memberApplicationProfile?.employer_business_address1?.trim() ?? '';
    const employerBusinessAddressBarangay =
        memberApplicationProfile?.employer_business_address_barangay?.trim() ??
        '';
    const employerBusinessAddress2 =
        memberApplicationProfile?.employer_business_address2?.trim() ?? '';
    const employerBusinessAddress3 =
        memberApplicationProfile?.employer_business_address3?.trim() ?? '';
    const employerBusinessAddressZip =
        memberApplicationProfile?.employer_business_address_zip?.trim() ?? '';
    const [
        employerBusinessAddressZipValue,
        setEmployerBusinessAddressZipValue,
    ] = useState<string>(employerBusinessAddressZip);
    const employerBusinessProvinceSearch = useLocationSearch({
        initialQuery: employerBusinessAddress3,
        searchUrl: provinces.url(),
    });
    const employerBusinessCitySearch = useLocationSearch({
        initialQuery: employerBusinessAddress2,
        searchUrl: cities.url(),
        params: {
            province: employerBusinessProvinceSearch.query || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
    const employerBusinessBarangaySearch = useLocationSearch({
        initialQuery: employerBusinessAddressBarangay,
        searchUrl: barangays.url(),
        params: {
            municipality: employerBusinessCitySearch.selectedValue || undefined,
            province: employerBusinessProvinceSearch.query || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
    const [educationalAttainment, setEducationalAttainment] = useState<string>(
        memberApplicationProfile?.educational_attainment?.trim() ?? '',
    );
    const [civilStatusValue, setCivilStatusValue] = useState<string>(
        memberApplicationProfile?.civil_status?.trim() ?? '',
    );
    const [housingStatusValue, setHousingStatusValue] = useState<string>(
        memberApplicationProfile?.housing_status?.trim() ?? '',
    );
    const [spouseBirthdateValue, setSpouseBirthdateValue] = useState<string>(
        memberApplicationProfile?.spouse_birthdate ?? '',
    );
    const spouseAge = calculateAge(spouseBirthdateValue || null);
    const effectiveCivilStatus = normalizeCivilStatusValue(
        isCivilStatusLocked ? memberCivilStatus : civilStatusValue,
    );
    const isSingle = effectiveCivilStatus === 'Single';
    const educationalAttainmentOptions =
        educationalAttainment !== '' &&
        !EDUCATIONAL_ATTAINMENT_OPTIONS.includes(educationalAttainment)
            ? [educationalAttainment, ...EDUCATIONAL_ATTAINMENT_OPTIONS]
            : EDUCATIONAL_ATTAINMENT_OPTIONS;
    const [employmentType, setEmploymentType] = useState<string>(
        memberApplicationProfile?.employment_type?.trim() ?? '',
    );
    const employmentTypeOptions =
        employmentType !== '' &&
        !EMPLOYMENT_TYPE_OPTIONS.includes(employmentType)
            ? [employmentType, ...EMPLOYMENT_TYPE_OPTIONS]
            : EMPLOYMENT_TYPE_OPTIONS;
    const isPensioner = employmentType === PENSIONER_EMPLOYMENT_TYPE;
    const initialNatureOfBusiness =
        memberApplicationProfile?.nature_of_business?.trim() ?? '';
    const hasPresetNatureOfBusiness =
        initialNatureOfBusiness !== '' &&
        initialNatureOfBusiness !== NATURE_OF_BUSINESS_OTHER_VALUE &&
        NATURE_OF_BUSINESS_OPTIONS.includes(initialNatureOfBusiness);
    const [natureOfBusinessSelection, setNatureOfBusinessSelection] =
        useState<string>(
            initialNatureOfBusiness === ''
                ? ''
                : hasPresetNatureOfBusiness
                  ? initialNatureOfBusiness
                  : NATURE_OF_BUSINESS_OTHER_VALUE,
        );
    const [natureOfBusinessOther, setNatureOfBusinessOther] = useState<string>(
        !hasPresetNatureOfBusiness && initialNatureOfBusiness !== ''
            ? initialNatureOfBusiness
            : '',
    );
    const [grossMonthlyIncome, setGrossMonthlyIncome] = useState<string>(
        memberApplicationProfile?.gross_monthly_income ?? '',
    );
    const [paydaySelection, setPaydaySelection] = useState<string>(
        normalizePaydayValue(memberApplicationProfile?.payday ?? ''),
    );
    const [idTypeSelection, setIdTypeSelection] = useState<string>(
        memberApplicationProfile?.id_type ?? '',
    );
    const [idTypeOther, setIdTypeOther] = useState<string>(
        memberApplicationProfile?.id_type_other ?? '',
    );
    const [releaseMethod, setReleaseMethod] = useState<string>(
        memberApplicationProfile?.release_method ?? '',
    );
    const initialAtmHolderName =
        memberApplicationProfile?.payout_atm_holder_name?.trim() ?? '';
    const [atmHolderName, setAtmHolderName] =
        useState<string>(initialAtmHolderName);
    const [isOwnAtmCard, setIsOwnAtmCard] = useState<boolean>(
        initialAtmHolderName === '' ||
            initialAtmHolderName === memberDisplayName.trim(),
    );
    const [useSameReleaseAccount, setUseSameReleaseAccount] = useState<boolean>(
        memberApplicationProfile?.release_uses_payout_account ?? true,
    );
    const [releaseBankName, setReleaseBankName] = useState<string>(
        memberApplicationProfile?.release_bank_name ?? '',
    );
    const [releaseAccountName, setReleaseAccountName] = useState<string>(
        memberApplicationProfile?.release_account_name ?? '',
    );
    const [releaseAccountNumber, setReleaseAccountNumber] = useState<string>(
        memberApplicationProfile?.release_account_number ?? '',
    );
    const [releaseAccountType, setReleaseAccountType] = useState<string>(
        memberApplicationProfile?.release_account_type ?? '',
    );
    const resolvedNatureOfBusiness =
        natureOfBusinessSelection === NATURE_OF_BUSINESS_OTHER_VALUE
            ? natureOfBusinessOther.trim()
            : natureOfBusinessSelection;
    const [activeTab, setActiveTab] = useState<ProfileTab>(() => {
        if (typeof window === 'undefined') {
            return 'account';
        }

        const requestedTab = new URLSearchParams(window.location.search).get(
            'tab',
        );

        return (PROFILE_TAB_ORDER as readonly string[]).includes(
            requestedTab ?? '',
        )
            ? (requestedTab as ProfileTab)
            : 'account';
    });
    const [dependentsValues, setDependentsValues] = useState<DependentValues>(
        () => dependents ?? {},
    );
    const handleDependentsChange = (
        field: string,
        value: string | number | boolean | null,
    ) => {
        setDependentsValues((current) => ({ ...current, [field]: value }));
    };
    const handleEmployerCitySelect = async (code: string) => {
        if (!code) {
            return;
        }

        try {
            const response = await api.get(zip.url(), {
                params: { locality_code: code },
            });
            const resolvedZip = (
                response.data as { zip?: string | null }
            ).zip?.trim();

            if (resolvedZip) {
                setEmployerBusinessAddressZipValue(resolvedZip);
            }
        } catch {
            // Intentionally left empty: ZIP lookup is best-effort.
        }
    };
    const handleHomeCitySelect = async (code: string) => {
        if (!code) {
            return;
        }

        try {
            const response = await api.get(zip.url(), {
                params: { locality_code: code },
            });
            const resolvedZip = (
                response.data as { zip?: string | null }
            ).zip?.trim();

            if (resolvedZip) {
                setHomeAddressZipValue(resolvedZip);
            }
        } catch {
            // Intentionally left empty: ZIP lookup is best-effort.
        }
    };
    const availableTabs = (
        hasMemberAccess ? PROFILE_TAB_ORDER : ['account']
    ) as ProfileTab[];
    const resolvedActiveTab = hasMemberAccess ? activeTab : 'account';
    const activeTabIndex = availableTabs.indexOf(resolvedActiveTab);
    const previousTab =
        activeTabIndex > 0 ? availableTabs[activeTabIndex - 1] : null;
    const nextTab =
        activeTabIndex >= 0 && activeTabIndex < availableTabs.length - 1
            ? availableTabs[activeTabIndex + 1]
            : null;
    const showOnboardingSteps = onboarding && hasMemberAccess;
    const showStepperNav = availableTabs.length > 1;

    useEffect(() => {
        if (!profilePhotoPreview) {
            return;
        }

        return () => {
            URL.revokeObjectURL(profilePhotoPreview);
        };
    }, [profilePhotoPreview]);

    useEffect(() => {
        if (!profilePhotoDraftPreview) {
            return;
        }

        return () => {
            URL.revokeObjectURL(profilePhotoDraftPreview);
        };
    }, [profilePhotoDraftPreview]);

    const setProfilePhotoDraft = (
        file: File | null,
        previewUrl: string | null,
    ) => {
        profilePhotoDraftFileRef.current = file;
        setProfilePhotoDraftFile(file);
        setProfilePhotoDraftPreview(previewUrl);
    };

    const clearProfilePhotoDraft = () => {
        setProfilePhotoDraft(null, null);
    };

    const setProfilePhotoInputFile = (file: File | null) => {
        const input = profilePhotoInputRef.current;

        if (!input) {
            return;
        }

        if (!file) {
            input.value = '';
            return;
        }

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        input.files = dataTransfer.files;
    };

    const handleProfilePhotoCropClose = () => {
        setShowProfilePhotoCropModal(false);

        if (!profilePhotoDraftFileRef.current) {
            return;
        }

        clearProfilePhotoDraft();
        setProfilePhotoInputFile(profilePhotoFile);
    };

    const handleProfilePhotoCropSave = async (
        result: ProfileImageCropResult,
    ) => {
        if (
            !profilePhotoDraftPreview ||
            !profilePhotoDraftFile ||
            !result.croppedAreaPixels
        ) {
            return;
        }

        try {
            const { file } = await createCroppedImageFile({
                imageSrc: profilePhotoDraftPreview,
                pixelCrop: result.croppedAreaPixels,
                fileName: profilePhotoDraftFile.name,
                mimeType: profilePhotoDraftFile.type,
                maxSize: PROFILE_PHOTO_OUTPUT_SIZE,
                quality: PROFILE_PHOTO_OUTPUT_QUALITY,
            });
            const previewUrl = URL.createObjectURL(file);

            setProfilePhotoPreview(previewUrl);
            setProfilePhotoFile(file);
            setProfilePhotoInputFile(file);
            clearProfilePhotoDraft();
        } catch (error) {
            showErrorToast(error, 'Unable to crop the photo.', {
                id: 'profile-photo-crop',
            });
            throw error;
        }
    };

    const handleProfilePhotoChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (!PROFILE_PHOTO_ALLOWED_TYPES.has(file.type)) {
            showErrorToast(null, 'Please select a JPG, PNG, or WebP image.', {
                id: 'profile-photo-type',
            });
            event.target.value = '';
            clearProfilePhotoDraft();
            return;
        }

        if (file.size > PROFILE_PHOTO_MAX_BYTES) {
            showErrorToast(null, 'Image must be 2MB or smaller.', {
                id: 'profile-photo-size',
            });
            event.target.value = '';
            clearProfilePhotoDraft();
            return;
        }

        const previewUrl = URL.createObjectURL(file);
        setProfilePhotoDraft(file, previewUrl);
        setShowProfilePhotoCropModal(true);
        event.target.value = '';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile Settings</h1>

            <SettingsLayout>
                <SurfaceCard
                    variant="default"
                    padding="lg"
                    className="space-y-6"
                >
                    <section className="max-w-3xl space-y-12">
                        <div className="space-y-6">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <Heading
                                    variant="small"
                                    title="Profile information"
                                    description="Update your profile details, photo, and contact information"
                                />
                                {hasMemberAccess && (
                                    <Badge
                                        variant={
                                            isProfileComplete
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {isProfileComplete
                                            ? 'Profile complete'
                                            : 'Profile incomplete'}
                                    </Badge>
                                )}
                            </div>

                            {showOnboardingAlert && (
                                <Alert className="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/40 dark:text-amber-100">
                                    <AlertTitle>
                                        Complete your profile to continue
                                    </AlertTitle>
                                    <AlertDescription>
                                        Add the personal and work details below
                                        to unlock your client dashboard.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {showMissingProfileFields ? (
                                <Alert className="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-800/50 dark:bg-amber-950/40 dark:text-amber-100">
                                    <AlertTitle>Finish your profile</AlertTitle>
                                    <AlertDescription className="text-amber-900 dark:text-amber-100">
                                        <p>
                                            A few required fields still need
                                            your input to finish onboarding:
                                        </p>
                                        <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
                                            {missingProfileFields.map(
                                                (label, index) => {
                                                    const fieldKey =
                                                        missingFieldKeys[index];

                                                    if (!fieldKey) {
                                                        return (
                                                            <li key={label}>
                                                                {label}
                                                            </li>
                                                        );
                                                    }

                                                    return (
                                                        <li key={fieldKey}>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    jumpToField(
                                                                        fieldKey,
                                                                    )
                                                                }
                                                                className="underline decoration-amber-500/60 underline-offset-2 hover:text-amber-950 dark:hover:text-white"
                                                            >
                                                                {label}
                                                            </button>
                                                        </li>
                                                    );
                                                },
                                            )}
                                        </ul>
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            {status === 'membership-linked' && (
                                <Alert className="border-green-200 bg-green-50 text-green-950 dark:border-green-800/50 dark:bg-green-950/40 dark:text-green-100">
                                    <AlertTitle>Membership linked</AlertTitle>
                                    <AlertDescription>
                                        You now have member portal access.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <Form
                                {...ProfileController.update.form()}
                                options={{
                                    preserveScroll: true,
                                }}
                                onSuccess={() => {
                                    showSuccessToast(
                                        adminToastCopy.success.updated(
                                            'Profile',
                                        ),
                                        { id: 'profile-update' },
                                    );
                                }}
                                onError={(formErrors) => {
                                    showErrorToast(
                                        formErrors,
                                        adminToastCopy.error.updated('Profile'),
                                        { id: 'profile-update' },
                                    );
                                    const nextTabWithErrors =
                                        findFirstTabWithErrors(formErrors);
                                    const firstInvalidField =
                                        findFirstInvalidField(formErrors);

                                    if (
                                        nextTabWithErrors &&
                                        (hasMemberAccess ||
                                            nextTabWithErrors === 'account')
                                    ) {
                                        setActiveTab(nextTabWithErrors);
                                    }

                                    focusInvalidField(firstInvalidField);
                                }}
                                encType="multipart/form-data"
                                noValidate
                                className="space-y-6"
                            >
                                {({
                                    processing,
                                    recentlySuccessful,
                                    errors: formErrors,
                                }) => (
                                    <>
                                        <Tabs
                                            value={resolvedActiveTab}
                                            onValueChange={(value) => {
                                                setActiveTab(
                                                    value as ProfileTab,
                                                );
                                            }}
                                            className="flex w-full flex-col gap-6"
                                        >
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <TabsList className="w-full flex-wrap justify-start gap-2">
                                                    <TabsTrigger value="account">
                                                        Account
                                                    </TabsTrigger>
                                                    {hasMemberAccess && (
                                                        <>
                                                            <TabsTrigger value="personal">
                                                                Personal
                                                            </TabsTrigger>
                                                            <TabsTrigger value="work">
                                                                Work &amp;
                                                                Finances
                                                            </TabsTrigger>
                                                            <TabsTrigger value="bank">
                                                                Bank &amp;
                                                                Payout
                                                            </TabsTrigger>
                                                            <TabsTrigger value="dependents">
                                                                Dependents
                                                            </TabsTrigger>
                                                        </>
                                                    )}
                                                </TabsList>
                                                {showOnboardingSteps && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="shrink-0"
                                                    >
                                                        Step{' '}
                                                        {activeTabIndex + 1} of{' '}
                                                        {availableTabs.length}
                                                    </Badge>
                                                )}
                                            </div>

                                            <TabsContent
                                                value="account"
                                                forceMount
                                                className="mt-0"
                                            >
                                                <SurfaceCard
                                                    variant="muted"
                                                    padding="md"
                                                    className="space-y-6"
                                                >
                                                    <div className="space-y-1">
                                                        <h3 className="text-base font-semibold tracking-tight">
                                                            Account
                                                        </h3>
                                                        <p className="text-sm text-muted-foreground">
                                                            Manage your profile
                                                            photo, login
                                                            details, and contact
                                                            information.
                                                        </p>
                                                    </div>

                                                    <div className="space-y-6">
                                                        <div className="grid gap-3">
                                                            <Label htmlFor="profile_photo">
                                                                Profile picture
                                                            </Label>

                                                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                                                                <label
                                                                    htmlFor="profile_photo"
                                                                    className="group relative flex h-24 w-24 cursor-pointer items-center justify-center rounded-full"
                                                                >
                                                                    <Avatar className="h-24 w-24 overflow-hidden rounded-full border border-border/70 shadow-sm">
                                                                        <AvatarImage
                                                                            src={
                                                                                profilePhotoUrl
                                                                            }
                                                                            alt={
                                                                                displayName
                                                                            }
                                                                            className="object-cover"
                                                                        />
                                                                        <AvatarFallback className="rounded-full bg-neutral-200 text-sm text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                                                            {getInitials(
                                                                                displayName,
                                                                            )}
                                                                        </AvatarFallback>
                                                                    </Avatar>
                                                                    <span className="absolute inset-0 rounded-full bg-black/40 opacity-0 transition-opacity duration-200 group-hover:opacity-100" />
                                                                    <span className="absolute right-1 bottom-1 flex h-8 w-8 items-center justify-center rounded-full border border-white/70 bg-white/90 text-neutral-900 shadow-sm transition-transform duration-200 group-hover:scale-105 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                                                                        <Camera className="h-4 w-4" />
                                                                    </span>
                                                                </label>

                                                                <div className="space-y-2 text-sm text-muted-foreground">
                                                                    <p>
                                                                        Upload a
                                                                        JPG,
                                                                        PNG, or
                                                                        WebP
                                                                        image
                                                                        (max
                                                                        2MB).
                                                                    </p>
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            profilePhotoInputRef.current?.click()
                                                                        }
                                                                    >
                                                                        Change
                                                                        photo
                                                                    </Button>
                                                                </div>
                                                            </div>

                                                            <input
                                                                id="profile_photo"
                                                                ref={
                                                                    profilePhotoInputRef
                                                                }
                                                                name="profile_photo"
                                                                type="file"
                                                                accept="image/png,image/jpeg,image/webp"
                                                                className="sr-only"
                                                                onChange={
                                                                    handleProfilePhotoChange
                                                                }
                                                            />

                                                            <InputError
                                                                className="mt-2"
                                                                message={
                                                                    formErrors.profile_photo
                                                                }
                                                            />
                                                        </div>

                                                        {adminProfile && (
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="fullname">
                                                                    Full name
                                                                </Label>

                                                                <Input
                                                                    id="fullname"
                                                                    className="mt-1 block w-full"
                                                                    defaultValue={
                                                                        adminProfile.fullname ??
                                                                        ''
                                                                    }
                                                                    name="fullname"
                                                                    autoComplete="name"
                                                                    placeholder="Full name"
                                                                />

                                                                <InputError
                                                                    className="mt-2"
                                                                    message={
                                                                        formErrors.fullname
                                                                    }
                                                                />
                                                            </div>
                                                        )}
                                                    </div>

                                                    <Separator />

                                                    <div className="space-y-6">
                                                        <div className="space-y-1">
                                                            <h3 className="text-base font-semibold">
                                                                Basic Account
                                                                Information
                                                            </h3>
                                                            <p className="text-sm text-muted-foreground">
                                                                Update your
                                                                login and
                                                                contact details.
                                                            </p>
                                                        </div>

                                                        <div className="grid gap-4 md:grid-cols-2">
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="username">
                                                                    Username
                                                                </Label>

                                                                <Input
                                                                    id="username"
                                                                    className="mt-1 block w-full"
                                                                    defaultValue={
                                                                        auth
                                                                            .user
                                                                            .username ??
                                                                        auth
                                                                            .user
                                                                            .name
                                                                    }
                                                                    name="username"
                                                                    required
                                                                    autoComplete="username"
                                                                    placeholder="Username"
                                                                />

                                                                <InputError
                                                                    className="mt-2"
                                                                    message={
                                                                        formErrors.username
                                                                    }
                                                                />
                                                            </div>

                                                            <div className="grid gap-2">
                                                                <Label htmlFor="email">
                                                                    Email
                                                                    address
                                                                </Label>

                                                                <Input
                                                                    id="email"
                                                                    type="email"
                                                                    className="mt-1 block w-full"
                                                                    defaultValue={
                                                                        auth
                                                                            .user
                                                                            .email
                                                                    }
                                                                    name="email"
                                                                    required
                                                                    autoComplete="username"
                                                                    placeholder="Email address"
                                                                />

                                                                <InputError
                                                                    className="mt-2"
                                                                    message={
                                                                        formErrors.email
                                                                    }
                                                                />
                                                            </div>

                                                            <div className="grid gap-2">
                                                                <Label htmlFor="phoneno">
                                                                    Cell number
                                                                </Label>

                                                                <Input
                                                                    id="phoneno"
                                                                    type="tel"
                                                                    className="mt-1 block w-full"
                                                                    defaultValue={
                                                                        auth
                                                                            .user
                                                                            .phoneno ??
                                                                        ''
                                                                    }
                                                                    name="phoneno"
                                                                    required
                                                                    autoComplete="tel"
                                                                    inputMode="numeric"
                                                                    maxLength={
                                                                        11
                                                                    }
                                                                    placeholder="09XXXXXXXXX"
                                                                    onChange={
                                                                        handleMobileNumberInput
                                                                    }
                                                                />

                                                                <InputError
                                                                    className="mt-2"
                                                                    message={
                                                                        formErrors.phoneno
                                                                    }
                                                                />
                                                            </div>
                                                        </div>

                                                        {mustVerifyEmail &&
                                                            auth.user
                                                                .email_verified_at ===
                                                                null && (
                                                                <div>
                                                                    <p className="-mt-4 text-sm text-muted-foreground">
                                                                        Your
                                                                        email
                                                                        address
                                                                        is
                                                                        unverified.{' '}
                                                                        <Link
                                                                            href={send()}
                                                                            as="button"
                                                                            className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                                        >
                                                                            Click
                                                                            here
                                                                            to
                                                                            resend
                                                                            the
                                                                            verification
                                                                            email.
                                                                        </Link>
                                                                    </p>

                                                                    {status ===
                                                                        'verification-link-sent' && (
                                                                        <div className="mt-2 text-sm font-medium text-green-600">
                                                                            A
                                                                            new
                                                                            verification
                                                                            link
                                                                            has
                                                                            been
                                                                            sent
                                                                            to
                                                                            your
                                                                            email
                                                                            address.
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            )}
                                                    </div>
                                                </SurfaceCard>
                                            </TabsContent>

                                            {hasMemberAccess && (
                                                <TabsContent
                                                    value="personal"
                                                    forceMount
                                                    className="mt-0"
                                                >
                                                    <SurfaceCard
                                                        variant="muted"
                                                        padding="md"
                                                        className="space-y-6"
                                                    >
                                                        <div className="space-y-6">
                                                            <div className="space-y-1">
                                                                <h3 className="text-base font-semibold">
                                                                    Personal
                                                                </h3>
                                                                <p className="text-sm text-muted-foreground">
                                                                    Review your
                                                                    verified
                                                                    member
                                                                    details and
                                                                    keep your
                                                                    application
                                                                    profile
                                                                    updated.
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    Fields with
                                                                    a subtle
                                                                    outline come
                                                                    from your
                                                                    verified
                                                                    member
                                                                    record and
                                                                    are
                                                                    read-only.
                                                                </p>
                                                            </div>

                                                            {!memberRecord && (
                                                                <p className="text-sm text-muted-foreground">
                                                                    No member
                                                                    record was
                                                                    found for
                                                                    this
                                                                    account.
                                                                </p>
                                                            )}

                                                            <div className="grid gap-4 md:grid-cols-3">
                                                                <div className="grid gap-2 md:col-span-3">
                                                                    <Label htmlFor="member_full_name">
                                                                        Member
                                                                        full
                                                                        name
                                                                    </Label>

                                                                    <Input
                                                                        id="member_full_name"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            hasWmasterValue(
                                                                                memberDisplayName,
                                                                            ) &&
                                                                                WMASTER_VALUE_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            memberDisplayName
                                                                        }
                                                                        placeholder="Not available"
                                                                        disabled
                                                                    />
                                                                </div>
                                                                {hasStructuredName && (
                                                                    <>
                                                                        {memberFirstName !==
                                                                            '' && (
                                                                            <div className="grid gap-2">
                                                                                <Label htmlFor="member_first_name">
                                                                                    First
                                                                                    name
                                                                                </Label>

                                                                                <Input
                                                                                    id="member_first_name"
                                                                                    className={cn(
                                                                                        'mt-1 block w-full',
                                                                                        hasWmasterValue(
                                                                                            memberFirstName,
                                                                                        ) &&
                                                                                            WMASTER_VALUE_CLASS,
                                                                                    )}
                                                                                    defaultValue={
                                                                                        memberFirstName
                                                                                    }
                                                                                    disabled
                                                                                />
                                                                            </div>
                                                                        )}

                                                                        {memberLastName !==
                                                                            '' && (
                                                                            <div className="grid gap-2">
                                                                                <Label htmlFor="member_last_name">
                                                                                    Last
                                                                                    name
                                                                                </Label>

                                                                                <Input
                                                                                    id="member_last_name"
                                                                                    className={cn(
                                                                                        'mt-1 block w-full',
                                                                                        hasWmasterValue(
                                                                                            memberLastName,
                                                                                        ) &&
                                                                                            WMASTER_VALUE_CLASS,
                                                                                    )}
                                                                                    defaultValue={
                                                                                        memberLastName
                                                                                    }
                                                                                    disabled
                                                                                />
                                                                            </div>
                                                                        )}

                                                                        {memberMiddleName !==
                                                                            '' && (
                                                                            <div className="grid gap-2">
                                                                                <Label htmlFor="member_middle_name">
                                                                                    Middle
                                                                                    name
                                                                                </Label>

                                                                                <Input
                                                                                    id="member_middle_name"
                                                                                    className={cn(
                                                                                        'mt-1 block w-full',
                                                                                        hasWmasterValue(
                                                                                            memberMiddleName,
                                                                                        ) &&
                                                                                            WMASTER_VALUE_CLASS,
                                                                                    )}
                                                                                    defaultValue={
                                                                                        memberMiddleName
                                                                                    }
                                                                                    disabled
                                                                                />
                                                                            </div>
                                                                        )}
                                                                    </>
                                                                )}
                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="nickname">
                                                                        Nickname
                                                                    </Label>

                                                                    <Input
                                                                        id="nickname"
                                                                        className="mt-1 block w-full"
                                                                        defaultValue={
                                                                            memberApplicationProfile?.nickname ??
                                                                            ''
                                                                        }
                                                                        name="nickname"
                                                                        autoComplete="nickname"
                                                                        placeholder="Preferred name"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.nickname
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="member_birthday">
                                                                        Birthdate
                                                                    </Label>

                                                                    <Input
                                                                        id="member_birthday"
                                                                        type="date"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            hasWmasterValue(
                                                                                memberRecord?.birthday,
                                                                            ) &&
                                                                                WMASTER_VALUE_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            memberRecord?.birthday ??
                                                                            ''
                                                                        }
                                                                        disabled
                                                                    />
                                                                </div>

                                                                {isBirthplaceLocked ? (
                                                                    <div className="grid gap-2 md:col-span-2">
                                                                        <Label htmlFor="birthplace">
                                                                            Birthplace
                                                                        </Label>
                                                                        <Input
                                                                            id="birthplace"
                                                                            className={cn(
                                                                                'mt-1 block w-full',
                                                                                WMASTER_VALUE_CLASS,
                                                                            )}
                                                                            defaultValue={
                                                                                memberBirthplace
                                                                            }
                                                                            placeholder="Not available"
                                                                            disabled
                                                                        />
                                                                        <input
                                                                            type="hidden"
                                                                            name="birthplace_city"
                                                                            value={
                                                                                memberBirthplaceCity
                                                                            }
                                                                        />
                                                                        <input
                                                                            type="hidden"
                                                                            name="birthplace_province"
                                                                            value={
                                                                                memberBirthplaceProvince
                                                                            }
                                                                        />
                                                                    </div>
                                                                ) : (
                                                                    <>
                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="birthplace_province">
                                                                                Birthplace
                                                                                province
                                                                            </Label>
                                                                            <LocationCombobox
                                                                                id="birthplace_province"
                                                                                name="birthplace_province"
                                                                                search={
                                                                                    birthplaceProvinceSearch
                                                                                }
                                                                                placeholder="Select province"
                                                                                required
                                                                                inputClassName={cn(
                                                                                    'mt-1 block w-full',
                                                                                    isFieldMissing(
                                                                                        'birthplace_province',
                                                                                    ) &&
                                                                                        MISSING_FIELD_CLASS,
                                                                                )}
                                                                                loadingMessage="Searching province suggestions..."
                                                                                errorMessage="Province suggestions are temporarily unavailable."
                                                                                promptMessage="Type at least 2 characters to search provinces."
                                                                                onSelect={() => {
                                                                                    birthplaceCitySearch.setSelectedValue(
                                                                                        '',
                                                                                    );
                                                                                }}
                                                                            />

                                                                            <InputError
                                                                                className="mt-2"
                                                                                message={
                                                                                    formErrors.birthplace_province
                                                                                }
                                                                            />
                                                                        </div>
                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="birthplace_city">
                                                                                Birthplace
                                                                                city/municipality
                                                                            </Label>
                                                                            <LocationCombobox
                                                                                id="birthplace_city"
                                                                                name="birthplace_city"
                                                                                search={
                                                                                    birthplaceCitySearch
                                                                                }
                                                                                placeholder="Select city or municipality"
                                                                                required
                                                                                disabled={
                                                                                    !birthplaceProvinceSearch.selectedValue
                                                                                }
                                                                                inputClassName={cn(
                                                                                    'mt-1 block w-full',
                                                                                    isFieldMissing(
                                                                                        'birthplace_city',
                                                                                    ) &&
                                                                                        MISSING_FIELD_CLASS,
                                                                                )}
                                                                                loadingMessage="Searching city suggestions..."
                                                                                errorMessage="City suggestions are temporarily unavailable."
                                                                                promptMessage="Select a province first."
                                                                                onSelect={(
                                                                                    suggestion,
                                                                                ) => {
                                                                                    if (
                                                                                        suggestion.province
                                                                                    ) {
                                                                                        birthplaceProvinceSearch.setSelectedValue(
                                                                                            suggestion.province,
                                                                                        );
                                                                                    }
                                                                                }}
                                                                            />

                                                                            <InputError
                                                                                className="mt-2"
                                                                                message={
                                                                                    formErrors.birthplace_city
                                                                                }
                                                                            />
                                                                        </div>
                                                                    </>
                                                                )}

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="member_age">
                                                                        Age
                                                                    </Label>

                                                                    <Input
                                                                        id="member_age"
                                                                        type="number"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            hasWmasterValue(
                                                                                memberAge,
                                                                            ) &&
                                                                                WMASTER_VALUE_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            memberAge ??
                                                                            ''
                                                                        }
                                                                        placeholder="Not available"
                                                                        disabled
                                                                    />
                                                                </div>

                                                                <div className="grid gap-4 md:col-span-3 md:grid-cols-2">
                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="home_address3">
                                                                            Province
                                                                        </Label>
                                                                        <OriginalValueHint
                                                                            value={
                                                                                homeAddress3RawHint
                                                                            }
                                                                        />

                                                                        <LocationCombobox
                                                                            id="home_address3"
                                                                            name="home_address3"
                                                                            search={
                                                                                homeProvinceSearch
                                                                            }
                                                                            placeholder="Select province"
                                                                            required
                                                                            inputClassName={cn(
                                                                                'mt-1 block w-full',
                                                                                isFieldMissing(
                                                                                    'home_address3',
                                                                                ) &&
                                                                                    MISSING_FIELD_CLASS,
                                                                            )}
                                                                            loadingMessage="Searching province suggestions..."
                                                                            errorMessage="Province suggestions are temporarily unavailable."
                                                                            promptMessage="Type at least 2 characters to search provinces."
                                                                            onSelect={() => {
                                                                                homeCitySearch.setSelectedValue(
                                                                                    '',
                                                                                );
                                                                                homeBarangaySearch.setSelectedValue(
                                                                                    '',
                                                                                );
                                                                                setHomeAddressZipValue(
                                                                                    '',
                                                                                );
                                                                            }}
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.home_address3
                                                                            }
                                                                        />
                                                                    </div>

                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="home_address2">
                                                                            City/Municipality
                                                                        </Label>
                                                                        <OriginalValueHint
                                                                            value={
                                                                                homeAddress2RawHint
                                                                            }
                                                                        />

                                                                        <LocationCombobox
                                                                            id="home_address2"
                                                                            name="home_address2"
                                                                            search={
                                                                                homeCitySearch
                                                                            }
                                                                            placeholder="Select city or municipality"
                                                                            required
                                                                            disabled={
                                                                                !homeProvinceSearch.selectedValue
                                                                            }
                                                                            inputClassName={cn(
                                                                                'mt-1 block w-full',
                                                                                isFieldMissing(
                                                                                    'home_address2',
                                                                                ) &&
                                                                                    MISSING_FIELD_CLASS,
                                                                            )}
                                                                            loadingMessage="Searching city suggestions..."
                                                                            errorMessage="City suggestions are temporarily unavailable."
                                                                            promptMessage="Select a province first."
                                                                            onSelect={(
                                                                                suggestion,
                                                                            ) => {
                                                                                if (
                                                                                    suggestion.province
                                                                                ) {
                                                                                    homeProvinceSearch.setSelectedValue(
                                                                                        suggestion.province,
                                                                                    );
                                                                                }

                                                                                homeBarangaySearch.setSelectedValue(
                                                                                    '',
                                                                                );
                                                                                void handleHomeCitySelect(
                                                                                    suggestion.code,
                                                                                );
                                                                            }}
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.home_address2
                                                                            }
                                                                        />
                                                                    </div>

                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="home_address_zip">
                                                                            ZIP
                                                                            code
                                                                        </Label>

                                                                        <Input
                                                                            id="home_address_zip"
                                                                            className="mt-1 block w-full"
                                                                            value={
                                                                                homeAddressZipValue
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setHomeAddressZipValue(
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            name="home_address_zip"
                                                                            inputMode="numeric"
                                                                            autoComplete="postal-code"
                                                                            placeholder="Auto-filled from city"
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.home_address_zip
                                                                            }
                                                                        />
                                                                    </div>

                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="home_address_barangay">
                                                                            Barangay
                                                                        </Label>
                                                                        <OriginalValueHint
                                                                            value={
                                                                                homeAddressBarangayRawHint
                                                                            }
                                                                        />

                                                                        <LocationCombobox
                                                                            id="home_address_barangay"
                                                                            name="home_address_barangay"
                                                                            search={
                                                                                homeBarangaySearch
                                                                            }
                                                                            placeholder="Select barangay"
                                                                            disabled={
                                                                                !homeCitySearch.selectedValue
                                                                            }
                                                                            inputClassName={cn(
                                                                                'mt-1 block w-full',
                                                                                isFieldMissing(
                                                                                    'home_address_barangay',
                                                                                ) &&
                                                                                    MISSING_FIELD_CLASS,
                                                                            )}
                                                                            loadingMessage="Loading barangays..."
                                                                            errorMessage="Barangay suggestions are temporarily unavailable."
                                                                            promptMessage="Select a city or municipality first."
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.home_address_barangay
                                                                            }
                                                                        />
                                                                    </div>

                                                                    <div className="grid gap-2 md:col-span-2">
                                                                        <Label htmlFor="home_address1">
                                                                            Address
                                                                            (street)
                                                                        </Label>

                                                                        <Input
                                                                            id="home_address1"
                                                                            className={cn(
                                                                                'mt-1 block w-full',
                                                                                isFieldMissing(
                                                                                    'home_address1',
                                                                                ) &&
                                                                                    MISSING_FIELD_CLASS,
                                                                            )}
                                                                            defaultValue={
                                                                                homeAddress1
                                                                            }
                                                                            name="home_address1"
                                                                            required
                                                                            placeholder="Home address"
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.home_address1
                                                                            }
                                                                        />
                                                                    </div>
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="length_of_stay">
                                                                        Length
                                                                        of stay
                                                                    </Label>

                                                                    <Input
                                                                        id="length_of_stay"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            isFieldMissing(
                                                                                'length_of_stay',
                                                                            ) &&
                                                                                MISSING_FIELD_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            memberApplicationProfile?.length_of_stay ??
                                                                            ''
                                                                        }
                                                                        name="length_of_stay"
                                                                        required
                                                                        placeholder="e.g. 2 years"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.length_of_stay
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label
                                                                        htmlFor={
                                                                            isHousingStatusLocked
                                                                                ? 'member_housing_status'
                                                                                : 'housing_status'
                                                                        }
                                                                    >
                                                                        Housing
                                                                        status
                                                                    </Label>

                                                                    {isHousingStatusLocked ? (
                                                                        <Input
                                                                            id="member_housing_status"
                                                                            className={cn(
                                                                                'mt-1 block w-full',
                                                                                WMASTER_VALUE_CLASS,
                                                                            )}
                                                                            defaultValue={
                                                                                memberRecord?.housing_status ??
                                                                                ''
                                                                            }
                                                                            placeholder="Not available"
                                                                            disabled
                                                                        />
                                                                    ) : (
                                                                        <>
                                                                            <Select
                                                                                value={
                                                                                    housingStatusValue ||
                                                                                    undefined
                                                                                }
                                                                                onValueChange={
                                                                                    setHousingStatusValue
                                                                                }
                                                                            >
                                                                                <SelectTrigger
                                                                                    id="housing_status"
                                                                                    className={cn(
                                                                                        'mt-1 w-full',
                                                                                        isFieldMissing(
                                                                                            'housing_status',
                                                                                        ) &&
                                                                                            MISSING_FIELD_CLASS,
                                                                                    )}
                                                                                >
                                                                                    <SelectValue placeholder="Select housing status" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {HOUSING_STATUS_OPTIONS.map(
                                                                                        (
                                                                                            option,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    option.value
                                                                                                }
                                                                                                value={
                                                                                                    option.value
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    option.label
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                            <input
                                                                                type="hidden"
                                                                                name="housing_status"
                                                                                value={
                                                                                    housingStatusValue
                                                                                }
                                                                            />
                                                                            <InputError
                                                                                className="mt-2"
                                                                                message={
                                                                                    formErrors.housing_status
                                                                                }
                                                                            />
                                                                        </>
                                                                    )}
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label
                                                                        htmlFor={
                                                                            isCivilStatusLocked
                                                                                ? 'member_civil_status'
                                                                                : 'civil_status'
                                                                        }
                                                                    >
                                                                        Civil
                                                                        status
                                                                    </Label>

                                                                    {isCivilStatusLocked ? (
                                                                        <Select
                                                                            value={
                                                                                memberCivilStatus ||
                                                                                undefined
                                                                            }
                                                                            disabled
                                                                        >
                                                                            <SelectTrigger
                                                                                id="member_civil_status"
                                                                                className={cn(
                                                                                    'mt-1 w-full',
                                                                                    WMASTER_VALUE_CLASS,
                                                                                )}
                                                                            >
                                                                                <SelectValue placeholder="Not available" />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                {CIVIL_STATUS_OPTIONS.map(
                                                                                    (
                                                                                        option,
                                                                                    ) => (
                                                                                        <SelectItem
                                                                                            key={
                                                                                                option
                                                                                            }
                                                                                            value={
                                                                                                option
                                                                                            }
                                                                                        >
                                                                                            {
                                                                                                option
                                                                                            }
                                                                                        </SelectItem>
                                                                                    ),
                                                                                )}
                                                                            </SelectContent>
                                                                        </Select>
                                                                    ) : (
                                                                        <>
                                                                            <Select
                                                                                value={
                                                                                    civilStatusValue ||
                                                                                    undefined
                                                                                }
                                                                                onValueChange={
                                                                                    setCivilStatusValue
                                                                                }
                                                                            >
                                                                                <SelectTrigger
                                                                                    id="civil_status"
                                                                                    className={cn(
                                                                                        'mt-1 w-full',
                                                                                        isFieldMissing(
                                                                                            'civil_status',
                                                                                        ) &&
                                                                                            MISSING_FIELD_CLASS,
                                                                                    )}
                                                                                >
                                                                                    <SelectValue placeholder="Select civil status" />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {CIVIL_STATUS_OPTIONS.map(
                                                                                        (
                                                                                            option,
                                                                                        ) => (
                                                                                            <SelectItem
                                                                                                key={
                                                                                                    option
                                                                                                }
                                                                                                value={
                                                                                                    option
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    option
                                                                                                }
                                                                                            </SelectItem>
                                                                                        ),
                                                                                    )}
                                                                                </SelectContent>
                                                                            </Select>
                                                                            <input
                                                                                type="hidden"
                                                                                name="civil_status"
                                                                                value={
                                                                                    civilStatusValue
                                                                                }
                                                                            />
                                                                            <InputError
                                                                                className="mt-2"
                                                                                message={
                                                                                    formErrors.civil_status
                                                                                }
                                                                            />
                                                                        </>
                                                                    )}
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="educational_attainment">
                                                                        Educational
                                                                        attainment
                                                                    </Label>

                                                                    <Select
                                                                        value={
                                                                            educationalAttainment ||
                                                                            undefined
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) => {
                                                                            setEducationalAttainment(
                                                                                value,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <SelectTrigger
                                                                            id="educational_attainment"
                                                                            className={cn(
                                                                                'mt-1 w-full',
                                                                                isFieldMissing(
                                                                                    'educational_attainment',
                                                                                ) &&
                                                                                    MISSING_FIELD_CLASS,
                                                                            )}
                                                                        >
                                                                            <SelectValue placeholder="Select educational attainment" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {educationalAttainmentOptions.map(
                                                                                (
                                                                                    option,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            option
                                                                                        }
                                                                                        value={
                                                                                            option
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            option
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                        </SelectContent>
                                                                    </Select>

                                                                    <input
                                                                        type="hidden"
                                                                        name="educational_attainment"
                                                                        value={
                                                                            educationalAttainment
                                                                        }
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.educational_attainment
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="number_of_children">
                                                                        No. of
                                                                        children
                                                                    </Label>

                                                                    <Input
                                                                        id="number_of_children"
                                                                        type="number"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            hasWmasterValue(
                                                                                memberRecord?.number_of_children,
                                                                            ) &&
                                                                                WMASTER_VALUE_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            numberOfChildrenValue
                                                                        }
                                                                        name="number_of_children"
                                                                        min={0}
                                                                        inputMode="numeric"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.number_of_children
                                                                        }
                                                                    />
                                                                </div>

                                                                {!isSingle && (
                                                                    <>
                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="member_record_spouse_name">
                                                                                Spouse
                                                                                name
                                                                            </Label>
                                                                            {isSpouseNameLocked ? (
                                                                                <Input
                                                                                    id="member_record_spouse_name"
                                                                                    className={cn(
                                                                                        'mt-1 block w-full',
                                                                                        WMASTER_VALUE_CLASS,
                                                                                    )}
                                                                                    defaultValue={
                                                                                        memberRecord?.spouse_name ??
                                                                                        ''
                                                                                    }
                                                                                    placeholder="Not available"
                                                                                    disabled
                                                                                />
                                                                            ) : (
                                                                                <>
                                                                                    <Input
                                                                                        id="member_record_spouse_name"
                                                                                        className={cn(
                                                                                            'mt-1 block w-full',
                                                                                            isFieldMissing(
                                                                                                'spouse_name',
                                                                                            ) &&
                                                                                                MISSING_FIELD_CLASS,
                                                                                        )}
                                                                                        defaultValue={
                                                                                            memberApplicationProfile?.spouse_name ??
                                                                                            ''
                                                                                        }
                                                                                        name="spouse_name"
                                                                                        placeholder="Spouse name"
                                                                                    />
                                                                                    <InputError
                                                                                        className="mt-2"
                                                                                        message={
                                                                                            formErrors.spouse_name
                                                                                        }
                                                                                    />
                                                                                </>
                                                                            )}
                                                                        </div>

                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="spouse_birthdate">
                                                                                Spouse
                                                                                birthdate
                                                                            </Label>

                                                                            <BirthdateInput
                                                                                id="spouse_birthdate"
                                                                                name="spouse_birthdate"
                                                                                className={cn(
                                                                                    isFieldMissing(
                                                                                        'spouse_birthdate',
                                                                                    ) &&
                                                                                        MISSING_FIELD_CLASS,
                                                                                )}
                                                                                value={
                                                                                    spouseBirthdateValue
                                                                                }
                                                                                onValueChange={
                                                                                    setSpouseBirthdateValue
                                                                                }
                                                                            />

                                                                            <InputError
                                                                                className="mt-2"
                                                                                message={
                                                                                    formErrors.spouse_birthdate
                                                                                }
                                                                            />
                                                                        </div>

                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="spouse_age_display">
                                                                                Spouse
                                                                                age
                                                                            </Label>

                                                                            <Input
                                                                                id="spouse_age_display"
                                                                                type="number"
                                                                                className="mt-1 block w-full"
                                                                                value={
                                                                                    spouseAge ??
                                                                                    ''
                                                                                }
                                                                                placeholder="Computed from birthdate"
                                                                                disabled
                                                                                readOnly
                                                                            />
                                                                        </div>

                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="spouse_cell_no">
                                                                                Spouse
                                                                                cell
                                                                                no.
                                                                            </Label>

                                                                            <Input
                                                                                id="spouse_cell_no"
                                                                                type="tel"
                                                                                className="mt-1 block w-full"
                                                                                defaultValue={
                                                                                    memberApplicationProfile?.spouse_cell_no ??
                                                                                    ''
                                                                                }
                                                                                name="spouse_cell_no"
                                                                                inputMode="numeric"
                                                                                maxLength={
                                                                                    11
                                                                                }
                                                                                placeholder="09XXXXXXXXX"
                                                                                onChange={
                                                                                    handleMobileNumberInput
                                                                                }
                                                                            />

                                                                            <InputError
                                                                                className="mt-2"
                                                                                message={
                                                                                    formErrors.spouse_cell_no
                                                                                }
                                                                            />
                                                                        </div>
                                                                    </>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </SurfaceCard>
                                                </TabsContent>
                                            )}

                                            {hasMemberAccess && (
                                                <TabsContent
                                                    value="work"
                                                    forceMount
                                                    className="mt-0"
                                                >
                                                    <SurfaceCard
                                                        variant="muted"
                                                        padding="md"
                                                        className="space-y-6"
                                                    >
                                                        <div className="space-y-6">
                                                            <div className="space-y-1">
                                                                <h3 className="text-base font-semibold">
                                                                    Work &amp;
                                                                    Finances
                                                                </h3>
                                                                <p className="text-sm text-muted-foreground">
                                                                    Keep your
                                                                    employment
                                                                    and income
                                                                    details up
                                                                    to date.
                                                                </p>
                                                            </div>

                                                            <div className="grid gap-4 md:grid-cols-2">
                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="employment_type">
                                                                        Employment
                                                                        type
                                                                    </Label>

                                                                    <Select
                                                                        value={
                                                                            employmentType ||
                                                                            undefined
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) => {
                                                                            setEmploymentType(
                                                                                value,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <SelectTrigger
                                                                            id="employment_type"
                                                                            className={cn(
                                                                                'mt-1 w-full',
                                                                                isFieldMissing(
                                                                                    'employment_type',
                                                                                ) &&
                                                                                    MISSING_FIELD_CLASS,
                                                                            )}
                                                                        >
                                                                            <SelectValue placeholder="Select employment type" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {employmentTypeOptions.map(
                                                                                (
                                                                                    option,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            option
                                                                                        }
                                                                                        value={
                                                                                            option
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            option
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                        </SelectContent>
                                                                    </Select>

                                                                    <input
                                                                        type="hidden"
                                                                        name="employment_type"
                                                                        value={
                                                                            employmentType
                                                                        }
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.employment_type
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="employer_business_name">
                                                                        Employer
                                                                        or
                                                                        business
                                                                        name
                                                                    </Label>

                                                                    <Input
                                                                        id="employer_business_name"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            isFieldMissing(
                                                                                'employer_business_name',
                                                                            ) &&
                                                                                MISSING_FIELD_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            memberApplicationProfile?.employer_business_name ??
                                                                            ''
                                                                        }
                                                                        name="employer_business_name"
                                                                        required={
                                                                            !isPensioner
                                                                        }
                                                                        placeholder="Employer or business name"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.employer_business_name
                                                                        }
                                                                    />
                                                                </div>
                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="employer_business_address3">
                                                                        Province
                                                                    </Label>

                                                                    <LocationCombobox
                                                                        id="employer_business_address3"
                                                                        name="employer_business_address3"
                                                                        search={
                                                                            employerBusinessProvinceSearch
                                                                        }
                                                                        placeholder="Select province"
                                                                        required
                                                                        inputClassName="mt-1 block w-full"
                                                                        loadingMessage="Searching province suggestions..."
                                                                        errorMessage="Province suggestions are temporarily unavailable."
                                                                        promptMessage="Type at least 2 characters to search provinces."
                                                                        onSelect={() => {
                                                                            employerBusinessCitySearch.setSelectedValue(
                                                                                '',
                                                                            );
                                                                            employerBusinessBarangaySearch.setSelectedValue(
                                                                                '',
                                                                            );
                                                                            setEmployerBusinessAddressZipValue(
                                                                                '',
                                                                            );
                                                                        }}
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.employer_business_address3
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="employer_business_address2">
                                                                        City/Municipality
                                                                    </Label>

                                                                    <LocationCombobox
                                                                        id="employer_business_address2"
                                                                        name="employer_business_address2"
                                                                        search={
                                                                            employerBusinessCitySearch
                                                                        }
                                                                        placeholder="Select city or municipality"
                                                                        required
                                                                        disabled={
                                                                            !employerBusinessProvinceSearch.selectedValue
                                                                        }
                                                                        inputClassName="mt-1 block w-full"
                                                                        loadingMessage="Searching city suggestions..."
                                                                        errorMessage="City suggestions are temporarily unavailable."
                                                                        promptMessage="Select a province first."
                                                                        onSelect={(
                                                                            suggestion,
                                                                        ) => {
                                                                            if (
                                                                                suggestion.province
                                                                            ) {
                                                                                employerBusinessProvinceSearch.setSelectedValue(
                                                                                    suggestion.province,
                                                                                );
                                                                            }

                                                                            employerBusinessBarangaySearch.setSelectedValue(
                                                                                '',
                                                                            );
                                                                            void handleEmployerCitySelect(
                                                                                suggestion.code,
                                                                            );
                                                                        }}
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.employer_business_address2
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="employer_business_address_zip">
                                                                        ZIP code
                                                                    </Label>

                                                                    <Input
                                                                        id="employer_business_address_zip"
                                                                        className="mt-1 block w-full"
                                                                        value={
                                                                            employerBusinessAddressZipValue
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            setEmployerBusinessAddressZipValue(
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        name="employer_business_address_zip"
                                                                        inputMode="numeric"
                                                                        autoComplete="postal-code"
                                                                        placeholder="Auto-filled from city"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.employer_business_address_zip
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="employer_business_address_barangay">
                                                                        Barangay
                                                                    </Label>

                                                                    <LocationCombobox
                                                                        id="employer_business_address_barangay"
                                                                        name="employer_business_address_barangay"
                                                                        search={
                                                                            employerBusinessBarangaySearch
                                                                        }
                                                                        placeholder="Select barangay"
                                                                        disabled={
                                                                            !employerBusinessCitySearch.selectedValue
                                                                        }
                                                                        inputClassName={cn(
                                                                            'mt-1 block w-full',
                                                                            isFieldMissing(
                                                                                'employer_business_address_barangay',
                                                                            ) &&
                                                                                MISSING_FIELD_CLASS,
                                                                        )}
                                                                        loadingMessage="Loading barangays..."
                                                                        errorMessage="Barangay suggestions are temporarily unavailable."
                                                                        promptMessage="Select a city or municipality first."
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.employer_business_address_barangay
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2 md:col-span-2">
                                                                    <Label htmlFor="employer_business_address1">
                                                                        Employer/Business
                                                                        address
                                                                        (street)
                                                                    </Label>

                                                                    <Input
                                                                        id="employer_business_address1"
                                                                        className="mt-1 block w-full"
                                                                        defaultValue={
                                                                            employerBusinessAddress1
                                                                        }
                                                                        name="employer_business_address1"
                                                                        required
                                                                        placeholder="Employer or business address"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.employer_business_address1
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="telephone_no">
                                                                        Telephone
                                                                        number
                                                                    </Label>

                                                                    <Input
                                                                        id="telephone_no"
                                                                        type="tel"
                                                                        className="mt-1 block w-full"
                                                                        defaultValue={
                                                                            memberApplicationProfile?.telephone_no ??
                                                                            ''
                                                                        }
                                                                        name="telephone_no"
                                                                        placeholder="Telephone number"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.telephone_no
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="current_position">
                                                                        Current
                                                                        position
                                                                    </Label>

                                                                    <Input
                                                                        id="current_position"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            isCurrentPositionFromWmaster &&
                                                                                WMASTER_VALUE_CLASS,
                                                                            isFieldMissing(
                                                                                'current_position',
                                                                            ) &&
                                                                                MISSING_FIELD_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            resolvedCurrentPosition
                                                                        }
                                                                        name="current_position"
                                                                        required={
                                                                            !isPensioner
                                                                        }
                                                                        placeholder="Current position"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.current_position
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-4 md:col-span-2 md:grid-cols-2">
                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="nature_of_business">
                                                                            Nature
                                                                            of
                                                                            business
                                                                        </Label>

                                                                        <Select
                                                                            value={
                                                                                natureOfBusinessSelection ||
                                                                                undefined
                                                                            }
                                                                            onValueChange={(
                                                                                value,
                                                                            ) => {
                                                                                setNatureOfBusinessSelection(
                                                                                    value,
                                                                                );

                                                                                if (
                                                                                    value !==
                                                                                    NATURE_OF_BUSINESS_OTHER_VALUE
                                                                                ) {
                                                                                    setNatureOfBusinessOther(
                                                                                        '',
                                                                                    );
                                                                                }
                                                                            }}
                                                                        >
                                                                            <SelectTrigger
                                                                                id="nature_of_business"
                                                                                className="mt-1 w-full"
                                                                            >
                                                                                <SelectValue placeholder="Select an industry" />
                                                                            </SelectTrigger>
                                                                            <SelectContent>
                                                                                {NATURE_OF_BUSINESS_OPTIONS.map(
                                                                                    (
                                                                                        option,
                                                                                    ) => (
                                                                                        <SelectItem
                                                                                            key={
                                                                                                option
                                                                                            }
                                                                                            value={
                                                                                                option
                                                                                            }
                                                                                        >
                                                                                            {
                                                                                                option
                                                                                            }
                                                                                        </SelectItem>
                                                                                    ),
                                                                                )}
                                                                            </SelectContent>
                                                                        </Select>

                                                                        <input
                                                                            type="hidden"
                                                                            name="nature_of_business"
                                                                            value={
                                                                                resolvedNatureOfBusiness
                                                                            }
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.nature_of_business
                                                                            }
                                                                        />
                                                                    </div>

                                                                    {natureOfBusinessSelection ===
                                                                        NATURE_OF_BUSINESS_OTHER_VALUE && (
                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="nature_of_business_other">
                                                                                Specify
                                                                                industry
                                                                            </Label>

                                                                            <Input
                                                                                id="nature_of_business_other"
                                                                                className="mt-1 block w-full"
                                                                                value={
                                                                                    natureOfBusinessOther
                                                                                }
                                                                                name="nature_of_business_other"
                                                                                placeholder="Describe your industry"
                                                                                onChange={(
                                                                                    event,
                                                                                ) => {
                                                                                    setNatureOfBusinessOther(
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                    );
                                                                                }}
                                                                            />
                                                                        </div>
                                                                    )}

                                                                    <div className="grid gap-2 md:col-span-2">
                                                                        <Label htmlFor="years_in_work_business">
                                                                            Years
                                                                            in
                                                                            work
                                                                            or
                                                                            business
                                                                        </Label>

                                                                        <Input
                                                                            id="years_in_work_business"
                                                                            className="mt-1 block w-full"
                                                                            defaultValue={
                                                                                memberApplicationProfile?.years_in_work_business ??
                                                                                ''
                                                                            }
                                                                            name="years_in_work_business"
                                                                            placeholder="e.g. 5 years"
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.years_in_work_business
                                                                            }
                                                                        />
                                                                    </div>
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="gross_monthly_income">
                                                                        Gross
                                                                        monthly
                                                                        income
                                                                    </Label>

                                                                    <CurrencyInput
                                                                        id="gross_monthly_income"
                                                                        className={cn(
                                                                            isFieldMissing(
                                                                                'gross_monthly_income',
                                                                            ) &&
                                                                                MISSING_FIELD_CLASS,
                                                                        )}
                                                                        value={
                                                                            grossMonthlyIncome
                                                                        }
                                                                        onValueChange={
                                                                            setGrossMonthlyIncome
                                                                        }
                                                                        required
                                                                    />

                                                                    <input
                                                                        type="hidden"
                                                                        name="gross_monthly_income"
                                                                        value={
                                                                            grossMonthlyIncome
                                                                        }
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.gross_monthly_income
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="payday">
                                                                        Payday
                                                                    </Label>

                                                                    <Select
                                                                        value={
                                                                            paydaySelection ||
                                                                            undefined
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) => {
                                                                            setPaydaySelection(
                                                                                value,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <SelectTrigger
                                                                            id="payday"
                                                                            className={cn(
                                                                                'mt-1 w-full',
                                                                                isFieldMissing(
                                                                                    'payday',
                                                                                ) &&
                                                                                    MISSING_FIELD_CLASS,
                                                                            )}
                                                                        >
                                                                            <SelectValue placeholder="Select payday" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {PAYDAY_OPTIONS.map(
                                                                                (
                                                                                    option,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            option
                                                                                        }
                                                                                        value={
                                                                                            option
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            option
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                        </SelectContent>
                                                                    </Select>

                                                                    <input
                                                                        type="hidden"
                                                                        name="payday"
                                                                        value={
                                                                            paydaySelection
                                                                        }
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.payday
                                                                        }
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </SurfaceCard>
                                                </TabsContent>
                                            )}

                                            {hasMemberAccess && (
                                                <TabsContent
                                                    value="bank"
                                                    forceMount
                                                    className="mt-0"
                                                >
                                                    <SurfaceCard
                                                        variant="muted"
                                                        padding="md"
                                                        className="space-y-6"
                                                    >
                                                        <div className="space-y-6">
                                                            <div className="space-y-1">
                                                                <h3 className="text-base font-semibold">
                                                                    Bank &amp;
                                                                    Payout
                                                                </h3>
                                                                <p className="text-sm text-muted-foreground">
                                                                    Bank name,
                                                                    account
                                                                    name,
                                                                    account
                                                                    number,
                                                                    account
                                                                    type, and
                                                                    release
                                                                    method are
                                                                    required
                                                                    before you
                                                                    can start a
                                                                    loan
                                                                    request.
                                                                </p>
                                                            </div>

                                                            <div className="grid gap-4 md:grid-cols-2">
                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="payout_bank_name">
                                                                        Bank
                                                                        name
                                                                    </Label>

                                                                    <Input
                                                                        id="payout_bank_name"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            isFieldMissing(
                                                                                'payout_bank_name',
                                                                            ) &&
                                                                                MISSING_FIELD_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            memberApplicationProfile?.payout_bank_name ??
                                                                            ''
                                                                        }
                                                                        name="payout_bank_name"
                                                                        placeholder="Bank name"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.payout_bank_name
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="payout_account_name">
                                                                        Account
                                                                        name
                                                                    </Label>

                                                                    <Input
                                                                        id="payout_account_name"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            isFieldMissing(
                                                                                'payout_account_name',
                                                                            ) &&
                                                                                MISSING_FIELD_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            memberApplicationProfile?.payout_account_name ??
                                                                            ''
                                                                        }
                                                                        name="payout_account_name"
                                                                        placeholder="Account name"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.payout_account_name
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="payout_account_number">
                                                                        Account
                                                                        number
                                                                    </Label>

                                                                    <Input
                                                                        id="payout_account_number"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            isFieldMissing(
                                                                                'payout_account_number',
                                                                            ) &&
                                                                                MISSING_FIELD_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            memberApplicationProfile?.payout_account_number ??
                                                                            ''
                                                                        }
                                                                        name="payout_account_number"
                                                                        placeholder="Account number"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.payout_account_number
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="payout_account_type">
                                                                        Account
                                                                        type
                                                                    </Label>

                                                                    <Input
                                                                        id="payout_account_type"
                                                                        className={cn(
                                                                            'mt-1 block w-full',
                                                                            isFieldMissing(
                                                                                'payout_account_type',
                                                                            ) &&
                                                                                MISSING_FIELD_CLASS,
                                                                        )}
                                                                        defaultValue={
                                                                            memberApplicationProfile?.payout_account_type ??
                                                                            ''
                                                                        }
                                                                        name="payout_account_type"
                                                                        placeholder="e.g. Savings"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.payout_account_type
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="release_method">
                                                                        Release
                                                                        method
                                                                    </Label>

                                                                    <Select
                                                                        value={
                                                                            releaseMethod ||
                                                                            undefined
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) => {
                                                                            setReleaseMethod(
                                                                                value,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <SelectTrigger
                                                                            id="release_method"
                                                                            className={cn(
                                                                                'mt-1 w-full',
                                                                                isFieldMissing(
                                                                                    'release_method',
                                                                                ) &&
                                                                                    MISSING_FIELD_CLASS,
                                                                            )}
                                                                        >
                                                                            <SelectValue placeholder="Select release method" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {RELEASE_METHOD_OPTIONS.map(
                                                                                (
                                                                                    option,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            option
                                                                                        }
                                                                                        value={
                                                                                            option
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            option
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                        </SelectContent>
                                                                    </Select>
                                                                    <input
                                                                        type="hidden"
                                                                        name="release_method"
                                                                        value={
                                                                            releaseMethod
                                                                        }
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.release_method
                                                                        }
                                                                    />
                                                                </div>

                                                                {releaseMethod ===
                                                                'ATM' ? (
                                                                    <>
                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="payout_bank_branch">
                                                                                Bank
                                                                                branch
                                                                            </Label>

                                                                            <Input
                                                                                id="payout_bank_branch"
                                                                                className="mt-1 block w-full"
                                                                                defaultValue={
                                                                                    memberApplicationProfile?.payout_bank_branch ??
                                                                                    ''
                                                                                }
                                                                                name="payout_bank_branch"
                                                                                placeholder="Bank branch"
                                                                            />

                                                                            <InputError
                                                                                className="mt-2"
                                                                                message={
                                                                                    formErrors.payout_bank_branch
                                                                                }
                                                                            />
                                                                        </div>

                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="payout_atm_number">
                                                                                ATM
                                                                                card
                                                                                number
                                                                            </Label>

                                                                            <Input
                                                                                id="payout_atm_number"
                                                                                className={cn(
                                                                                    'mt-1 block w-full',
                                                                                    isFieldMissing(
                                                                                        'payout_atm_number',
                                                                                    ) &&
                                                                                        MISSING_FIELD_CLASS,
                                                                                )}
                                                                                defaultValue={
                                                                                    memberApplicationProfile?.payout_atm_number ??
                                                                                    ''
                                                                                }
                                                                                name="payout_atm_number"
                                                                                placeholder="ATM card number"
                                                                            />

                                                                            <InputError
                                                                                className="mt-2"
                                                                                message={
                                                                                    formErrors.payout_atm_number
                                                                                }
                                                                            />
                                                                        </div>

                                                                        <div className="grid gap-2">
                                                                            <Label htmlFor="payout_atm_holder_name">
                                                                                ATM
                                                                                card
                                                                                holder
                                                                                name{' '}
                                                                                <span className="text-muted-foreground">
                                                                                    (if
                                                                                    not
                                                                                    you)
                                                                                </span>
                                                                            </Label>

                                                                            <div className="flex items-center gap-2">
                                                                                <Checkbox
                                                                                    id="payout_atm_holder_name_is_own"
                                                                                    checked={
                                                                                        isOwnAtmCard
                                                                                    }
                                                                                    onCheckedChange={(
                                                                                        checked,
                                                                                    ) => {
                                                                                        const next =
                                                                                            checked ===
                                                                                            true;
                                                                                        setIsOwnAtmCard(
                                                                                            next,
                                                                                        );
                                                                                        setAtmHolderName(
                                                                                            next
                                                                                                ? memberDisplayName
                                                                                                : '',
                                                                                        );
                                                                                    }}
                                                                                />
                                                                                <Label
                                                                                    htmlFor="payout_atm_holder_name_is_own"
                                                                                    className="text-sm font-normal"
                                                                                >
                                                                                    This
                                                                                    is
                                                                                    my
                                                                                    own
                                                                                    ATM
                                                                                    card
                                                                                </Label>
                                                                            </div>

                                                                            {isOwnAtmCard ? (
                                                                                <input
                                                                                    type="hidden"
                                                                                    name="payout_atm_holder_name"
                                                                                    value={
                                                                                        memberDisplayName
                                                                                    }
                                                                                />
                                                                            ) : (
                                                                                <>
                                                                                    <Input
                                                                                        id="payout_atm_holder_name"
                                                                                        className={cn(
                                                                                            'mt-1 block w-full',
                                                                                            isFieldMissing(
                                                                                                'payout_atm_holder_name',
                                                                                            ) &&
                                                                                                MISSING_FIELD_CLASS,
                                                                                        )}
                                                                                        value={
                                                                                            atmHolderName
                                                                                        }
                                                                                        onChange={(
                                                                                            event,
                                                                                        ) =>
                                                                                            setAtmHolderName(
                                                                                                event
                                                                                                    .target
                                                                                                    .value,
                                                                                            )
                                                                                        }
                                                                                        name="payout_atm_holder_name"
                                                                                        placeholder="ATM card holder name"
                                                                                    />

                                                                                    <InputError
                                                                                        className="mt-2"
                                                                                        message={
                                                                                            formErrors.payout_atm_holder_name
                                                                                        }
                                                                                    />
                                                                                </>
                                                                            )}
                                                                        </div>
                                                                    </>
                                                                ) : null}

                                                                {releaseMethod ===
                                                                'Bank Transfer' ? (
                                                                    <>
                                                                        <input
                                                                            type="hidden"
                                                                            name="release_uses_payout_account"
                                                                            value={
                                                                                useSameReleaseAccount
                                                                                    ? '1'
                                                                                    : '0'
                                                                            }
                                                                        />
                                                                        <input
                                                                            type="hidden"
                                                                            name="release_bank_name"
                                                                            value={
                                                                                releaseBankName
                                                                            }
                                                                        />
                                                                        <input
                                                                            type="hidden"
                                                                            name="release_account_name"
                                                                            value={
                                                                                releaseAccountName
                                                                            }
                                                                        />
                                                                        <input
                                                                            type="hidden"
                                                                            name="release_account_number"
                                                                            value={
                                                                                releaseAccountNumber
                                                                            }
                                                                        />
                                                                        <input
                                                                            type="hidden"
                                                                            name="release_account_type"
                                                                            value={
                                                                                releaseAccountType
                                                                            }
                                                                        />
                                                                        <ReleaseAccountFields
                                                                            idPrefix="release_account"
                                                                            useSameAccount={
                                                                                useSameReleaseAccount
                                                                            }
                                                                            onToggleSameAccount={
                                                                                setUseSameReleaseAccount
                                                                            }
                                                                            values={{
                                                                                bank_name:
                                                                                    releaseBankName,
                                                                                account_name:
                                                                                    releaseAccountName,
                                                                                account_number:
                                                                                    releaseAccountNumber,
                                                                                account_type:
                                                                                    releaseAccountType,
                                                                            }}
                                                                            errors={{
                                                                                bank_name:
                                                                                    formErrors.release_bank_name,
                                                                                account_name:
                                                                                    formErrors.release_account_name,
                                                                                account_number:
                                                                                    formErrors.release_account_number,
                                                                                account_type:
                                                                                    formErrors.release_account_type,
                                                                            }}
                                                                            missingFields={{
                                                                                bank_name:
                                                                                    isFieldMissing(
                                                                                        'release_bank_name',
                                                                                    ),
                                                                                account_name:
                                                                                    isFieldMissing(
                                                                                        'release_account_name',
                                                                                    ),
                                                                                account_number:
                                                                                    isFieldMissing(
                                                                                        'release_account_number',
                                                                                    ),
                                                                                account_type:
                                                                                    isFieldMissing(
                                                                                        'release_account_type',
                                                                                    ),
                                                                            }}
                                                                            missingFieldClassName={
                                                                                MISSING_FIELD_CLASS
                                                                            }
                                                                            onChange={(
                                                                                field,
                                                                                value,
                                                                            ) => {
                                                                                if (
                                                                                    field ===
                                                                                    'bank_name'
                                                                                )
                                                                                    setReleaseBankName(
                                                                                        value,
                                                                                    );
                                                                                if (
                                                                                    field ===
                                                                                    'account_name'
                                                                                )
                                                                                    setReleaseAccountName(
                                                                                        value,
                                                                                    );
                                                                                if (
                                                                                    field ===
                                                                                    'account_number'
                                                                                )
                                                                                    setReleaseAccountNumber(
                                                                                        value,
                                                                                    );
                                                                                if (
                                                                                    field ===
                                                                                    'account_type'
                                                                                )
                                                                                    setReleaseAccountType(
                                                                                        value,
                                                                                    );
                                                                            }}
                                                                        />
                                                                    </>
                                                                ) : null}
                                                            </div>
                                                        </div>

                                                        <Separator />

                                                        <div className="space-y-6">
                                                            <div className="space-y-1">
                                                                <h3 className="text-base font-semibold">
                                                                    Source of
                                                                    Funds &amp;
                                                                    Government
                                                                    ID
                                                                </h3>
                                                                <p className="text-sm text-muted-foreground">
                                                                    Required
                                                                    before you
                                                                    can start a
                                                                    loan
                                                                    request.
                                                                </p>
                                                            </div>

                                                            <div className="grid gap-4 md:grid-cols-2">
                                                                <div className="grid gap-2 md:col-span-2">
                                                                    <Label htmlFor="source_of_fund_wealth">
                                                                        Source
                                                                        of fund
                                                                        / wealth
                                                                    </Label>

                                                                    <Input
                                                                        id="source_of_fund_wealth"
                                                                        className="mt-1 block w-full"
                                                                        defaultValue={
                                                                            memberApplicationProfile?.source_of_fund_wealth ??
                                                                            ''
                                                                        }
                                                                        name="source_of_fund_wealth"
                                                                        placeholder="e.g. Salary, business income"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.source_of_fund_wealth
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="id_type">
                                                                        Government
                                                                        ID type
                                                                    </Label>

                                                                    <Select
                                                                        value={
                                                                            idTypeSelection ||
                                                                            undefined
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) => {
                                                                            setIdTypeSelection(
                                                                                value,
                                                                            );

                                                                            if (
                                                                                value !==
                                                                                ID_TYPE_OTHER_VALUE
                                                                            ) {
                                                                                setIdTypeOther(
                                                                                    '',
                                                                                );
                                                                            }
                                                                        }}
                                                                    >
                                                                        <SelectTrigger
                                                                            id="id_type"
                                                                            className="mt-1 w-full"
                                                                        >
                                                                            <SelectValue placeholder="Select ID type" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {ID_TYPE_OPTIONS.map(
                                                                                (
                                                                                    option,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            option
                                                                                        }
                                                                                        value={
                                                                                            option
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            option
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                        </SelectContent>
                                                                    </Select>

                                                                    <input
                                                                        type="hidden"
                                                                        name="id_type"
                                                                        value={
                                                                            idTypeSelection
                                                                        }
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.id_type
                                                                        }
                                                                    />
                                                                </div>

                                                                {idTypeSelection ===
                                                                    ID_TYPE_OTHER_VALUE && (
                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="id_type_other">
                                                                            Specify
                                                                            ID
                                                                            type
                                                                        </Label>

                                                                        <Input
                                                                            id="id_type_other"
                                                                            className="mt-1 block w-full"
                                                                            value={
                                                                                idTypeOther
                                                                            }
                                                                            name="id_type_other"
                                                                            placeholder="Describe your ID type"
                                                                            onChange={(
                                                                                event,
                                                                            ) => {
                                                                                setIdTypeOther(
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                );
                                                                            }}
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.id_type_other
                                                                            }
                                                                        />
                                                                    </div>
                                                                )}

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="id_number">
                                                                        ID
                                                                        number
                                                                    </Label>

                                                                    <Input
                                                                        id="id_number"
                                                                        className="mt-1 block w-full"
                                                                        defaultValue={
                                                                            memberApplicationProfile?.id_number ??
                                                                            ''
                                                                        }
                                                                        name="id_number"
                                                                        placeholder="ID number"
                                                                    />

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.id_number
                                                                        }
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <Separator />

                                                        <div className="space-y-6">
                                                            <div className="space-y-1">
                                                                <h3 className="text-base font-semibold">
                                                                    Physical
                                                                    details
                                                                </h3>
                                                                <p className="text-sm text-muted-foreground">
                                                                    Required for
                                                                    the Generali
                                                                    Health
                                                                    Statement.
                                                                </p>
                                                            </div>

                                                            <div className="grid gap-4 md:grid-cols-2">
                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="height_cm">
                                                                        Height
                                                                    </Label>

                                                                    <div className="relative">
                                                                        <Input
                                                                            id="height_cm"
                                                                            className="mt-1 block w-full pr-10"
                                                                            defaultValue={
                                                                                memberApplicationProfile?.height_cm ??
                                                                                ''
                                                                            }
                                                                            name="height_cm"
                                                                            inputMode="numeric"
                                                                            placeholder="e.g. 165"
                                                                        />

                                                                        <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
                                                                            cm
                                                                        </span>
                                                                    </div>

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.height_cm
                                                                        }
                                                                    />
                                                                </div>

                                                                <div className="grid gap-2">
                                                                    <Label htmlFor="weight_kg">
                                                                        Weight
                                                                    </Label>

                                                                    <div className="relative">
                                                                        <Input
                                                                            id="weight_kg"
                                                                            className="mt-1 block w-full pr-10"
                                                                            defaultValue={
                                                                                memberApplicationProfile?.weight_kg ??
                                                                                ''
                                                                            }
                                                                            name="weight_kg"
                                                                            inputMode="numeric"
                                                                            placeholder="e.g. 65"
                                                                        />

                                                                        <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
                                                                            kg
                                                                        </span>
                                                                    </div>

                                                                    <InputError
                                                                        className="mt-2"
                                                                        message={
                                                                            formErrors.weight_kg
                                                                        }
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </SurfaceCard>
                                                </TabsContent>
                                            )}

                                            {hasMemberAccess && (
                                                <TabsContent
                                                    value="dependents"
                                                    forceMount
                                                    className="mt-0"
                                                >
                                                    <SurfaceCard
                                                        variant="muted"
                                                        padding="md"
                                                        className="space-y-6"
                                                    >
                                                        <div className="space-y-6">
                                                            <div className="space-y-1">
                                                                <h3 className="text-base font-semibold">
                                                                    Dependents
                                                                </h3>
                                                                <p className="text-sm text-muted-foreground">
                                                                    Keep your
                                                                    dependents'
                                                                    names and
                                                                    birthdates
                                                                    up to date.
                                                                    These are
                                                                    used to
                                                                    pre-fill
                                                                    future loan
                                                                    requests.
                                                                    Changes here
                                                                    save
                                                                    immediately.
                                                                </p>
                                                            </div>

                                                            {DEPENDENT_CATEGORIES.filter(
                                                                (category) => {
                                                                    if (
                                                                        category.key ===
                                                                        'child'
                                                                    ) {
                                                                        return (
                                                                            memberCivilStatus ===
                                                                            'Married'
                                                                        );
                                                                    }

                                                                    if (
                                                                        category.key ===
                                                                            'sibling' ||
                                                                        category.key ===
                                                                            'parent'
                                                                    ) {
                                                                        return (
                                                                            memberCivilStatus ===
                                                                            'Single'
                                                                        );
                                                                    }

                                                                    return true;
                                                                },
                                                            ).map(
                                                                (category) => (
                                                                    <DependentCategorySection
                                                                        key={
                                                                            category.key
                                                                        }
                                                                        category={
                                                                            category
                                                                        }
                                                                        values={
                                                                            dependentsValues
                                                                        }
                                                                        errors={
                                                                            formErrors
                                                                        }
                                                                        withNameAttribute
                                                                        showCycleFields={
                                                                            false
                                                                        }
                                                                        onChange={
                                                                            handleDependentsChange
                                                                        }
                                                                    />
                                                                ),
                                                            )}
                                                        </div>
                                                    </SurfaceCard>
                                                </TabsContent>
                                            )}
                                        </Tabs>

                                        <div className="flex flex-col gap-3 border-t border-border/40 pt-6 sm:flex-row sm:items-center sm:justify-end">
                                            <div className="flex flex-wrap items-center gap-3">
                                                {showStepperNav && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        disabled={
                                                            processing ||
                                                            !previousTab
                                                        }
                                                        onClick={() => {
                                                            if (!previousTab) {
                                                                return;
                                                            }

                                                            setActiveTab(
                                                                previousTab,
                                                            );
                                                        }}
                                                    >
                                                        Previous
                                                    </Button>
                                                )}

                                                {showStepperNav && nextTab && (
                                                    <Button
                                                        type="button"
                                                        variant={
                                                            onboarding
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                        disabled={processing}
                                                        onClick={() => {
                                                            setActiveTab(
                                                                nextTab,
                                                            );
                                                        }}
                                                    >
                                                        Next
                                                    </Button>
                                                )}

                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    data-test="update-profile-button"
                                                    variant={
                                                        onboarding && nextTab
                                                            ? 'secondary'
                                                            : 'default'
                                                    }
                                                >
                                                    Save
                                                </Button>

                                                <Transition
                                                    show={recentlySuccessful}
                                                    enter="transition ease-in-out"
                                                    enterFrom="opacity-0"
                                                    leave="transition ease-in-out"
                                                    leaveTo="opacity-0"
                                                >
                                                    <p className="text-sm text-neutral-600">
                                                        Saved
                                                    </p>
                                                </Transition>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </div>

                        {!hasMemberAccess && (
                            <div className="space-y-6">
                                <Separator />

                                <div className="space-y-1">
                                    <h3 className="text-base font-semibold tracking-tight">
                                        Link your WIBS membership
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Already a WIBS member? Enter your WIBS
                                        account number to link your membership
                                        and gain member portal access. Your
                                        account number is created when you
                                        register as a member at the WIBS office
                                        — if you don&apos;t have one yet, set up
                                        your membership there first.
                                    </p>
                                </div>

                                <Form
                                    {...LinkMembershipController.store.form()}
                                    options={{ preserveScroll: true }}
                                    onSuccess={() => {
                                        showSuccessToast(
                                            'Membership linked — you now have member portal access.',
                                            { id: 'link-membership' },
                                        );
                                    }}
                                    onError={(formErrors) => {
                                        showErrorToast(
                                            formErrors,
                                            adminToastCopy.error.updated(
                                                'Membership link',
                                            ),
                                            { id: 'link-membership' },
                                        );
                                    }}
                                    className="space-y-4"
                                >
                                    {({ processing, errors: linkErrors }) => (
                                        <>
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="grid gap-2 md:col-span-2">
                                                    <Label htmlFor="link_accntno">
                                                        WIBS account number
                                                    </Label>
                                                    <Input
                                                        id="link_accntno"
                                                        name="accntno"
                                                        className="mt-1 block w-full"
                                                        placeholder="e.g. 003001"
                                                        autoComplete="off"
                                                    />
                                                    <InputError
                                                        className="mt-2"
                                                        message={
                                                            linkErrors.accntno
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="link_last_name">
                                                        Last name
                                                    </Label>
                                                    <Input
                                                        id="link_last_name"
                                                        name="last_name"
                                                        className="mt-1 block w-full"
                                                        placeholder="Last name"
                                                        autoComplete="family-name"
                                                    />
                                                    <InputError
                                                        className="mt-2"
                                                        message={
                                                            linkErrors.last_name
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="link_first_name">
                                                        First name
                                                    </Label>
                                                    <Input
                                                        id="link_first_name"
                                                        name="first_name"
                                                        className="mt-1 block w-full"
                                                        placeholder="First name"
                                                        autoComplete="given-name"
                                                    />
                                                    <InputError
                                                        className="mt-2"
                                                        message={
                                                            linkErrors.first_name
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="link_middle_initial">
                                                        Middle initial{' '}
                                                        <span className="text-muted-foreground">
                                                            (optional)
                                                        </span>
                                                    </Label>
                                                    <Input
                                                        id="link_middle_initial"
                                                        name="middle_initial"
                                                        className="mt-1 block w-full"
                                                        placeholder="M"
                                                        maxLength={1}
                                                        autoComplete="off"
                                                    />
                                                    <InputError
                                                        className="mt-2"
                                                        message={
                                                            linkErrors.middle_initial
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Link membership
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </div>
                        )}

                        <ProfileImageCropModal
                            isOpen={showProfilePhotoCropModal}
                            onClose={handleProfilePhotoCropClose}
                            onSave={handleProfilePhotoCropSave}
                            imagePreviewUrl={profilePhotoDraftPreview}
                        />
                    </section>
                </SurfaceCard>
            </SettingsLayout>
        </AppLayout>
    );
}
