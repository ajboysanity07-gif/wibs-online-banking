import { Form } from '@inertiajs/react';
import { useState } from 'react';
import {
    SettingsRow,
    SettingsSwitch,
} from '@/components/settings/settings-panel';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Badge } from '@/components/ui/badge';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { disable, enable } from '@/routes/two-factor';

const LABEL = 'Two-factor authentication';

type Props = {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    twoFactorAvailable?: boolean;
};

export function TwoFactorSection({
    requiresConfirmation = false,
    twoFactorEnabled = false,
    twoFactorAvailable = true,
}: Props) {
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

    if (!twoFactorAvailable) {
        return (
            <SettingsRow
                label={LABEL}
                value={
                    <p className="text-muted-foreground">
                        Two-factor authentication is currently unavailable for
                        this account.
                    </p>
                }
            />
        );
    }

    return (
        <>
            <SettingsRow
                label={LABEL}
                value={
                    <div className="space-y-2">
                        <Badge
                            variant={
                                twoFactorEnabled ? 'default' : 'destructive'
                            }
                        >
                            {twoFactorEnabled ? 'Enabled' : 'Disabled'}
                        </Badge>
                        <p className="text-muted-foreground">
                            {twoFactorEnabled
                                ? 'With two-factor authentication enabled, you will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.'
                                : 'When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.'}
                        </p>
                    </div>
                }
                action={
                    twoFactorEnabled ? (
                        <Form {...disable.form()}>
                            {({ processing }) => (
                                <SettingsSwitch
                                    checked
                                    label={`Disable ${LABEL.toLowerCase()}`}
                                    type="submit"
                                    disabled={processing}
                                />
                            )}
                        </Form>
                    ) : hasSetupData ? (
                        <SettingsSwitch
                            checked={false}
                            label="Continue two-factor authentication setup"
                            onClick={() => setShowSetupModal(true)}
                        />
                    ) : (
                        <Form
                            {...enable.form()}
                            onSuccess={() => setShowSetupModal(true)}
                        >
                            {({ processing }) => (
                                <SettingsSwitch
                                    checked={false}
                                    label={`Enable ${LABEL.toLowerCase()}`}
                                    type="submit"
                                    disabled={processing}
                                />
                            )}
                        </Form>
                    )
                }
            />

            {twoFactorEnabled && (
                <SettingsRow label="Recovery codes">
                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                </SettingsRow>
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
        </>
    );
}
