import { Link } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import type { ChangeEvent, RefObject } from 'react';
import InputError from '@/components/input-error';
import { SurfaceCard } from '@/components/surface-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { TabsContent } from '@/components/ui/tabs';
import { send } from '@/routes/verification';
import type { Auth } from '@/types/auth';
import {
    handleMobileNumberInput,
    type AdminProfileSummary,
} from '../profile-shared';

type Props = {
    formErrors: Record<string, string>;
    adminProfile: AdminProfileSummary | null;
    auth: Auth;
    displayName: string;
    getInitials: (name: string) => string;
    profilePhotoUrl: string | undefined;
    profilePhotoInputRef: RefObject<HTMLInputElement | null>;
    handleProfilePhotoChange: (event: ChangeEvent<HTMLInputElement>) => void;
    mustVerifyEmail: boolean;
    status?: string;
};

export function AccountTab({
    formErrors,
    adminProfile,
    auth,
    displayName,
    getInitials,
    profilePhotoUrl,
    profilePhotoInputRef,
    handleProfilePhotoChange,
    mustVerifyEmail,
    status,
}: Props) {
    return (
        <TabsContent value="account" forceMount className="mt-0">
            <SurfaceCard variant="muted" padding="md" className="space-y-6">
                <div className="space-y-1">
                    <h3 className="text-base font-semibold tracking-tight">
                        Account
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Manage your profile photo, login details, and contact
                        information.
                    </p>
                </div>

                <div className="space-y-6">
                    <div className="grid gap-3">
                        <Label htmlFor="profile_photo">Profile picture</Label>

                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                            <label
                                htmlFor="profile_photo"
                                className="group relative flex h-24 w-24 cursor-pointer items-center justify-center rounded-full"
                            >
                                <Avatar className="h-24 w-24 overflow-hidden rounded-full border border-border/70 shadow-sm">
                                    <AvatarImage
                                        src={profilePhotoUrl}
                                        alt={displayName}
                                        className="object-cover"
                                    />
                                    <AvatarFallback className="rounded-full bg-neutral-200 text-sm text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                        {getInitials(displayName)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="absolute inset-0 rounded-full bg-black/40 opacity-0 transition-opacity duration-200 group-hover:opacity-100" />
                                <span className="absolute right-1 bottom-1 flex h-8 w-8 items-center justify-center rounded-full border border-white/70 bg-white/90 text-neutral-900 shadow-sm transition-transform duration-200 group-hover:scale-105 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                                    <Camera className="h-4 w-4" />
                                </span>
                            </label>

                            <div className="space-y-2 text-sm text-muted-foreground">
                                <p>
                                    Upload a JPG, PNG, or WebP image (max 2MB).
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        profilePhotoInputRef.current?.click()
                                    }
                                >
                                    Change photo
                                </Button>
                            </div>
                        </div>

                        <input
                            id="profile_photo"
                            ref={profilePhotoInputRef}
                            name="profile_photo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            className="sr-only"
                            onChange={handleProfilePhotoChange}
                        />

                        <InputError
                            className="mt-2"
                            message={formErrors.profile_photo}
                        />
                    </div>

                    {adminProfile && (
                        <div className="grid gap-2">
                            <Label htmlFor="fullname">Full name</Label>

                            <Input
                                id="fullname"
                                className="mt-1 block w-full"
                                defaultValue={adminProfile.fullname ?? ''}
                                name="fullname"
                                autoComplete="name"
                                placeholder="Full name"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.fullname}
                            />
                        </div>
                    )}
                </div>

                <Separator />

                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">
                            Basic Account Information
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Update your login and contact details.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="username">Username</Label>

                            <Input
                                id="username"
                                className="mt-1 block w-full"
                                defaultValue={
                                    auth.user.username ?? auth.user.name
                                }
                                name="username"
                                required
                                autoComplete="username"
                                placeholder="Username"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.username}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>

                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                defaultValue={auth.user.email}
                                name="email"
                                required
                                autoComplete="username"
                                placeholder="Email address"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.email}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phoneno">Cell number</Label>

                            <Input
                                id="phoneno"
                                type="tel"
                                className="mt-1 block w-full"
                                defaultValue={auth.user.phoneno ?? ''}
                                name="phoneno"
                                required
                                autoComplete="tel"
                                inputMode="numeric"
                                maxLength={11}
                                placeholder="09XXXXXXXXX"
                                onChange={handleMobileNumberInput}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.phoneno}
                            />
                        </div>
                    </div>

                    {mustVerifyEmail &&
                        auth.user.email_verified_at === null && (
                            <div>
                                <p className="-mt-4 text-sm text-muted-foreground">
                                    Your email address is unverified.{' '}
                                    <Link
                                        href={send()}
                                        as="button"
                                        className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                    >
                                        Click here to resend the verification
                                        email.
                                    </Link>
                                </p>

                                {status === 'verification-link-sent' && (
                                    <div className="mt-2 text-sm font-medium text-green-600">
                                        A new verification link has been sent to
                                        your email address.
                                    </div>
                                )}
                            </div>
                        )}
                </div>
            </SurfaceCard>
        </TabsContent>
    );
}
