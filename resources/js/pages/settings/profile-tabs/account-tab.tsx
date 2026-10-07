import { Link } from '@inertiajs/react';
import { Camera, IdCard, UserCog } from 'lucide-react';
import type { ChangeEvent, RefObject } from 'react';
import InputError from '@/components/input-error';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { InlineEditRow } from '@/components/settings/inline-edit-row';
import { SurfaceCard } from '@/components/surface-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TabsContent } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { send } from '@/routes/verification';
import type { Auth } from '@/types/auth';
import {
    handleMobileNumberInput,
    hasWmasterValue,
    WMASTER_VALUE_CLASS,
    type AdminProfileSummary,
    type MemberRecord,
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
    memberRecord: MemberRecord | null;
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
    memberRecord,
}: Props) {
    return (
        <TabsContent value="account" forceMount className="mt-0">
            <SurfaceCard padding="none" className="overflow-hidden">
                <div className="space-y-1 px-6 pt-6 pb-5">
                    <h3 className="text-xl font-bold tracking-tight">
                        Account
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Manage your profile photo, login details, and contact
                        information.
                    </p>
                </div>

                <LoanRequestSectionCard
                    flat
                    title="Profile"
                    icon={UserCog}
                    contentClassName="space-y-0"
                >
                    <InlineEditRow
                        label="Profile picture"
                        value={profilePhotoUrl ? 'Photo uploaded' : 'No photo'}
                    >
                        <div className="grid gap-3">
                            <Label htmlFor="profile_photo">
                                Profile picture
                            </Label>

                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                                <Label
                                    htmlFor="profile_photo"
                                    className="group relative flex h-24 w-24 cursor-pointer items-center justify-center rounded-full text-base font-normal"
                                >
                                    <Avatar className="h-24 w-24 overflow-hidden rounded-full border border-border shadow-sm">
                                        <AvatarImage
                                            src={profilePhotoUrl}
                                            alt={displayName}
                                            className="object-cover"
                                        />
                                        <AvatarFallback className="rounded-full bg-muted text-sm text-foreground">
                                            {getInitials(displayName)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="absolute inset-0 rounded-full bg-black/40 opacity-0 transition-opacity duration-200 group-hover:opacity-100" />
                                    <span className="absolute right-1 bottom-1 flex h-8 w-8 items-center justify-center rounded-full border border-border bg-card text-foreground shadow-card transition-transform duration-200 group-hover:scale-105">
                                        <Camera className="h-4 w-4" />
                                    </span>
                                </Label>

                                <div className="space-y-2 text-sm text-muted-foreground">
                                    <p>
                                        Upload a JPG, PNG, or WebP image (max
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
                    </InlineEditRow>

                    {adminProfile && (
                        <InlineEditRow label="Full name">
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
                        </InlineEditRow>
                    )}
                </LoanRequestSectionCard>

                <LoanRequestSectionCard
                    flat
                    title="Basic Account Information"
                    description="Update your login and contact details."
                    icon={IdCard}
                    contentClassName="space-y-0"
                >
                    <div>
                        <InlineEditRow label="Username">
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
                        </InlineEditRow>

                        <InlineEditRow label="Email address">
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
                        </InlineEditRow>

                        <InlineEditRow label="Cell number">
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
                        </InlineEditRow>

                        {memberRecord && (
                            <InlineEditRow
                                label="Contact number on file"
                                readOnly
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="member_telephone">
                                        Contact number on file
                                    </Label>

                                    <Input
                                        id="member_telephone"
                                        type="tel"
                                        className={cn(
                                            'mt-1 block w-full',
                                            hasWmasterValue(
                                                memberRecord.telephone,
                                            ) && WMASTER_VALUE_CLASS,
                                        )}
                                        defaultValue={
                                            memberRecord.telephone ?? ''
                                        }
                                        disabled
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        From your membership record -- this is
                                        the number printed on insurance
                                        documents (Generali, Grepalife).
                                    </p>
                                </div>
                            </InlineEditRow>
                        )}
                    </div>

                    {mustVerifyEmail &&
                        auth.user.email_verified_at === null && (
                            <div>
                                <p className="mt-4 text-sm text-muted-foreground">
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
                                    <div className="mt-2 text-sm font-medium text-primary">
                                        A new verification link has been sent to
                                        your email address.
                                    </div>
                                )}
                            </div>
                        )}
                </LoanRequestSectionCard>
            </SurfaceCard>
        </TabsContent>
    );
}
