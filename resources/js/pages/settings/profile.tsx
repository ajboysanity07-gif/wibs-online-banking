import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Camera, ShieldBan, ShieldCheck } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { NumericFormat } from 'react-number-format';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import AppearanceTabs from '@/components/appearance-tabs';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { LocationAutocompleteInput } from '@/components/location-autocomplete-input';
import ProfileImageCropModal, {
    type ProfileImageCropResult,
} from '@/components/profile/profile-image-crop-modal';
import { SurfaceCard } from '@/components/surface-card';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { createCroppedImageFile } from '@/lib/image-crop';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { cities, provinces } from '@/routes/api/locations';
import { edit } from '@/routes/profile';
import { disable, enable } from '@/routes/two-factor';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

type SettingsTab = 'profile' | 'password' | 'two-factor' | 'appearance';

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
    address2: string | null;
    address3: string | null;
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
    number_of_children: number | null;
    spouse_name: string | null;
    educational_attainment: string | null;
    spouse_age: number | null;
    spouse_cell_no: string | null;
    employment_type: string | null;
    employer_business_name: string | null;
    employer_business_address: string | null;
    employer_business_address1: string | null;
    employer_business_address2: string | null;
    employer_business_address3: string | null;
    telephone_no: string | null;
    current_position: string | null;
    nature_of_business: string | null;
    years_in_work_business: string | null;
    gross_monthly_income: string | null;
    payday: string | null;
    profile_completed_at: string | null;
};

type ProfileCompletion = {
    isComplete: boolean;
    completedAt: string | null;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    adminProfile?: AdminProfileSummary | null;
    memberRecord?: MemberRecord | null;
    memberApplicationProfile?: MemberApplicationProfileData | null;
    profileCompletion?: ProfileCompletion | null;
    onboarding?: boolean;
    initialTab?: SettingsTab;
    twoFactorEnabled?: boolean;
    requiresConfirmation?: boolean;
    twoFactorAvailable?: boolean;
};

const tabContentClasses =
    'data-[state=active]:animate-in data-[state=active]:fade-in-0 data-[state=active]:slide-in-from-bottom-2 data-[state=active]:duration-300';
const WMASTER_VALUE_CLASS = 'border-ring/40 ring-1 ring-ring/20';

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
const EMPLOYMENT_TYPE_OPTIONS = ['Regular', 'Contract', 'Self-Employed'];
const CIVIL_STATUS_OPTIONS = [
    'Single',
    'Married',
    'Separated',
    'Widowed',
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

export default function Profile({
    mustVerifyEmail,
    status,
    adminProfile = null,
    memberRecord = null,
    memberApplicationProfile = null,
    profileCompletion = null,
    onboarding = false,
    initialTab = 'profile',
    twoFactorEnabled = false,
    requiresConfirmation = false,
    twoFactorAvailable = true,
}: Props) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const profilePhotoInputRef = useRef<HTMLInputElement>(null);
    const profilePhotoDraftFileRef = useRef<File | null>(null);
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const [activeTab, setActiveTab] = useState<SettingsTab>(initialTab);
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
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
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
        structuredMemberName ||
        memberRecord?.bname?.trim() ||
        '';
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
    const memberDisplayAddress =
        memberRecord?.display_address?.trim() ||
        memberRecord?.address?.trim() ||
        '';
    const memberCivilStatus = normalizeCivilStatusValue(
        memberRecord?.civilstat ?? '',
    );
    const numberOfChildrenValue =
        memberApplicationProfile?.number_of_children ??
        memberRecord?.number_of_children ??
        '';
    const isBirthplaceLocked = hasWmasterValue(memberBirthplace);
    const isSpouseNameLocked = hasWmasterValue(memberRecord?.spouse_name);
    const isProfileComplete = Boolean(profileCompletion?.isComplete);
    const showOnboardingAlert =
        onboarding && adminProfile === null && !isProfileComplete;
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
    });
    const employerBusinessAddress1 =
        memberApplicationProfile?.employer_business_address1?.trim() ?? '';
    const employerBusinessAddress2 =
        memberApplicationProfile?.employer_business_address2?.trim() ?? '';
    const employerBusinessAddress3 =
        memberApplicationProfile?.employer_business_address3?.trim() ?? '';
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
    });
    const [educationalAttainment, setEducationalAttainment] = useState<string>(
        memberApplicationProfile?.educational_attainment?.trim() ?? '',
    );
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
    const resolvedNatureOfBusiness =
        natureOfBusinessSelection === NATURE_OF_BUSINESS_OTHER_VALUE
            ? natureOfBusinessOther.trim()
            : natureOfBusinessSelection;

    useEffect(() => {
        setActiveTab(initialTab);
    }, [initialTab]);

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

    const settingsTabs = [
        { value: 'profile', label: 'Profile' },
        { value: 'password', label: 'Password' },
        {
            value: 'two-factor',
            label: 'Two-Factor Auth',
            disabled: !twoFactorAvailable,
        },
        { value: 'appearance', label: 'Appearance' },
    ] satisfies Array<{
        value: SettingsTab;
        label: string;
        disabled?: boolean;
    }>;

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
                <SurfaceCard variant="default" padding="lg" className="space-y-6">
                    <Tabs
                        value={activeTab}
                        onValueChange={(value) =>
                            setActiveTab(value as SettingsTab)
                        }
                        orientation="vertical"
                        className="flex flex-col lg:flex-row lg:space-x-12"
                    >
                        <aside className="w-full max-w-xl lg:w-48">
                            <nav aria-label="Settings">
                                <TabsList className="flex w-full flex-col gap-1 bg-transparent p-0">
                                    {settingsTabs.map((tab) => (
                                        <TabsTrigger
                                            key={tab.value}
                                            value={tab.value}
                                            disabled={tab.disabled}
                                            className="w-full justify-start rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-all hover:bg-muted/70 hover:text-foreground data-[state=active]:bg-muted data-[state=active]:text-foreground data-[state=active]:shadow-sm"
                                        >
                                            {tab.label}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </nav>
                        </aside>

                        <Separator className="my-6 lg:hidden" />

                        <div className="flex-1 md:max-w-3xl">
                            <TabsContent
                                value="profile"
                                forceMount
                                className={tabContentClasses}
                            >
                                <section className="max-w-3xl space-y-12">
                                    <div className="space-y-6">
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <Heading
                                                variant="small"
                                                title="Profile information"
                                                description="Update your profile details, photo, and contact information"
                                            />
                                            {adminProfile === null && (
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
                                                    Complete your profile to
                                                    continue
                                                </AlertTitle>
                                                <AlertDescription>
                                                    Add the personal and work
                                                    details below to unlock your
                                                    client dashboard.
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
                                                    adminToastCopy.error.updated(
                                                        'Profile',
                                                    ),
                                                    { id: 'profile-update' },
                                                );
                                            }}
                                            encType="multipart/form-data"
                                            className="space-y-8"
                                        >
                                            {({
                                                processing,
                                                recentlySuccessful,
                                                errors: formErrors,
                                            }) => (
                                                <>
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
                                                                        auth.user
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

                                                    {adminProfile === null && (
                                                        <>
                                                            <Separator />

                                                            <div className="space-y-6">
                                                                <div className="space-y-1">
                                                                    <h3 className="text-base font-semibold">
                                                                        Personal
                                                                        Data
                                                                    </h3>
                                                                    <p className="text-sm text-muted-foreground">
                                                                        Review
                                                                        your
                                                                        verified
                                                                        member
                                                                        details
                                                                        and keep
                                                                        your
                                                                        application
                                                                        profile
                                                                        updated.
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Some
                                                                        fields
                                                                        below
                                                                        are
                                                                        read-only
                                                                        from
                                                                        your
                                                                        verified
                                                                        member
                                                                        record.
                                                                    </p>
                                                                </div>

                                                                {!memberRecord && (
                                                                    <p className="text-sm text-muted-foreground">
                                                                        No
                                                                        member
                                                                        record
                                                                        was
                                                                        found
                                                                        for this
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
                                                                                <Label htmlFor="birthplace_city">
                                                                                    Birthplace
                                                                                    city/municipality
                                                                                </Label>
                                                                                <LocationAutocompleteInput
                                                                                    id="birthplace_city"
                                                                                    name="birthplace_city"
                                                                                    search={
                                                                                        birthplaceCitySearch
                                                                                    }
                                                                                    placeholder="Select city or municipality"
                                                                                    required
                                                                                    inputClassName="mt-1 block w-full"
                                                                                    loadingMessage="Searching city suggestions..."
                                                                                    errorMessage="City suggestions are temporarily unavailable."
                                                                                />

                                                                                <InputError
                                                                                    className="mt-2"
                                                                                    message={
                                                                                        formErrors.birthplace_city
                                                                                    }
                                                                                />
                                                                            </div>
                                                                            <div className="grid gap-2">
                                                                                <Label htmlFor="birthplace_province">
                                                                                    Birthplace
                                                                                    province
                                                                                </Label>
                                                                                <LocationAutocompleteInput
                                                                                    id="birthplace_province"
                                                                                    name="birthplace_province"
                                                                                    search={
                                                                                        birthplaceProvinceSearch
                                                                                    }
                                                                                    placeholder="Select province"
                                                                                    inputClassName="mt-1 block w-full"
                                                                                    loadingMessage="Searching province suggestions..."
                                                                                    errorMessage="Province suggestions are temporarily unavailable."
                                                                                    promptMessage="Type at least 2 characters to search provinces."
                                                                                />

                                                                                <InputError
                                                                                    className="mt-2"
                                                                                    message={
                                                                                        formErrors.birthplace_province
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

                                                                    {memberAddressStreet !== '' ||
                                                                    memberAddressCity !==
                                                                        '' ||
                                                                    memberAddressProvince !==
                                                                        '' ? (
                                                                        <div className="grid gap-4 md:col-span-3 md:grid-cols-2">
                                                                            <div className="grid gap-2 md:col-span-2">
                                                                                <Label htmlFor="member_record_address1">
                                                                                    Address
                                                                                    (street)
                                                                                </Label>

                                                                                <Input
                                                                                    id="member_record_address1"
                                                                                    className={cn(
                                                                                        'mt-1 block w-full',
                                                                                        hasWmasterValue(
                                                                                            memberAddressStreet,
                                                                                        ) &&
                                                                                            WMASTER_VALUE_CLASS,
                                                                                    )}
                                                                                    defaultValue={
                                                                                        memberAddressStreet
                                                                                    }
                                                                                    placeholder="Not available"
                                                                                    disabled
                                                                                />
                                                                            </div>

                                                                            <div className="grid gap-2">
                                                                                <Label htmlFor="member_record_address2">
                                                                                    City/Municipality
                                                                                </Label>

                                                                                <Input
                                                                                    id="member_record_address2"
                                                                                    className={cn(
                                                                                        'mt-1 block w-full',
                                                                                        hasWmasterValue(
                                                                                            memberAddressCity,
                                                                                        ) &&
                                                                                            WMASTER_VALUE_CLASS,
                                                                                    )}
                                                                                    defaultValue={
                                                                                        memberAddressCity
                                                                                    }
                                                                                    placeholder="Not available"
                                                                                    disabled
                                                                                />
                                                                            </div>

                                                                            <div className="grid gap-2">
                                                                                <Label htmlFor="member_record_address3">
                                                                                    Province
                                                                                </Label>

                                                                                <Input
                                                                                    id="member_record_address3"
                                                                                    className={cn(
                                                                                        'mt-1 block w-full',
                                                                                        hasWmasterValue(
                                                                                            memberAddressProvince,
                                                                                        ) &&
                                                                                            WMASTER_VALUE_CLASS,
                                                                                    )}
                                                                                    defaultValue={
                                                                                        memberAddressProvince
                                                                                    }
                                                                                    placeholder="Not available"
                                                                                    disabled
                                                                                />
                                                                            </div>
                                                                        </div>
                                                                    ) : (
                                                                        <div className="grid gap-2 md:col-span-3">
                                                                            <Label htmlFor="member_record_address">
                                                                                Address
                                                                            </Label>

                                                                            <Input
                                                                                id="member_record_address"
                                                                                className={cn(
                                                                                    'mt-1 block w-full',
                                                                                    hasWmasterValue(
                                                                                        memberDisplayAddress,
                                                                                    ) &&
                                                                                        WMASTER_VALUE_CLASS,
                                                                                )}
                                                                                defaultValue={
                                                                                    memberDisplayAddress
                                                                                }
                                                                                placeholder="Not available"
                                                                                disabled
                                                                            />
                                                                        </div>
                                                                    )}

                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="length_of_stay">
                                                                            Length
                                                                            of
                                                                            stay
                                                                        </Label>

                                                                        <Input
                                                                            id="length_of_stay"
                                                                            className="mt-1 block w-full"
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
                                                                        <Label htmlFor="member_housing_status">
                                                                            Housing
                                                                            status
                                                                        </Label>

                                                                        <Input
                                                                            id="member_housing_status"
                                                                            className={cn(
                                                                                'mt-1 block w-full',
                                                                                hasWmasterValue(
                                                                                    memberRecord?.housing_status,
                                                                                ) &&
                                                                                    WMASTER_VALUE_CLASS,
                                                                            )}
                                                                            defaultValue={
                                                                                memberRecord?.housing_status ??
                                                                                ''
                                                                            }
                                                                            placeholder="Not available"
                                                                            disabled
                                                                        />
                                                                    </div>

                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="member_civil_status">
                                                                            Civil
                                                                            status
                                                                        </Label>

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
                                                                                    hasWmasterValue(
                                                                                        memberCivilStatus,
                                                                                    ) &&
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
                                                                                className="mt-1 w-full"
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
                                                                            No.
                                                                            of
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
                                                                                    className="mt-1 block w-full"
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
                                                                        <Label htmlFor="spouse_age">
                                                                            Spouse
                                                                            age
                                                                        </Label>

                                                                        <Input
                                                                            id="spouse_age"
                                                                            type="number"
                                                                            className="mt-1 block w-full"
                                                                            defaultValue={
                                                                                memberApplicationProfile?.spouse_age ??
                                                                                ''
                                                                            }
                                                                            name="spouse_age"
                                                                            inputMode="numeric"
                                                                            min={
                                                                                0
                                                                            }
                                                                            placeholder="Age"
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.spouse_age
                                                                            }
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
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.spouse_cell_no
                                                                            }
                                                                        />
                                                                    </div>

                                                                </div>
                                                            </div>

                                                            <Separator />

                                                            <div className="space-y-6">
                                                                <div className="space-y-1">
                                                                    <h3 className="text-base font-semibold">
                                                                        Work
                                                                        &amp;
                                                                        Finances
                                                                    </h3>
                                                                    <p className="text-sm text-muted-foreground">
                                                                        Keep
                                                                        your
                                                                        employment
                                                                        and
                                                                        income
                                                                        details
                                                                        up to
                                                                        date.
                                                                    </p>
                                                                </div>

                                                                <div className="grid gap-4 md:grid-cols-2">
                                                                    <div className="grid gap-2 md:col-span-2">
                                                                        <Label htmlFor="member_occupation">
                                                                            Occupation
                                                                        </Label>

                                                                        <Input
                                                                            id="member_occupation"
                                                                            className={cn(
                                                                                'mt-1 block w-full',
                                                                                hasWmasterValue(
                                                                                    memberRecord?.occupation,
                                                                                ) &&
                                                                                    WMASTER_VALUE_CLASS,
                                                                            )}
                                                                            defaultValue={
                                                                                memberRecord?.occupation ??
                                                                                ''
                                                                            }
                                                                            placeholder="Not available"
                                                                            disabled
                                                                        />
                                                                    </div>
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
                                                                                className="mt-1 w-full"
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
                                                                            className="mt-1 block w-full"
                                                                            defaultValue={
                                                                                memberApplicationProfile?.employer_business_name ??
                                                                                ''
                                                                            }
                                                                            name="employer_business_name"
                                                                            required
                                                                            placeholder="Employer or business name"
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.employer_business_name
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
                                                                        <Label htmlFor="employer_business_address2">
                                                                            City/Municipality
                                                                        </Label>

                                                                        <LocationAutocompleteInput
                                                                            id="employer_business_address2"
                                                                            name="employer_business_address2"
                                                                            search={
                                                                                employerBusinessCitySearch
                                                                            }
                                                                            placeholder="Select city or municipality"
                                                                            required
                                                                            inputClassName="mt-1 block w-full"
                                                                            loadingMessage="Searching city suggestions..."
                                                                            errorMessage="City suggestions are temporarily unavailable."
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.employer_business_address2
                                                                            }
                                                                        />
                                                                    </div>

                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="employer_business_address3">
                                                                            Province
                                                                        </Label>

                                                                        <LocationAutocompleteInput
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
                                                                        />

                                                                        <InputError
                                                                            className="mt-2"
                                                                            message={
                                                                                formErrors.employer_business_address3
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
                                                                            className="mt-1 block w-full"
                                                                            defaultValue={
                                                                                memberApplicationProfile?.current_position ??
                                                                                ''
                                                                            }
                                                                            name="current_position"
                                                                            required
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

                                                                        <div className="relative">
                                                                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground">
                                                                                PHP
                                                                            </span>
                                                                            <NumericFormat
                                                                                id="gross_monthly_income"
                                                                                className="mt-1 block w-full pl-12"
                                                                                value={
                                                                                    grossMonthlyIncome
                                                                                }
                                                                                onValueChange={(
                                                                                    values,
                                                                                ) => {
                                                                                    setGrossMonthlyIncome(
                                                                                        values.value,
                                                                                    );
                                                                                }}
                                                                                thousandSeparator
                                                                                decimalScale={
                                                                                    2
                                                                                }
                                                                                fixedDecimalScale
                                                                                allowNegative={
                                                                                    false
                                                                                }
                                                                                placeholder="0.00"
                                                                                inputMode="decimal"
                                                                                required
                                                                                valueIsNumericString
                                                                                customInput={
                                                                                    Input
                                                                                }
                                                                            />
                                                                        </div>

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
                                                                                className="mt-1 w-full"
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
                                                        </>
                                                    )}

                                                    <div className="flex items-center gap-4">
                                                        <Button
                                                            disabled={
                                                                processing
                                                            }
                                                            data-test="update-profile-button"
                                                        >
                                                            Save
                                                        </Button>

                                                        <Transition
                                                            show={
                                                                recentlySuccessful
                                                            }
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
                                                </>
                                            )}
                                        </Form>
                                    </div>

                                    <DeleteUser />

                                    <ProfileImageCropModal
                                        isOpen={showProfilePhotoCropModal}
                                        onClose={handleProfilePhotoCropClose}
                                        onSave={handleProfilePhotoCropSave}
                                        imagePreviewUrl={
                                            profilePhotoDraftPreview
                                        }
                                    />
                                </section>
                            </TabsContent>

                            <TabsContent
                                value="password"
                                forceMount
                                className={tabContentClasses}
                            >
                                <section className="max-w-xl space-y-6">
                                    <Heading
                                        variant="small"
                                        title="Update password"
                                        description="Ensure your account is using a long, random password to stay secure"
                                    />

                                    <Form
                                        {...PasswordController.update.form()}
                                        options={{
                                            preserveScroll: true,
                                        }}
                                        onSuccess={() => {
                                            showSuccessToast(
                                                adminToastCopy.success.updated(
                                                    'Password',
                                                ),
                                                { id: 'password-update' },
                                            );
                                        }}
                                        resetOnError={[
                                            'password',
                                            'password_confirmation',
                                            'current_password',
                                        ]}
                                        resetOnSuccess
                                        onError={(formErrors) => {
                                            showErrorToast(
                                                formErrors,
                                                adminToastCopy.error.updated(
                                                    'Password',
                                                ),
                                                { id: 'password-update' },
                                            );

                                            if (formErrors.password) {
                                                passwordInput.current?.focus();
                                            }

                                            if (formErrors.current_password) {
                                                currentPasswordInput.current?.focus();
                                            }
                                        }}
                                        className="space-y-6"
                                    >
                                        {({
                                            errors: formErrors,
                                            processing,
                                            recentlySuccessful,
                                        }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="current_password">
                                                        Current password
                                                    </Label>

                                                    <Input
                                                        id="current_password"
                                                        ref={
                                                            currentPasswordInput
                                                        }
                                                        name="current_password"
                                                        type="password"
                                                        className="mt-1 block w-full"
                                                        autoComplete="current-password"
                                                        placeholder="Current password"
                                                    />

                                                    <InputError
                                                        message={
                                                            formErrors.current_password
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="password">
                                                        New password
                                                    </Label>

                                                    <Input
                                                        id="password"
                                                        ref={passwordInput}
                                                        name="password"
                                                        type="password"
                                                        className="mt-1 block w-full"
                                                        autoComplete="new-password"
                                                        placeholder="New password"
                                                    />

                                                    <InputError
                                                        message={
                                                            formErrors.password
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="password_confirmation">
                                                        Confirm password
                                                    </Label>

                                                    <Input
                                                        id="password_confirmation"
                                                        name="password_confirmation"
                                                        type="password"
                                                        className="mt-1 block w-full"
                                                        autoComplete="new-password"
                                                        placeholder="Confirm password"
                                                    />

                                                    <InputError
                                                        message={
                                                            formErrors.password_confirmation
                                                        }
                                                    />
                                                </div>

                                                <div className="flex items-center gap-4">
                                                    <Button
                                                        disabled={processing}
                                                        data-test="update-password-button"
                                                    >
                                                        Save password
                                                    </Button>

                                                    <Transition
                                                        show={
                                                            recentlySuccessful
                                                        }
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
                                            </>
                                        )}
                                    </Form>
                                </section>
                            </TabsContent>

                            <TabsContent
                                value="two-factor"
                                forceMount
                                className={tabContentClasses}
                            >
                                <section className="max-w-xl space-y-6">
                                    <Heading
                                        variant="small"
                                        title="Two-Factor Authentication"
                                        description="Manage your two-factor authentication settings"
                                    />

                                    {!twoFactorAvailable ? (
                                        <p className="text-sm text-muted-foreground">
                                            Two-factor authentication is
                                            currently unavailable for this
                                            account.
                                        </p>
                                    ) : twoFactorEnabled ? (
                                        <div className="flex flex-col items-start justify-start space-y-4">
                                            <Badge variant="default">
                                                Enabled
                                            </Badge>
                                            <p className="text-muted-foreground">
                                                With two-factor authentication
                                                enabled, you will be prompted
                                                for a secure, random pin during
                                                login, which you can retrieve
                                                from the TOTP-supported
                                                application on your phone.
                                            </p>

                                            <TwoFactorRecoveryCodes
                                                recoveryCodesList={
                                                    recoveryCodesList
                                                }
                                                fetchRecoveryCodes={
                                                    fetchRecoveryCodes
                                                }
                                                errors={errors}
                                            />

                                            <div className="relative inline">
                                                <Form
                                                    {...disable.form()}
                                                    onSuccess={() => {
                                                        showSuccessToast(
                                                            adminToastCopy.success.disabled(
                                                                'Two-factor authentication',
                                                            ),
                                                            {
                                                                id: 'two-factor-disable',
                                                            },
                                                        );
                                                    }}
                                                    onError={(formErrors) => {
                                                        showErrorToast(
                                                            formErrors,
                                                            adminToastCopy.error.disabled(
                                                                'Two-factor authentication',
                                                            ),
                                                            {
                                                                id: 'two-factor-disable',
                                                            },
                                                        );
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            variant="destructive"
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <ShieldBan />{' '}
                                                            Disable 2FA
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-start justify-start space-y-4">
                                            <Badge variant="destructive">
                                                Disabled
                                            </Badge>
                                            <p className="text-muted-foreground">
                                                When you enable two-factor
                                                authentication, you will be
                                                prompted for a secure pin during
                                                login. This pin can be retrieved
                                                from a TOTP-supported
                                                application on your phone.
                                            </p>

                                            <div>
                                                {hasSetupData ? (
                                                    <Button
                                                        onClick={() =>
                                                            setShowSetupModal(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <ShieldCheck />
                                                        Continue Setup
                                                    </Button>
                                                ) : (
                                                    <Form
                                                        {...enable.form()}
                                                        onSuccess={() => {
                                                            setShowSetupModal(
                                                                true,
                                                            );

                                                            if (
                                                                !requiresConfirmation
                                                            ) {
                                                                showSuccessToast(
                                                                    adminToastCopy.success.enabled(
                                                                        'Two-factor authentication',
                                                                    ),
                                                                    {
                                                                        id: 'two-factor-enable',
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                        onError={(
                                                            formErrors,
                                                        ) => {
                                                            showErrorToast(
                                                                formErrors,
                                                                adminToastCopy.error.enabled(
                                                                    'Two-factor authentication',
                                                                ),
                                                                {
                                                                    id: 'two-factor-enable',
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <ShieldCheck />
                                                                Enable 2FA
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    <TwoFactorSetupModal
                                        isOpen={showSetupModal}
                                        onClose={() => setShowSetupModal(false)}
                                        requiresConfirmation={
                                            requiresConfirmation
                                        }
                                        twoFactorEnabled={twoFactorEnabled}
                                        qrCodeSvg={qrCodeSvg}
                                        manualSetupKey={manualSetupKey}
                                        clearSetupData={clearSetupData}
                                        fetchSetupData={fetchSetupData}
                                        errors={errors}
                                    />
                                </section>
                            </TabsContent>

                            <TabsContent
                                value="appearance"
                                forceMount
                                className={tabContentClasses}
                            >
                                <section className="max-w-xl space-y-6">
                                    <Heading
                                        variant="small"
                                        title="Appearance settings"
                                        description="Update your account's appearance settings"
                                    />
                                    <AppearanceTabs />
                                </section>
                            </TabsContent>
                        </div>
                    </Tabs>
                </SurfaceCard>
            </SettingsLayout>
        </AppLayout>
    );
}
