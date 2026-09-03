import { Transition } from '@headlessui/react';
import { Form, Head, usePage } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import LinkMembershipController from '@/actions/App/Http/Controllers/Settings/LinkMembershipController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import type { DependentValues } from '@/components/dependents/dependent-category-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import ProfileImageCropModal, {
    type ProfileImageCropResult,
} from '@/components/profile/profile-image-crop-modal';
import { SurfaceCard } from '@/components/surface-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useInitials } from '@/hooks/use-initials';
import { useLocationSearch } from '@/hooks/use-location-search';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import api from '@/lib/api';
import { createCroppedImageFile } from '@/lib/image-crop';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import { barangays, cities, provinces, zip } from '@/routes/api/locations';
import {
    breadcrumbs,
    calculateAge,
    EDUCATIONAL_ATTAINMENT_OPTIONS,
    EMPLOYMENT_TYPE_OPTIONS,
    findFirstInvalidField,
    findFirstTabWithErrors,
    focusInvalidField,
    hasWmasterValue,
    isPensionerType,
    isSelfEmployedType,
    NATURE_OF_BUSINESS_OPTIONS,
    NATURE_OF_BUSINESS_OTHER_VALUE,
    normalizeCivilStatusValue,
    normalizePaydayValue,
    PROFILE_PHOTO_ALLOWED_TYPES,
    PROFILE_PHOTO_MAX_BYTES,
    PROFILE_PHOTO_OUTPUT_QUALITY,
    PROFILE_PHOTO_OUTPUT_SIZE,
    PROFILE_TAB_ORDER,
    SPOUSE_NOT_APPLICABLE_STATUSES,
    tabForField,
    type ProfileTab,
    type Props,
} from './profile-shared';
import { AccountTab } from './profile-tabs/account-tab';
import { BankTab } from './profile-tabs/bank-tab';
import { DependentsTab } from './profile-tabs/dependents-tab';
import { PersonalTab } from './profile-tabs/personal-tab';
import { WorkTab } from './profile-tabs/work-tab';

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
    const initialBirthplaceBarangay =
        memberApplicationProfile?.birthplace_barangay?.trim() ?? '';
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
    const birthplaceBarangaySearch = useLocationSearch({
        initialQuery: initialBirthplaceBarangay,
        searchUrl: barangays.url(),
        params: {
            municipality: birthplaceCitySearch.selectedValue || undefined,
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
    const [lengthOfStay, setLengthOfStay] = useState<string>(
        memberApplicationProfile?.length_of_stay ?? '',
    );
    const [spouseBirthdateValue, setSpouseBirthdateValue] = useState<string>(
        memberApplicationProfile?.spouse_birthdate ?? '',
    );
    const spouseAge = calculateAge(spouseBirthdateValue || null);
    const effectiveCivilStatus = normalizeCivilStatusValue(
        isCivilStatusLocked ? memberCivilStatus : civilStatusValue,
    );
    const spouseFieldsHidden =
        SPOUSE_NOT_APPLICABLE_STATUSES.includes(effectiveCivilStatus);
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
    const isPensioner = isPensionerType(employmentType);
    const isSelfEmployed = isSelfEmployedType(employmentType);
    const showDateEmployed = !isPensioner && !isSelfEmployed;
    const [employerDateEmployed, setEmployerDateEmployed] = useState<string>(
        memberApplicationProfile?.employer_date_employed ?? '',
    );
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
    const [institutionalEmployerCategory, setInstitutionalEmployerCategory] =
        useState<string>(
            memberApplicationProfile?.institutional_employer_category ?? '',
        );
    const [yearsInWorkBusiness, setYearsInWorkBusiness] = useState<string>(
        memberApplicationProfile?.years_in_work_business ?? '',
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
    const [releaseAccountId, setReleaseAccountId] = useState<number | null>(
        memberApplicationProfile?.release_saved_account_id ?? null,
    );
    const [paymentOption, setPaymentOption] = useState<string>(
        memberApplicationProfile?.payment_option ?? '',
    );
    const [paymentAccountId, setPaymentAccountId] = useState<number | null>(
        memberApplicationProfile?.payment_saved_account_id ?? null,
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

                            {status === 'loan-prerequisites-incomplete' && (
                                <Alert variant="destructive">
                                    <AlertTitle>
                                        Finish your Work details first
                                    </AlertTitle>
                                    <AlertDescription>
                                        Select your Institutional Employer
                                        Category below before starting a loan
                                        request.
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
                                                                Release Method
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

                                            <AccountTab
                                                formErrors={formErrors}
                                                adminProfile={adminProfile}
                                                auth={auth}
                                                displayName={displayName}
                                                getInitials={getInitials}
                                                profilePhotoUrl={
                                                    profilePhotoUrl
                                                }
                                                profilePhotoInputRef={
                                                    profilePhotoInputRef
                                                }
                                                handleProfilePhotoChange={
                                                    handleProfilePhotoChange
                                                }
                                                mustVerifyEmail={
                                                    mustVerifyEmail
                                                }
                                                status={status}
                                            />

                                            {hasMemberAccess && (
                                                <PersonalTab
                                                    formErrors={formErrors}
                                                    memberRecord={memberRecord}
                                                    memberApplicationProfile={
                                                        memberApplicationProfile
                                                    }
                                                    isFieldMissing={
                                                        isFieldMissing
                                                    }
                                                    hasStructuredName={
                                                        hasStructuredName
                                                    }
                                                    memberFirstName={
                                                        memberFirstName
                                                    }
                                                    memberLastName={
                                                        memberLastName
                                                    }
                                                    memberMiddleName={
                                                        memberMiddleName
                                                    }
                                                    memberDisplayName={
                                                        memberDisplayName
                                                    }
                                                    memberAge={memberAge}
                                                    memberCivilStatus={
                                                        memberCivilStatus
                                                    }
                                                    isCivilStatusLocked={
                                                        isCivilStatusLocked
                                                    }
                                                    isHousingStatusLocked={
                                                        isHousingStatusLocked
                                                    }
                                                    isSpouseNameLocked={
                                                        isSpouseNameLocked
                                                    }
                                                    spouseFieldsHidden={
                                                        spouseFieldsHidden
                                                    }
                                                    numberOfChildrenValue={
                                                        numberOfChildrenValue
                                                    }
                                                    birthplaceProvinceSearch={
                                                        birthplaceProvinceSearch
                                                    }
                                                    birthplaceCitySearch={
                                                        birthplaceCitySearch
                                                    }
                                                    birthplaceBarangaySearch={
                                                        birthplaceBarangaySearch
                                                    }
                                                    homeProvinceSearch={
                                                        homeProvinceSearch
                                                    }
                                                    homeCitySearch={
                                                        homeCitySearch
                                                    }
                                                    homeBarangaySearch={
                                                        homeBarangaySearch
                                                    }
                                                    homeAddress1={homeAddress1}
                                                    homeAddress2RawHint={
                                                        homeAddress2RawHint
                                                    }
                                                    homeAddress3RawHint={
                                                        homeAddress3RawHint
                                                    }
                                                    homeAddressBarangayRawHint={
                                                        homeAddressBarangayRawHint
                                                    }
                                                    homeAddressZipValue={
                                                        homeAddressZipValue
                                                    }
                                                    setHomeAddressZipValue={
                                                        setHomeAddressZipValue
                                                    }
                                                    handleHomeCitySelect={
                                                        handleHomeCitySelect
                                                    }
                                                    civilStatusValue={
                                                        civilStatusValue
                                                    }
                                                    setCivilStatusValue={
                                                        setCivilStatusValue
                                                    }
                                                    housingStatusValue={
                                                        housingStatusValue
                                                    }
                                                    setHousingStatusValue={
                                                        setHousingStatusValue
                                                    }
                                                    lengthOfStay={lengthOfStay}
                                                    setLengthOfStay={
                                                        setLengthOfStay
                                                    }
                                                    spouseBirthdateValue={
                                                        spouseBirthdateValue
                                                    }
                                                    setSpouseBirthdateValue={
                                                        setSpouseBirthdateValue
                                                    }
                                                    spouseAge={spouseAge}
                                                    educationalAttainment={
                                                        educationalAttainment
                                                    }
                                                    setEducationalAttainment={
                                                        setEducationalAttainment
                                                    }
                                                    educationalAttainmentOptions={
                                                        educationalAttainmentOptions
                                                    }
                                                />
                                            )}

                                            {hasMemberAccess && (
                                                <WorkTab
                                                    formErrors={formErrors}
                                                    memberApplicationProfile={
                                                        memberApplicationProfile
                                                    }
                                                    isFieldMissing={
                                                        isFieldMissing
                                                    }
                                                    employmentType={
                                                        employmentType
                                                    }
                                                    setEmploymentType={
                                                        setEmploymentType
                                                    }
                                                    employmentTypeOptions={
                                                        employmentTypeOptions
                                                    }
                                                    isPensioner={isPensioner}
                                                    showDateEmployed={
                                                        showDateEmployed
                                                    }
                                                    employerDateEmployed={
                                                        employerDateEmployed
                                                    }
                                                    setEmployerDateEmployed={
                                                        setEmployerDateEmployed
                                                    }
                                                    isCurrentPositionFromWmaster={
                                                        isCurrentPositionFromWmaster
                                                    }
                                                    resolvedCurrentPosition={
                                                        resolvedCurrentPosition
                                                    }
                                                    employerBusinessAddress1={
                                                        employerBusinessAddress1
                                                    }
                                                    employerBusinessProvinceSearch={
                                                        employerBusinessProvinceSearch
                                                    }
                                                    employerBusinessCitySearch={
                                                        employerBusinessCitySearch
                                                    }
                                                    employerBusinessBarangaySearch={
                                                        employerBusinessBarangaySearch
                                                    }
                                                    employerBusinessAddressZipValue={
                                                        employerBusinessAddressZipValue
                                                    }
                                                    setEmployerBusinessAddressZipValue={
                                                        setEmployerBusinessAddressZipValue
                                                    }
                                                    handleEmployerCitySelect={
                                                        handleEmployerCitySelect
                                                    }
                                                    natureOfBusinessSelection={
                                                        natureOfBusinessSelection
                                                    }
                                                    setNatureOfBusinessSelection={
                                                        setNatureOfBusinessSelection
                                                    }
                                                    natureOfBusinessOther={
                                                        natureOfBusinessOther
                                                    }
                                                    setNatureOfBusinessOther={
                                                        setNatureOfBusinessOther
                                                    }
                                                    resolvedNatureOfBusiness={
                                                        resolvedNatureOfBusiness
                                                    }
                                                    institutionalEmployerCategory={
                                                        institutionalEmployerCategory
                                                    }
                                                    setInstitutionalEmployerCategory={
                                                        setInstitutionalEmployerCategory
                                                    }
                                                    yearsInWorkBusiness={
                                                        yearsInWorkBusiness
                                                    }
                                                    setYearsInWorkBusiness={
                                                        setYearsInWorkBusiness
                                                    }
                                                    grossMonthlyIncome={
                                                        grossMonthlyIncome
                                                    }
                                                    setGrossMonthlyIncome={
                                                        setGrossMonthlyIncome
                                                    }
                                                    paydaySelection={
                                                        paydaySelection
                                                    }
                                                    setPaydaySelection={
                                                        setPaydaySelection
                                                    }
                                                />
                                            )}

                                            {hasMemberAccess && (
                                                <BankTab
                                                    formErrors={formErrors}
                                                    memberApplicationProfile={
                                                        memberApplicationProfile
                                                    }
                                                    isFieldMissing={
                                                        isFieldMissing
                                                    }
                                                    releaseMethod={
                                                        releaseMethod
                                                    }
                                                    setReleaseMethod={
                                                        setReleaseMethod
                                                    }
                                                    releaseAccountId={
                                                        releaseAccountId
                                                    }
                                                    setReleaseAccountId={
                                                        setReleaseAccountId
                                                    }
                                                    idTypeSelection={
                                                        idTypeSelection
                                                    }
                                                    setIdTypeSelection={
                                                        setIdTypeSelection
                                                    }
                                                    idTypeOther={idTypeOther}
                                                    setIdTypeOther={
                                                        setIdTypeOther
                                                    }
                                                    paymentOption={
                                                        paymentOption
                                                    }
                                                    setPaymentOption={
                                                        setPaymentOption
                                                    }
                                                    paymentAccountId={
                                                        paymentAccountId
                                                    }
                                                    setPaymentAccountId={
                                                        setPaymentAccountId
                                                    }
                                                />
                                            )}

                                            {hasMemberAccess && (
                                                <DependentsTab
                                                    formErrors={formErrors}
                                                    memberCivilStatus={
                                                        memberCivilStatus
                                                    }
                                                    dependentsValues={
                                                        dependentsValues
                                                    }
                                                    handleDependentsChange={
                                                        handleDependentsChange
                                                    }
                                                />
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
