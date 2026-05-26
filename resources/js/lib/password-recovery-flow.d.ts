export type PasswordRecoveryWizardStep =
    | 'identify'
    | 'choose_method'
    | 'email_confirmation'
    | 'phone_verify'
    | 'phone_reset';

export type PasswordRecoveryStateStep =
    | 'lookup'
    | 'options'
    | 'phone_verify'
    | 'phone_reset';

export type PasswordRecoveryProgressStepId = 'identify' | 'recover' | 'reset';

export type PasswordRecoveryProgressState =
    | 'complete'
    | 'current'
    | 'upcoming';

export type PasswordRecoveryProgressItem = {
    id: PasswordRecoveryProgressStepId;
    label: string;
    state: PasswordRecoveryProgressState;
};

export type PasswordRecoveryStepContent = {
    title: string;
    description: string;
};

export type PasswordRecoveryTransitionDirection = 'forward' | 'backward';

export const PASSWORD_RECOVERY_WIZARD_STEPS: {
    readonly IDENTIFY: 'identify';
    readonly CHOOSE_METHOD: 'choose_method';
    readonly EMAIL_CONFIRMATION: 'email_confirmation';
    readonly PHONE_VERIFY: 'phone_verify';
    readonly PHONE_RESET: 'phone_reset';
};

export const PASSWORD_RECOVERY_PROGRESS_STEPS: Array<{
    id: PasswordRecoveryProgressStepId;
    label: string;
}>;

export function resolvePasswordRecoveryWizardStep(options: {
    recoveryStep: PasswordRecoveryStateStep;
    emailConfirmationVisible?: boolean;
    stepOverride?: PasswordRecoveryWizardStep | null;
}): PasswordRecoveryWizardStep;

export function getPasswordRecoveryProgressItems(
    wizardStep: PasswordRecoveryWizardStep,
): PasswordRecoveryProgressItem[];

export function getPasswordRecoveryIdentifierSummary(options?: {
    typedIdentifier?: string | null;
}): string;

export function getPasswordRecoveryStepIndex(
    wizardStep: PasswordRecoveryWizardStep,
): number;

export function resolvePasswordRecoveryTransitionDirection(options: {
    previousStep?: PasswordRecoveryWizardStep | null;
    nextStep: PasswordRecoveryWizardStep;
}): PasswordRecoveryTransitionDirection;

export function getPasswordRecoveryStepContent(options: {
    wizardStep: PasswordRecoveryWizardStep;
    selectedOptionMaskedValue?: string | null;
    phoneMaskedValue?: string | null;
    otpLength?: number;
}): PasswordRecoveryStepContent;
