import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { type FormEvent, useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { SurfaceCard } from '@/components/surface-card';
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

const defaultRecoveryState: RecoveryState = {
    step: 'lookup',
    options: [],
    phone: null,
};

type PendingAction = 'lookup' | 'email' | 'phone' | 'verify' | 'reset' | null;

export default function ForgotPassword({ recovery, status }: Props) {
    const [lookupIdentifier, setLookupIdentifier] = useState('');
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

    useEffect(() => {
        setRecoveryState(recovery ?? defaultRecoveryState);
    }, [recovery]);

    useEffect(() => {
        setStatusMessage(status ?? '');
    }, [status]);

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

    const handleResponse = (payload?: RecoveryResponse): void => {
        setRecoveryState(payload?.recovery ?? defaultRecoveryState);
        setStatusMessage(payload?.message ?? '');
        setErrors({});
        setGlobalError('');
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
            setRecoveryState(defaultRecoveryState);
        }
    };

    const submitLookup = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setPendingAction('lookup');
        setGlobalError('');
        setStatusMessage('');
        clearError('identifier');

        try {
            const response = await api.post<RecoveryResponse>(
                '/spa/password-recovery/lookup',
                {
                    identifier: lookupIdentifier,
                },
            );

            handleResponse(response.data);
            setCode('');
            setPassword('');
            setPasswordConfirmation('');
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
        setGlobalError('');
        setStatusMessage('');

        try {
            const response = await api.post<RecoveryResponse>(
                '/spa/password-recovery/email',
            );

            handleResponse(response.data);
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
        setGlobalError('');
        setStatusMessage('');

        try {
            const response = await api.post<RecoveryResponse>(
                '/spa/password-recovery/phone/send',
            );

            handleResponse(response.data);
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
        setGlobalError('');
        setStatusMessage('');
        clearError('code');

        try {
            const response = await api.post<RecoveryResponse>(
                '/spa/password-recovery/phone/verify',
                {
                    code,
                },
            );

            handleResponse(response.data);
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
        setGlobalError('');
        setStatusMessage('');
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

            handleResponse(response.data);
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

    const isPhoneStep =
        recoveryState.step === 'phone_verify' ||
        recoveryState.step === 'phone_reset';

    return (
        <AuthLayout
            title="Forgot password"
            description="Find your account and choose how you want to reset your password"
        >
            <Head title="Forgot password" />

            <div className="space-y-6">
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

                <SurfaceCard variant="muted" padding="md" className="space-y-4">
                    <div className="space-y-1">
                        <p className="text-sm font-medium">Step 1</p>
                        <h2 className="text-base font-medium">
                            Identify your account
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Enter your username, account number, or email.
                        </p>
                    </div>

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
                </SurfaceCard>

                <SurfaceCard variant="muted" padding="md" className="space-y-4">
                    <div className="space-y-1">
                        <p className="text-sm font-medium">Step 2</p>
                        <h2 className="text-base font-medium">
                            Choose a recovery method
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Available options appear here after we identify your
                            account.
                        </p>
                    </div>

                    {recoveryState.options.length > 0 ? (
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
                                            {option.label} to{' '}
                                            {option.masked_value}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {option.type === 'email'
                                                ? 'Use your email inbox to continue the reset flow.'
                                                : 'We will send a one-time verification code by SMS.'}
                                        </p>
                                    </div>
                                    {pendingAction === option.type && (
                                        <Spinner />
                                    )}
                                </button>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Start with step 1 to view the recovery methods
                            linked to your account.
                        </p>
                    )}
                </SurfaceCard>

                {isPhoneStep && (
                    <SurfaceCard
                        variant="muted"
                        padding="md"
                        className="space-y-4"
                    >
                        <div className="space-y-1">
                            <p className="text-sm font-medium">Step 3</p>
                            <h2 className="text-base font-medium">
                                {recoveryState.step === 'phone_verify'
                                    ? 'Verify the SMS code'
                                    : 'Set a new password'}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {recoveryState.step === 'phone_verify'
                                    ? `Enter the ${OTP_MAX_LENGTH}-digit code sent to ${recoveryState.phone?.masked_value ?? 'your phone'}.`
                                    : `Verified through ${recoveryState.phone?.masked_value ?? 'your phone'}. Enter your new password below.`}
                            </p>
                        </div>

                        {recoveryState.step === 'phone_verify' ? (
                            <form
                                onSubmit={verifyPhoneCode}
                                className="space-y-4"
                            >
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
                                        {pendingAction === 'verify' && (
                                            <Spinner />
                                        )}
                                        Verify code
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
                                        {pendingAction === 'phone' && (
                                            <Spinner />
                                        )}
                                        Resend code
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <form
                                onSubmit={resetPasswordWithPhone}
                                className="space-y-4"
                            >
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
                                            setPasswordConfirmation(
                                                event.target.value,
                                            );
                                            clearError('password_confirmation');
                                        }}
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
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
                        )}
                    </SurfaceCard>
                )}

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Or, return to</span>
                    <TextLink href={login()}>log in</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
