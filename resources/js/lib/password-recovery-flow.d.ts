export type PasswordRecoveryStep =
    | 'lookup'
    | 'options'
    | 'phone_verify'
    | 'phone_reset';

export type PasswordRecoveryWizardStep =
    | 'identify'
    | 'choose_method'
    | 'email_confirmation'
    | 'phone_verify'
    | 'phone_reset';

export type PasswordRecoveryProgressItemState =
    | 'complete'
    | 'current'
    | 'upcoming';

export const PASSWORD_RECOVERY_WIZARD_STEPS: {
    readonly IDENTIFY: 'identify';
    readonly CHOOSE_METHOD: 'choose_method';
    readonly EMAIL_CONFIRMATION: 'email_confirmation';
    readonly PHONE_VERIFY: 'phone_verify';
    readonly PHONE_RESET: 'phone_reset';
};

export const PASSWORD_RECOVERY_PROGRESS_STEPS: Array<{
    id: string;
    label: string;
}>;

export function resolvePasswordRecoveryWizardStep(args: {
    recoveryStep: PasswordRecoveryStep;
    emailConfirmationVisible?: boolean;
    stepOverride?: PasswordRecoveryWizardStep | null;
}): PasswordRecoveryWizardStep;

export function getPasswordRecoveryProgressItems(
    wizardStep: PasswordRecoveryWizardStep,
): Array<{
    id: string;
    label: string;
    state: PasswordRecoveryProgressItemState;
}>;

export function getPasswordRecoveryIdentifierSummary(args?: {
    typedIdentifier?: string | null;
}): string;

export function getPasswordRecoveryStepIndex(
    wizardStep: PasswordRecoveryWizardStep,
): number;

export function resolvePasswordRecoveryTransitionDirection(args: {
    previousStep?: PasswordRecoveryWizardStep | null;
    nextStep: PasswordRecoveryWizardStep;
}): 'forward' | 'backward';

export function getPasswordRecoveryStepContent(args: {
    wizardStep: PasswordRecoveryWizardStep;
    selectedOptionMaskedValue?: string | null;
    phoneMaskedValue?: string | null;
    otpLength?: number;
}): {
    title: string;
    description: string;
};
