import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import { ShieldBan, ShieldCheck } from 'lucide-react';
import { useRef, useState } from 'react';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SurfaceCard } from '@/components/surface-card';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { security as securitySettings } from '@/routes/settings';
import { disable, enable } from '@/routes/two-factor';
import type { BreadcrumbItem } from '@/types';

type Props = {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    twoFactorAvailable?: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security settings',
        href: securitySettings().url,
    },
];

export default function Security({
    requiresConfirmation = false,
    twoFactorEnabled = false,
    twoFactorAvailable = true,
}: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
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
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Security settings" />

            <h1 className="sr-only">Security Settings</h1>

            <SettingsLayout>
                <SurfaceCard variant="default" padding="lg" className="space-y-8">
                    <section className="space-y-6">
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
                            resetOnError={[
                                'password',
                                'password_confirmation',
                                'current_password',
                            ]}
                            resetOnSuccess
                            onError={(formErrors) => {
                                if (formErrors.password) {
                                    passwordInput.current?.focus();
                                }

                                if (formErrors.current_password) {
                                    currentPasswordInput.current?.focus();
                                }
                            }}
                            className="space-y-6"
                        >
                            {({ errors: formErrors, processing, recentlySuccessful }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="current_password">
                                            Current password
                                        </Label>

                                        <Input
                                            id="current_password"
                                            ref={currentPasswordInput}
                                            name="current_password"
                                            type="password"
                                            className="mt-1 block w-full"
                                            autoComplete="current-password"
                                            placeholder="Current password"
                                        />

                                        <InputError
                                            message={formErrors.current_password}
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

                                        <InputError message={formErrors.password} />
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
                                            message={formErrors.password_confirmation}
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
                                </>
                            )}
                        </Form>
                    </section>

                    <Separator />

                    <section className="space-y-6">
                        <Heading
                            variant="small"
                            title="Two-Factor Authentication"
                            description="Manage your two-factor authentication settings"
                        />

                        {!twoFactorAvailable ? (
                            <p className="text-sm text-muted-foreground">
                                Two-factor authentication is currently unavailable
                                for this account.
                            </p>
                        ) : twoFactorEnabled ? (
                            <div className="flex flex-col items-start justify-start space-y-4">
                                <Badge variant="default">Enabled</Badge>
                                <p className="text-muted-foreground">
                                    With two-factor authentication enabled, you will
                                    be prompted for a secure, random pin during login,
                                    which you can retrieve from the TOTP-supported
                                    application on your phone.
                                </p>

                                <TwoFactorRecoveryCodes
                                    recoveryCodesList={recoveryCodesList}
                                    fetchRecoveryCodes={fetchRecoveryCodes}
                                    errors={errors}
                                />

                                <div className="relative inline">
                                    <Form {...disable.form()}>
                                        {({ processing }) => (
                                            <Button
                                                variant="destructive"
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <ShieldBan /> Disable 2FA
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col items-start justify-start space-y-4">
                                <Badge variant="destructive">Disabled</Badge>
                                <p className="text-muted-foreground">
                                    When you enable two-factor authentication, you
                                    will be prompted for a secure pin during login.
                                    This pin can be retrieved from a TOTP-supported
                                    application on your phone.
                                </p>

                                <div>
                                    {hasSetupData ? (
                                        <Button onClick={() => setShowSetupModal(true)}>
                                            <ShieldCheck />
                                            Continue Setup
                                        </Button>
                                    ) : (
                                        <Form
                                            {...enable.form()}
                                            onSuccess={() => setShowSetupModal(true)}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
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
                            requiresConfirmation={requiresConfirmation}
                            twoFactorEnabled={twoFactorEnabled}
                            qrCodeSvg={qrCodeSvg}
                            manualSetupKey={manualSetupKey}
                            clearSetupData={clearSetupData}
                            fetchSetupData={fetchSetupData}
                            errors={errors}
                        />
                    </section>

                    <Separator />

                    <DeleteUser />
                </SurfaceCard>
            </SettingsLayout>
        </AppLayout>
    );
}
