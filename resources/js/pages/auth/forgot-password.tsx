import { Transition } from '@headlessui/react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { type FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { PasswordInput } from '@/components/ui/password-input';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import api, { getApiErrorMessage, mapValidationErrors } from '@/lib/api';
import {
    getPasswordRecoveryStepContent,
    getPasswordRecoveryStepIndex,
    getPasswordRecoveryStepProgress,
    PASSWORD_RECOVERY_WIZARD_STEPS,
    resolvePasswordRecoveryTransitionDirection,
    resolvePasswordRecoveryWizardStep,
} from '@/lib/password-recovery-flow';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { login } from '@/routes';

type RecoveryOption = {
    type: 'email' | 'phone';
    label: string;
    masked_value: string;
};

type RecoveryState = {
    step: 'lookup' | 'options' | 'phone_verify' | 'phone_reset';
    options: RecoveryOption[];
    phone: {
        masked_value: string;
    } | null;
};

type RecoveryResponse = {
    message?: string;
    redirect_to?: string;
    recovery?: RecoveryState;
};

type Props = {
    recovery: RecoveryState;
    status?: string;
};

type PendingAction = 'lookup' | 'email' | 'phone' | 'verify' | 'reset' | null;
type WizardStep =
    | 'identify'
    | 'choose_method'
    | 'email_confirmation'
    | 'phone_verify'
    | 'phone_reset';

const defaultRecoveryState: RecoveryState = {
    step: 'lookup',
    options: [],
    phone: null,
};

const changeActionClassName =
    'cursor-pointer text-sm text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500';

function SummaryRow({
    label,
    value,
    actionLabel,
    onAction,
}: {
    label: string;
    value: string;
    actionLabel: string;
    onAction: () => void;
}) {
    return (
        <div className="flex items-center justify-between gap-4 rounded-xl border border-border/50 bg-muted/30 px-4 py-3">
            <div className="min-w-0 space-y-1">
                <p className="text-xs font-medium tracking-[0.12em] text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="truncate text-sm font-medium">{value}</p>
            </div>
            <button
                type="button"
                className={changeActionClassName}
                onClick={onAction}
            >
                {actionLabel}
            </button>
        </div>
    );
}

export default function ForgotPassword({ recovery, status }: Props) {
    const [lookupIdentifier, setLookupIdentifier] = useState('');
    const [confirmedIdentifier, setConfirmedIdentifier] = useState('');
    const [code, setCode] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [recoveryState, setRecoveryState] = useState<RecoveryState>(
        recovery ?? defaultRecoveryState,
    );
    const [statusMessage, setStatusMessage] = useState(status ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [globalError, setGlobalError] = useState('');
    const [pendingAction, setPendingAction] = useState<PendingAction>(null);
    const [selectedMethod, setSelectedMethod] = useState<
        RecoveryOption['type'] | null
    >(null);
    const [emailConfirmationVisible, setEmailConfirmationVisible] =
        useState(false);
    const [stepOverride, setStepOverride] = useState<WizardStep | null>(null);
    const previousStepRef = useRef<WizardStep | null>(null);

    useEffect(() => {
        setRecoveryState(recovery ?? defaultRecoveryState);
        setStatusMessage(status ?? '');

        if (
            recovery?.step === 'phone_verify' ||
            recovery?.step === 'phone_reset'
        ) {
            setSelectedMethod('phone');
        }

        if (recovery?.step === 'lookup') {
            setSelectedMethod(null);
            setEmailConfirmationVisible(false);
        }
    }, [recovery, status]);

    const currentStep = useMemo<WizardStep>(
        () =>
            resolvePasswordRecoveryWizardStep({
                recoveryStep: recoveryState.step,
                emailConfirmationVisible,
                stepOverride,
            }),
        [emailConfirmationVisible, recoveryState.step, stepOverride],
    );

    const progress = useMemo(
        () => getPasswordRecoveryStepProgress(currentStep),
        [currentStep],
    );

    const selectedOption = useMemo<RecoveryOption | null>(() => {
        const inferredMethod =
            selectedMethod ??
            (currentStep === PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY ||
            currentStep === PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_RESET
                ? 'phone'
                : null);

        if (inferredMethod === null) {
            return null;
        }

        const matchedOption =
            recoveryState.options.find(
                (option) => option.type === inferredMethod,
            ) ?? null;

        if (matchedOption !== null) {
            return matchedOption;
        }

        if (inferredMethod === 'phone' && recoveryState.phone !== null) {
            return {
                type: 'phone',
                label: 'Send code',
                masked_value: recoveryState.phone.masked_value,
            };
        }

        return null;
    }, [
        currentStep,
        recoveryState.options,
        recoveryState.phone,
        selectedMethod,
    ]);

    const stepDetails = useMemo(
        () =>
            getPasswordRecoveryStepContent({
                wizardStep: currentStep,
                selectedOptionMaskedValue: selectedOption?.masked_value ?? null,
                phoneMaskedValue: recoveryState.phone?.masked_value ?? null,
                otpLength: OTP_MAX_LENGTH,
            }),
        [
            currentStep,
            recoveryState.phone?.masked_value,
            selectedOption?.masked_value,
        ],
    );

    const transitionDirection = useMemo(
        () =>
            resolvePasswordRecoveryTransitionDirection({
                previousStep: previousStepRef.current,
                nextStep: currentStep,
            }),
        [currentStep],
    );

    useEffect(() => {
        previousStepRef.current = currentStep;
    }, [currentStep]);

    const clearError = (key: string): void => {
        setErrors((current) => {
            if (!current[key]) {
                return current;
            }

            const next = { ...current };
            delete next[key];
            return next;
        });
    };

    const resetTransientState = (): void => {
        setGlobalError('');
        setStatusMessage('');
        setErrors({});
    };

    const moveToIdentifyStep = (): void => {
        setStepOverride(PASSWORD_RECOVERY_WIZARD_STEPS.IDENTIFY);
        setSelectedMethod(null);
        setEmailConfirmationVisible(false);
        setCode('');
        setPassword('');
        setPasswordConfirmation('');
        resetTransientState();
    };

    const moveToMethodStep = (): void => {
        setStepOverride(PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD);
        setSelectedMethod(null);
        setEmailConfirmationVisible(false);
        setCode('');
        setPassword('');
        setPasswordConfirmation('');
        resetTransientState();
    };

    const applyRecoveryResponse = (
        payload: RecoveryResponse | undefined,
        options?: {
            selectedMethod?: RecoveryOption['type'] | null;
            emailConfirmationVisible?: boolean;
            confirmedIdentifier?: string | null;
        },
    ): void => {
        const nextRecovery = payload?.recovery ?? defaultRecoveryState;

        setRecoveryState(nextRecovery);
        setStatusMessage(payload?.message ?? '');
        setGlobalError('');
        setErrors({});
        setStepOverride(null);

        if (options?.selectedMethod !== undefined) {
            setSelectedMethod(options.selectedMethod);
        }

        if (options?.emailConfirmationVisible !== undefined) {
            setEmailConfirmationVisible(options.emailConfirmationVisible);
        }

        if (options?.confirmedIdentifier !== undefined) {
            setConfirmedIdentifier(options.confirmedIdentifier ?? '');
        }
    };

    const handleFailure = (
        error: unknown,
        fallbackMessage: string,
        resetToLookup = false,
    ): void => {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            setErrors(mapValidationErrors(error.response.data?.errors));
            setGlobalError('');
            return;
        }

        setGlobalError(getApiErrorMessage(error, fallbackMessage));

        if (resetToLookup) {
            setSelectedMethod(null);
            setEmailConfirmationVisible(false);
            setStepOverride(PASSWORD_RECOVERY_WIZARD_STEPS.IDENTIFY);
        }
    };

    const submitLookup = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setPendingAction('lookup');
        resetTransientState();
        clearError('identifier');

        try {
            const response = await api.post<RecoveryResponse>(
                '/spa/password-recovery/lookup',
                {
                    identifier: lookupIdentifier,
                },
            );

            const normalizedIdentifier = lookupIdentifier.trim();

            applyRecoveryResponse(response.data, {
                selectedMethod: null,
                emailConfirmationVisible: false,
                confirmedIdentifier:
                    normalizedIdentifier !== ''
                        ? normalizedIdentifier
                        : confirmedIdentifier,
            });
        } catch (error) {
            handleFailure(
                error,
                'We could not start account recovery right now.',
            );
        } finally {
            setPendingAction(null);
        }
    };

    const sendEmailRecovery = async (): Promise<void> => {
        setPendingAction('email');
        resetTransientState();

        try {
            const response = await api.post<RecoveryResponse>(
                '/spa/password-recovery/email',
            );

            applyRecoveryResponse(response.data, {
                selectedMethod: 'email',
                emailConfirmationVisible: true,
            });
        } catch (error) {
            handleFailure(
                error,
                'We could not send a password reset link right now.',
                true,
            );
        } finally {
            setPendingAction(null);
        }
    };

    const sendPhoneRecoveryCode = async (): Promise<void> => {
        setPendingAction('phone');
        resetTransientState();

        try {
            const response = await api.post<RecoveryResponse>(
                '/spa/password-recovery/phone/send',
            );

            applyRecoveryResponse(response.data, {
                selectedMethod: 'phone',
                emailConfirmationVisible: false,
            });
            setCode('');
        } catch (error) {
            handleFailure(
                error,
                'We could not send a verification code right now.',
                true,
            );
        } finally {
            setPendingAction(null);
        }
    };

    const verifyPhoneCode = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setPendingAction('verify');
        resetTransientState();
        clearError('code');

        try {
            const response = await api.post<RecoveryResponse>(
                '/spa/password-recovery/phone/verify',
                {
                    code,
                },
            );

            applyRecoveryResponse(response.data, {
                selectedMethod: 'phone',
                emailConfirmationVisible: false,
            });
        } catch (error) {
            handleFailure(
                error,
                'We could not verify that code right now.',
                true,
            );
        } finally {
            setPendingAction(null);
        }
    };

    const resetPasswordWithPhone = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();
        setPendingAction('reset');
        resetTransientState();
        clearError('password');
        clearError('password_confirmation');

        try {
            const response = await api.post<RecoveryResponse>(
                '/spa/password-recovery/phone/reset',
                {
                    password,
                    password_confirmation: passwordConfirmation,
                },
            );

            if (response.data.redirect_to) {
                router.visit(response.data.redirect_to);
                return;
            }

            applyRecoveryResponse(response.data);
        } catch (error) {
            handleFailure(
                error,
                'We could not reset your password right now.',
                true,
            );
        } finally {
            setPendingAction(null);
        }
    };

    const renderStepBody = () => {
        if (currentStep === PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD) {
            return (
                <div className="space-y-3">
                    {recoveryState.options.map((option) => (
                        <button
                            key={option.type}
                            type="button"
                            className="flex w-full items-start justify-between gap-4 rounded-xl border border-border/50 bg-background px-4 py-4 text-left transition-colors hover:border-border"
                            onClick={() => {
                                if (option.type === 'email') {
                                    void sendEmailRecovery();
                                    return;
                                }

                                void sendPhoneRecoveryCode();
                            }}
                            disabled={pendingAction !== null}
                            data-test={`${option.type}-recovery-option-button`}
                        >
                            <div className="space-y-1">
                                <p className="text-sm font-medium">
                                    {option.label} to {option.masked_value}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {option.type === 'email'
                                        ? 'Continue with the standard reset-link flow.'
                                        : 'Receive a one-time verification code by SMS.'}
                                </p>
                            </div>
                            {pendingAction === option.type && <Spinner />}
                        </button>
                    ))}
                </div>
            );
        }

        if (currentStep === PASSWORD_RECOVERY_WIZARD_STEPS.EMAIL_CONFIRMATION) {
            return (
                <div className="space-y-4 text-center">
                    <div className="rounded-xl border border-border/50 bg-muted/40 px-4 py-4 text-sm text-muted-foreground">
                        If the details match our records, the next step is in
                        your inbox.
                    </div>

                    <div className="text-sm text-muted-foreground">
                        Need something else?{' '}
                        <button
                            type="button"
                            className={changeActionClassName}
                            onClick={moveToMethodStep}
                        >
                            Choose another recovery method
                        </button>
                    </div>
                </div>
            );
        }

        if (currentStep === PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_VERIFY) {
            return (
                <form onSubmit={verifyPhoneCode} className="space-y-4">
                    <div className="flex flex-col items-center gap-3 text-center">
                        <InputOTP
                            name="code"
                            maxLength={OTP_MAX_LENGTH}
                            value={code}
                            onChange={(value) => {
                                setCode(value);
                                clearError('code');
                            }}
                            pattern={REGEXP_ONLY_DIGITS}
                            disabled={pendingAction !== null}
                        >
                            <InputOTPGroup>
                                {Array.from(
                                    { length: OTP_MAX_LENGTH },
                                    (_, index) => (
                                        <InputOTPSlot
                                            key={index}
                                            index={index}
                                        />
                                    ),
                                )}
                            </InputOTPGroup>
                        </InputOTP>
                        <InputError message={errors.code} />
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button
                            type="submit"
                            className="flex-1"
                            disabled={pendingAction !== null}
                            data-test="phone-recovery-verify-button"
                        >
                            {pendingAction === 'verify' && <Spinner />}
                            Continue
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            className="flex-1"
                            disabled={pendingAction !== null}
                            onClick={() => {
                                void sendPhoneRecoveryCode();
                            }}
                            data-test="phone-recovery-resend-button"
                        >
                            {pendingAction === 'phone' && <Spinner />}
                            Resend code
                        </Button>
                    </div>
                </form>
            );
        }

        if (currentStep === PASSWORD_RECOVERY_WIZARD_STEPS.PHONE_RESET) {
            return (
                <form onSubmit={resetPasswordWithPhone} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            autoComplete="new-password"
                            placeholder="New password"
                            value={password}
                            onChange={(event) => {
                                setPassword(event.target.value);
                                clearError('password');
                            }}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirm password
                        </Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            autoComplete="new-password"
                            placeholder="Confirm password"
                            value={passwordConfirmation}
                            onChange={(event) => {
                                setPasswordConfirmation(event.target.value);
                                clearError('password_confirmation');
                            }}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={pendingAction !== null}
                        data-test="phone-recovery-reset-button"
                    >
                        {pendingAction === 'reset' && <Spinner />}
                        Reset password
                    </Button>
                </form>
            );
        }

        return (
            <form onSubmit={submitLookup} className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="identifier">
                        Username, account number, or email
                    </Label>
                    <Input
                        id="identifier"
                        type="text"
                        name="identifier"
                        autoComplete="off"
                        autoFocus
                        placeholder="username, 000123, or email@example.com"
                        value={lookupIdentifier}
                        onChange={(event) => {
                            setLookupIdentifier(event.target.value);
                            clearError('identifier');
                        }}
                    />
                    <InputError message={errors.identifier} />
                </div>

                <Button
                    type="submit"
                    className="w-full"
                    disabled={pendingAction !== null}
                    data-test="password-recovery-lookup-button"
                >
                    {pendingAction === 'lookup' && <Spinner />}
                    Continue
                </Button>
            </form>
        );
    };

    const transitionEnterFromClassName =
        transitionDirection === 'forward'
            ? 'opacity-0 translate-x-3'
            : 'opacity-0 -translate-x-3';

    return (
        <AuthLayout
            title={stepDetails.title}
            description={stepDetails.description}
        >
            <Head title="Forgot password" />

            <div className="flex flex-col gap-6">
                <p className="text-center text-xs font-medium tracking-[0.14em] text-muted-foreground uppercase">
                    Step {progress.current} of {progress.total}
                </p>

                {statusMessage && (
                    <div className="rounded-xl border border-green-500/20 bg-green-500/10 px-4 py-3 text-sm font-medium text-green-700 dark:text-green-300">
                        {statusMessage}
                    </div>
                )}

                {globalError && (
                    <div className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
                        {globalError}
                    </div>
                )}

                <Transition
                    key={`${currentStep}-${getPasswordRecoveryStepIndex(currentStep)}-${transitionDirection}`}
                    appear
                    show
                    enter="transition motion-safe:duration-200 motion-safe:ease-out motion-reduce:transition-none"
                    enterFrom={transitionEnterFromClassName}
                    enterTo="opacity-100 translate-x-0"
                >
                    <div className="space-y-6">
                        {currentStep !==
                            PASSWORD_RECOVERY_WIZARD_STEPS.IDENTIFY && (
                            <div className="space-y-2">
                                <SummaryRow
                                    label="Identifier"
                                    value={
                                        confirmedIdentifier !== ''
                                            ? confirmedIdentifier
                                            : 'Account identified'
                                    }
                                    actionLabel="Change"
                                    onAction={moveToIdentifyStep}
                                />

                                {selectedOption !== null &&
                                    currentStep !==
                                        PASSWORD_RECOVERY_WIZARD_STEPS.CHOOSE_METHOD && (
                                        <SummaryRow
                                            label="Recovery method"
                                            value={selectedOption.masked_value}
                                            actionLabel="Change"
                                            onAction={moveToMethodStep}
                                        />
                                    )}
                            </div>
                        )}

                        {renderStepBody()}
                    </div>
                </Transition>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Or, return to</span>
                    <TextLink href={login()}>log in</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
