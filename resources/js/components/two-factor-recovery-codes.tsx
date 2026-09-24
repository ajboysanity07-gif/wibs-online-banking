import { Form } from '@inertiajs/react';
import { Eye, EyeOff, Loader2, LockKeyhole, RefreshCw } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import { regenerateRecoveryCodes } from '@/routes/two-factor';
import AlertError from './alert-error';

type Props = {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
}: Props) {
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const [isFetchingCodes, setIsFetchingCodes] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const toggleCodesVisibility = async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            setIsFetchingCodes(true);
            await fetchRecoveryCodes();
            setIsFetchingCodes(false);
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        }
    };

    const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex gap-3">
                    <LockKeyhole className="size-4" aria-hidden="true" />
                    2FA Recovery Codes
                </CardTitle>
                <CardDescription>
                    Recovery codes let you regain access if you lose your 2FA
                    device. Store them in a secure password manager.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between">
                    <Button
                        onClick={toggleCodesVisibility}
                        className="w-fit"
                        disabled={isFetchingCodes}
                        aria-expanded={codesAreVisible}
                        aria-controls="recovery-codes-section"
                    >
                        {isFetchingCodes ? (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        ) : (
                            <RecoveryCodeIconComponent
                                className="size-4"
                                aria-hidden="true"
                            />
                        )}
                        {isFetchingCodes
                            ? 'Loading...'
                            : `${codesAreVisible ? 'Hide' : 'View'} Recovery Codes`}
                    </Button>

                    {canRegenerateCodes && (
                        <Form
                            {...regenerateRecoveryCodes.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={() => {
                                showSuccessToast(
                                    adminToastCopy.success.updated(
                                        'Recovery codes',
                                    ),
                                    { id: 'recovery-codes' },
                                );
                                fetchRecoveryCodes();
                            }}
                            onError={(formErrors) => {
                                showErrorToast(
                                    formErrors,
                                    adminToastCopy.error.updated(
                                        'Recovery codes',
                                    ),
                                    { id: 'recovery-codes' },
                                );
                            }}
                        >
                            {({ processing }) => (
                                <Button
                                    variant="secondary"
                                    type="submit"
                                    disabled={processing}
                                    aria-describedby="regenerate-warning"
                                >
                                    {processing ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <RefreshCw />
                                    )}
                                    {processing
                                        ? 'Regenerating...'
                                        : 'Regenerate Codes'}
                                </Button>
                            )}
                        </Form>
                    )}
                </div>
                <div
                    id="recovery-codes-section"
                    className={`relative overflow-hidden transition-all duration-300 ${codesAreVisible ? 'h-auto opacity-100' : 'h-0 opacity-0'}`}
                    aria-hidden={!codesAreVisible}
                >
                    <div className="mt-3 space-y-3">
                        {errors?.length ? (
                            <AlertError errors={errors} />
                        ) : (
                            <>
                                <div
                                    ref={codesSectionRef}
                                    className="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                                    role="list"
                                    aria-label="Recovery codes"
                                >
                                    {recoveryCodesList.length ? (
                                        recoveryCodesList.map((code, index) => (
                                            <div
                                                key={index}
                                                role="listitem"
                                                className="select-text"
                                            >
                                                {code}
                                            </div>
                                        ))
                                    ) : (
                                        <div
                                            className="space-y-2"
                                            aria-label="Loading recovery codes"
                                        >
                                            {Array.from(
                                                { length: 8 },
                                                (_, index) => (
                                                    <div
                                                        key={index}
                                                        className="h-4 animate-pulse rounded bg-muted-foreground/20"
                                                        aria-hidden="true"
                                                    />
                                                ),
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="text-xs text-muted-foreground select-none">
                                    <p id="regenerate-warning">
                                        Each recovery code can be used once to
                                        access your account and will be removed
                                        after use. If you need more, click{' '}
                                        <span className="font-bold">
                                            Regenerate Codes
                                        </span>{' '}
                                        above.
                                    </p>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
