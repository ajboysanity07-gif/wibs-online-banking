import { Form, Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { FieldMessage } from '@/components/ui/field-message';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordInput } from '@/components/ui/password-input';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { store, usernameSuggestions } from '@/routes/register';

type MemberName = {
    first_name: string;
    last_name: string;
    middle_initial?: string | null;
};

type SuggestionsResponse = {
    current?: { value: string; available: boolean } | null;
    suggestions: string[];
};

type Props = {
    memberName?: MemberName | null;
};

const fetchSuggestions = async (
    url: string,
    signal: AbortSignal,
): Promise<SuggestionsResponse> => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        signal,
    });

    if (!response.ok) {
        throw new Error(`Failed to fetch suggestions: ${response.status}`);
    }

    return response.json();
};

export default function Register({ memberName }: Props) {
    const [passwordValue, setPasswordValue] = useState('');
    const [passwordConfirmationValue, setPasswordConfirmationValue] =
        useState('');
    const [usernameValue, setUsernameValue] = useState('');
    const [availability, setAvailability] = useState<
        'unknown' | 'checking' | 'available' | 'taken'
    >('unknown');
    const [suggestions, setSuggestions] = useState<string[]>([]);
    const [hasFocused, setHasFocused] = useState(false);
    const abortRef = useRef<AbortController | null>(null);
    const hasMemberName = Boolean(memberName);
    const shownSuggestions = hasMemberName ? suggestions : [];
    const shownAvailability = hasMemberName ? availability : 'unknown';

    const availabilityLabel = useMemo(() => {
        if (shownAvailability === 'checking') {
            return 'Checking...';
        }

        if (shownAvailability === 'available') {
            return 'Available';
        }

        if (shownAvailability === 'taken') {
            return 'Not available';
        }

        return null;
    }, [shownAvailability]);

    const availabilityClassName = useMemo(() => {
        if (shownAvailability === 'available') {
            return 'text-xs text-emerald-600';
        }

        if (shownAvailability === 'taken') {
            return 'text-xs text-red-600';
        }

        return 'text-xs text-muted-foreground';
    }, [shownAvailability]);

    const shouldShowSuggestions =
        hasMemberName &&
        shownSuggestions.length > 0 &&
        (hasFocused || usernameValue !== '' || shownAvailability === 'taken');

    const requestSuggestions = useCallback(
        async (currentValue: string, signal: AbortSignal): Promise<void> => {
            if (signal.aborted) {
                return;
            }

            if (currentValue !== '') {
                setAvailability('checking');
            } else {
                setAvailability('unknown');
            }

            const url =
                currentValue === ''
                    ? usernameSuggestions.url()
                    : usernameSuggestions.url({ query: { current: currentValue } });
            const data = await fetchSuggestions(url, signal);

            setSuggestions(data.suggestions ?? []);

            if (currentValue === '') {
                setAvailability('unknown');
                return;
            }

            if (data.current?.available) {
                setAvailability('available');
            } else {
                setAvailability('taken');
            }
        },
        [],
    );

    useEffect(() => {
        if (!hasMemberName) {
            return;
        }

        const currentValue = usernameValue.trim();
        const delay = currentValue === '' ? 0 : 300;
        const controller = new AbortController();

        abortRef.current?.abort();
        abortRef.current = controller;

        const timer = window.setTimeout(() => {
            requestSuggestions(currentValue, controller.signal).catch(() => {
                if (controller.signal.aborted) {
                    return;
                }

                setSuggestions([]);
                setAvailability('unknown');
            });
        }, delay);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [hasMemberName, memberName, requestSuggestions, usernameValue]);

    return (
        <AuthLayout
            title="Create your login"
            description="Enter your login details to finish setting up your account"
        >
            <Head title="Create login" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors, clearErrors }) => (
                    <>
                        <div className="grid gap-6">
                            <InputError
                                message={errors.verification}
                                className="text-sm"
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="username">Username</Label>
                                <Input
                                    id="username"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    name="username"
                                    placeholder="Choose a username"
                                    value={usernameValue}
                                    onChange={(event) => {
                                        setUsernameValue(event.target.value);
                                        if (errors.username) {
                                            clearErrors?.('username');
                                        }
                                    }}
                                    onFocus={() => setHasFocused(true)}
                                />
                                <FieldMessage
                                    error={errors.username}
                                    hint={availabilityLabel ?? undefined}
                                    reserveSpace
                                    className={
                                        errors.username
                                            ? undefined
                                            : availabilityClassName
                                    }
                                />
                                {shouldShowSuggestions && (
                                    <div className="space-y-2">
                                        <p className="text-xs text-muted-foreground">
                                            Suggestions
                                        </p>
                                        <div
                                            className="flex flex-wrap gap-2"
                                            aria-label="Username suggestions"
                                        >
                                            {shownSuggestions.map((suggestion) => (
                                                <Button
                                                    key={suggestion}
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-8 px-2 text-xs"
                                                    onClick={() => {
                                                        setUsernameValue(
                                                            suggestion,
                                                        );
                                                        setAvailability(
                                                            'checking',
                                                        );
                                                    }}
                                                >
                                                    {suggestion}
                                                </Button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <FieldMessage
                                    error={errors.email}
                                    reserveSpace={false}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phoneno">
                                    Phone number (optional)
                                </Label>
                                <Input
                                    id="phoneno"
                                    type="tel"
                                    tabIndex={3}
                                    autoComplete="tel"
                                    inputMode="numeric"
                                    name="phoneno"
                                    placeholder="09XXXXXXXXX"
                                    maxLength={11}
                                />
                                <FieldMessage
                                    error={errors.phoneno}
                                    reserveSpace={false}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    onChange={(event) =>
                                        setPasswordValue(event.target.value)
                                    }
                                />
                                <FieldMessage
                                    error={errors.password}
                                    reserveSpace={false}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setPasswordConfirmationValue(value);
                                        if (errors.password_confirmation) {
                                            clearErrors?.(
                                                'password_confirmation',
                                            );
                                        }
                                    }}
                                />
                                <FieldMessage
                                    error={errors.password_confirmation}
                                    hint={
                                        passwordConfirmationValue.length > 0
                                            ? passwordValue ===
                                                  passwordConfirmationValue
                                                ? 'Passwords match'
                                                : 'Passwords do not match'
                                            : undefined
                                    }
                                    reserveSpace
                                    className={
                                        errors.password_confirmation
                                            ? undefined
                                            : passwordConfirmationValue.length > 0
                                            ? passwordValue ===
                                              passwordConfirmationValue
                                                ? 'text-xs text-emerald-600'
                                                : 'text-xs text-red-600'
                                            : undefined
                                    }
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={6}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create login
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={7}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
