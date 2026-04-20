export const PASSWORD_RECOVERY_WIZARD_STEPS = {
    IDENTIFY: 'identify',
    CHOOSE_METHOD: 'choose_method',
    EMAIL_CONFIRMATION: 'email_confirmation',
    PHONE_VERIFY: 'phone_verify',
    PHONE_RESET: 'phone_reset',
};

export const resolvePasswordRecoveryWizardStep = ({
    recoveryStep,
    emailConfirmationVisible = false,
    stepOverride = null,
}) => {
    if (stepOverride !== null) {
        return stepOverride;
    }

    if (emailConfirmationVisible) {
        return PASSWORD_RECOVERY_WIZARD_STEPS.EMAIL_CONFIRMATION;
    }

    switch (recoveryStep) {
        case 'options':
            return PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD;
        case 'phone_verify':
            return PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY;
        case 'phone_reset':
            return PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_RESET;
        default:
            return PASSWORD_RECOVERY_WIZARD_STEPS.IDENTIFY;
    }
};

export const getPasswordRecoveryStepProgress = (wizardStep) => {
    switch (wizardStep) {
        case PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD:
            return { current: 2, total: 3 };
        case PASSWORD_RECOVERY_WIZARD_STEPS.EMAIL_CONFIRMATION:
            return { current: 3, total: 3 };
        case PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY:
            return { current: 3, total: 4 };
        case PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_RESET:
            return { current: 4, total: 4 };
        default:
            return { current: 1, total: 3 };
    }
};

export const getPasswordRecoveryStepIndex = (wizardStep) => {
    switch (wizardStep) {
        case PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD:
            return 1;
        case PASSWORD_RECOVERY_WIZARD_STEPS.EMAIL_CONFIRMATION:
        case PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY:
            return 2;
        case PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_RESET:
            return 3;
        default:
            return 0;
    }
};

export const resolvePasswordRecoveryTransitionDirection = ({
    previousStep = null,
    nextStep,
}) => {
    if (previousStep === null) {
        return 'forward';
    }

    return getPasswordRecoveryStepIndex(nextStep) <
        getPasswordRecoveryStepIndex(previousStep)
        ? 'backward'
        : 'forward';
};

export const getPasswordRecoveryStepContent = ({
    wizardStep,
    selectedOptionMaskedValue = null,
    phoneMaskedValue = null,
    otpLength = 6,
}) => {
    switch (wizardStep) {
        case PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD:
            return {
                title: 'Choose a recovery method',
                description:
                    'Select how you want to continue password recovery.',
            };
        case PASSWORD_RECOVERY_WIZARD_STEPS.EMAIL_CONFIRMATION:
            return {
                title: 'Check your email',
                description:
                    selectedOptionMaskedValue !== null
                        ? `If the details match our records, a reset link has been sent to ${selectedOptionMaskedValue}.`
                        : 'If the details match our records, a reset link has been sent.',
            };
        case PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY:
            return {
                title: 'Verify the SMS code',
                description:
                    phoneMaskedValue !== null
                        ? `Enter the ${otpLength}-digit code sent to ${phoneMaskedValue}.`
                        : `Enter the ${otpLength}-digit code sent to your phone.`,
            };
        case PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_RESET:
            return {
                title: 'Set a new password',
                description:
                    'Create a new password to finish recovering your account.',
            };
        default:
            return {
                title: 'Identify your account',
                description:
                    'Enter your username, account number, or email to continue.',
            };
    }
};
