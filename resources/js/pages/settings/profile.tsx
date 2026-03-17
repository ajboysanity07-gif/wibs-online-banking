import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Camera, ShieldBan, ShieldCheck } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import AppearanceTabs from '@/components/appearance-tabs';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import ProfileImageCropModal, {
    type ProfileImageCropResult,
} from '@/components/profile/profile-image-crop-modal';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useInitials } from '@/hooks/use-initials';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { createCroppedImageFile } from '@/lib/image-crop';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
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

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    adminProfile?: AdminProfileSummary | null;
    initialTab?: SettingsTab;
    twoFactorEnabled?: boolean;
    requiresConfirmation?: boolean;
    twoFactorAvailable?: boolean;
};

const tabContentClasses =
    'data-[state=active]:animate-in data-[state=active]:fade-in-0 data-[state=active]:slide-in-from-bottom-2 data-[state=active]:duration-300';

const PROFILE_PHOTO_MAX_BYTES = 2 * 1024 * 1024;
const PROFILE_PHOTO_ALLOWED_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
]);
const PROFILE_PHOTO_OUTPUT_SIZE = 512;
const PROFILE_PHOTO_OUTPUT_QUALITY = 0.92;

export default function Profile({
    mustVerifyEmail,
    status,
    adminProfile = null,
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
    const [profilePhotoDraftFile, setProfilePhotoDraftFile] = useState<
        File | null
    >(null);
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

    const handleProfilePhotoChange = (
        event: ChangeEvent<HTMLInputElement>,
    ) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (!PROFILE_PHOTO_ALLOWED_TYPES.has(file.type)) {
            showErrorToast(
                null,
                'Please select a JPG, PNG, or WebP image.',
                {
                    id: 'profile-photo-type',
                },
            );
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
                <div className="mt-6">
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

                        <div className="flex-1 md:max-w-2xl">
                            <TabsContent
                                value="profile"
                                forceMount
                                className={tabContentClasses}
                            >
                                <section className="max-w-xl space-y-12">
                                    <div className="space-y-6">
                                        <Heading
                                            variant="small"
                                            title="Profile information"
                                            description="Update your profile details, photo, and contact information"
                                        />

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
                                            className="space-y-6"
                                        >
                                            {({
                                                processing,
                                                recentlySuccessful,
                                                errors: formErrors,
                                            }) => (
                                                <>
                                                    {adminProfile && (
                                                        <>
                                                            <div className="grid gap-3">
                                                                <Label htmlFor="profile_photo">
                                                                    Profile
                                                                    picture
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
                                                                        <span className="absolute bottom-1 right-1 flex h-8 w-8 items-center justify-center rounded-full border border-white/70 bg-white/90 text-neutral-900 shadow-sm transition-transform duration-200 group-hover:scale-105 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                                                                            <Camera className="h-4 w-4" />
                                                                        </span>
                                                                    </label>

                                                                    <div className="space-y-2 text-sm text-muted-foreground">
                                                                        <p>
                                                                            Upload
                                                                            a
                                                                            JPG,
                                                                            PNG,
                                                                            or
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
                                                        </>
                                                    )}

                                                    <div className="grid gap-2">
                                                        <Label htmlFor="username">
                                                            Username
                                                        </Label>

                                                        <Input
                                                            id="username"
                                                            className="mt-1 block w-full"
                                                            defaultValue={
                                                                auth.user
                                                                    .username ??
                                                                auth.user.name
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
                                                            Email address
                                                        </Label>

                                                        <Input
                                                            id="email"
                                                            type="email"
                                                            className="mt-1 block w-full"
                                                            defaultValue={
                                                                auth.user.email
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
                                                            Phone number
                                                        </Label>

                                                        <Input
                                                            id="phoneno"
                                                            type="tel"
                                                            className="mt-1 block w-full"
                                                            defaultValue={
                                                                auth.user.phoneno
                                                            }
                                                            name="phoneno"
                                                            required
                                                            autoComplete="tel"
                                                            inputMode="numeric"
                                                            maxLength={11}
                                                            placeholder="09XXXXXXXXX"
                                                        />

                                                        <InputError
                                                            className="mt-2"
                                                            message={
                                                                formErrors.phoneno
                                                            }
                                                        />
                                                    </div>

                                                    {mustVerifyEmail &&
                                                        auth.user
                                                            .email_verified_at ===
                                                            null && (
                                                            <div>
                                                                <p className="-mt-4 text-sm text-muted-foreground">
                                                                    Your email
                                                                    address is
                                                                    unverified.{' '}
                                                                    <Link
                                                                        href={send()}
                                                                        as="button"
                                                                        className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                                    >
                                                                        Click
                                                                        here to
                                                                        resend
                                                                        the
                                                                        verification
                                                                        email.
                                                                    </Link>
                                                                </p>

                                                                {status ===
                                                                    'verification-link-sent' && (
                                                                    <div className="mt-2 text-sm font-medium text-green-600">
                                                                        A new
                                                                        verification
                                                                        link has
                                                                        been
                                                                        sent to
                                                                        your
                                                                        email
                                                                        address.
                                                                    </div>
                                                                )}
                                                            </div>
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
                                                    onError={(
                                                        formErrors,
                                                    ) => {
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
                                                            <ShieldBan /> Disable
                                                            2FA
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
                                        onClose={() =>
                                            setShowSetupModal(false)
                                        }
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
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
