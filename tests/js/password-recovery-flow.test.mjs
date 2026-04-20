import assert from 'node:assert/strict';
import test from 'node:test';
import {
    getPasswordRecoveryIdentifierSummary,
    getPasswordRecoveryStepContent,
    getPasswordRecoveryStepIndex,
    getPasswordRecoveryProgressItems,
    PASSWORD_RECOVERY_WIZARD_STEPS,
    resolvePasswordRecoveryTransitionDirection,
    resolvePasswordRecoveryWizardStep,
} from '../../resources/js/lib/password-recovery-flow.js';

test('lookup recovery state resolves to the identify step', () => {
    assert.equal(
        resolvePasswordRecoveryWizardStep({
            recoveryStep: 'lookup',
        }),
        PASSWORD_RECOVERY_WIZARD_STEPS.IDENTIFY,
    );
});

test('options recovery state resolves to choose method', () => {
    assert.equal(
        resolvePasswordRecoveryWizardStep({
            recoveryStep: 'options',
        }),
        PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD,
    );
});

test('email confirmation overrides the server options step', () => {
    assert.equal(
        resolvePasswordRecoveryWizardStep({
            recoveryStep: 'options',
            emailConfirmationVisible: true,
        }),
        PASSWORD_RECOVERY_WIZARD_STEPS.EMAIL_CONFIRMATION,
    );
});

test('manual step override wins over recovery state', () => {
    assert.equal(
        resolvePasswordRecoveryWizardStep({
            recoveryStep: 'phone_verify',
            stepOverride: PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD,
        }),
        PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD,
    );
});

test('phone recovery steps report the correct transition order', () => {
    assert.deepEqual(
        [
            getPasswordRecoveryStepIndex(
                PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD,
            ),
            getPasswordRecoveryStepIndex(
                PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY,
            ),
            getPasswordRecoveryStepIndex(
                PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_RESET,
            ),
        ],
        [1, 2, 3],
    );
});

test('email confirmation keeps the same transition order as phone verification', () => {
    assert.equal(
        getPasswordRecoveryStepIndex(
            PASSWORD_RECOVERY_WIZARD_STEPS.EMAIL_CONFIRMATION,
        ),
        getPasswordRecoveryStepIndex(
            PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY,
        ),
    );
});

test('progress items stay on a stable identify recover reset row for the email branch', () => {
    assert.deepEqual(
        getPasswordRecoveryProgressItems(
            PASSWORD_RECOVERY_WIZARD_STEPS.EMAIL_CONFIRMATION,
        ),
        [
            {
                id: 'identify',
                label: 'Identify',
                state: 'complete',
            },
            {
                id: 'recover',
                label: 'Recover',
                state: 'complete',
            },
            {
                id: 'reset',
                label: 'Reset',
                state: 'current',
            },
        ],
    );
});

test('progress items keep the reset stage upcoming during phone verification', () => {
    assert.deepEqual(
        getPasswordRecoveryProgressItems(
            PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY,
        ),
        [
            {
                id: 'identify',
                label: 'Identify',
                state: 'complete',
            },
            {
                id: 'recover',
                label: 'Recover',
                state: 'current',
            },
            {
                id: 'reset',
                label: 'Reset',
                state: 'upcoming',
            },
        ],
    );
});

test('transition direction moves backward when returning to an earlier step', () => {
    assert.equal(
        resolvePasswordRecoveryTransitionDirection({
            previousStep: PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_RESET,
            nextStep: PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD,
        }),
        'backward',
    );
});

test('transition direction moves forward when continuing deeper into recovery', () => {
    assert.equal(
        resolvePasswordRecoveryTransitionDirection({
            previousStep: PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD,
            nextStep: PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY,
        }),
        'forward',
    );
});

test('identifier summary never echoes the raw typed lookup value', () => {
    assert.equal(
        getPasswordRecoveryIdentifierSummary({
            typedIdentifier: 'member@example.com',
        }),
        'Account identified',
    );
});

test('wizard content uses focused copy for the email confirmation step', () => {
    assert.deepEqual(
        getPasswordRecoveryStepContent({
            wizardStep: PASSWORD_RECOVERY_WIZARD_STEPS.EMAIL_CONFIRMATION,
            selectedOptionMaskedValue: 'j*****@gmail.com',
        }),
        {
            title: 'Check your email',
            description:
                'If the details match our records, a reset link has been sent to j*****@gmail.com.',
        },
    );
});

test('wizard content uses the masked phone and otp length for phone verification', () => {
    assert.deepEqual(
        getPasswordRecoveryStepContent({
            wizardStep: PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY,
            phoneMaskedValue: '******2943',
            otpLength: 6,
        }),
        {
            title: 'Verify the SMS code',
            description: 'Enter the 6-digit code sent to ******2943.',
        },
    );
});
