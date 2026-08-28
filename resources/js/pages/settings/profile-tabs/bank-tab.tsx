import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import {
    PaymentAccountPickerSheet,
    PaymentMethodIcon,
} from '@/components/loan-request/payment-account-picker-sheet';
import type { PaymentMethodOption } from '@/components/loan-request/payment-account-picker-sheet';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { TabsContent } from '@/components/ui/tabs';
import { useSavedPaymentAccounts } from '@/hooks/use-saved-payment-accounts';
import { cn } from '@/lib/utils';
import type { MemberApplicationProfileData } from '../profile-shared';
import {
    ID_TYPE_OPTIONS,
    ID_TYPE_OTHER_VALUE,
    MISSING_FIELD_CLASS,
    PAYMENT_OPTION_OPTIONS,
    RELEASE_METHOD_OPTIONS,
} from '../profile-shared';

type Props = {
    formErrors: Record<string, string>;
    memberApplicationProfile: MemberApplicationProfileData | null;
    isFieldMissing: (field: string) => boolean;
    releaseMethod: string;
    setReleaseMethod: (value: string) => void;
    releaseAccountId: number | null;
    setReleaseAccountId: (value: number | null) => void;
    idTypeSelection: string;
    setIdTypeSelection: (value: string) => void;
    idTypeOther: string;
    setIdTypeOther: (value: string) => void;
    paymentOption: string;
    setPaymentOption: (value: string) => void;
    paymentAccountId: number | null;
    setPaymentAccountId: (value: number | null) => void;
};

const RELEASE_METHOD_OPTIONS_LIST: PaymentMethodOption[] =
    RELEASE_METHOD_OPTIONS.map((value) => ({
        value,
        label: value,
        needsAccount: value === 'ATM' || value === 'Bank Transfer',
    }));

const PAYMENT_OPTION_OPTIONS_LIST: PaymentMethodOption[] =
    PAYMENT_OPTION_OPTIONS.map((value) => ({
        value,
        label: value,
        needsAccount: value === 'ATM Deduction',
    }));

export function BankTab({
    formErrors,
    memberApplicationProfile,
    isFieldMissing,
    releaseMethod,
    setReleaseMethod,
    releaseAccountId,
    setReleaseAccountId,
    idTypeSelection,
    setIdTypeSelection,
    idTypeOther,
    setIdTypeOther,
    paymentOption,
    setPaymentOption,
    paymentAccountId,
    setPaymentAccountId,
}: Props) {
    const {
        accounts,
        isLoading: isLoadingAccounts,
        isSaving: isSavingAccount,
        loadAccounts,
        createAccount,
    } = useSavedPaymentAccounts();
    const [isReleaseSheetOpen, setIsReleaseSheetOpen] = useState(false);
    const [isPaymentSheetOpen, setIsPaymentSheetOpen] = useState(false);

    useEffect(() => {
        void loadAccounts();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const releaseNeedsAccount =
        releaseMethod === 'ATM' || releaseMethod === 'Bank Transfer';
    const paymentNeedsAccount = paymentOption === 'ATM Deduction';

    const releaseAccountLabel = useMemo(
        () =>
            accounts.find((account) => account.id === releaseAccountId)?.label,
        [accounts, releaseAccountId],
    );
    const paymentAccountLabel = useMemo(
        () =>
            accounts.find((account) => account.id === paymentAccountId)?.label,
        [accounts, paymentAccountId],
    );

    const confirmRelease = async (method: string, accountId: number | null) => {
        setReleaseMethod(method);
        setReleaseAccountId(accountId);

        return true;
    };

    const confirmPayment = async (method: string, accountId: number | null) => {
        setPaymentOption(method);
        setPaymentAccountId(accountId);

        return true;
    };

    return (
        <TabsContent value="bank" forceMount className="mt-0">
            <SurfaceCard variant="muted" padding="md" className="space-y-6">
                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">
                            Loan Disbursement
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Choose how you&apos;d like to receive your loan.
                            Bank account details are required for ATM and Bank
                            Transfer.
                        </p>
                    </div>

                    <div
                        className={cn(
                            'flex flex-wrap items-center gap-3 rounded-md border border-input p-3',
                            isFieldMissing('release_method') &&
                                MISSING_FIELD_CLASS,
                        )}
                    >
                        <div className="flex-1 space-y-1">
                            <div className="flex items-center gap-2">
                                <PaymentMethodIcon
                                    method={releaseMethod || null}
                                    className="h-4 w-4 text-muted-foreground"
                                />
                                <p className="text-sm font-medium">
                                    {releaseMethod || 'Not set'}
                                </p>
                            </div>
                            {releaseNeedsAccount && (
                                <p className="text-sm text-muted-foreground">
                                    {releaseAccountLabel ??
                                        'No account selected'}
                                </p>
                            )}
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                void loadAccounts();
                                setIsReleaseSheetOpen(true);
                            }}
                        >
                            {releaseMethod ? 'Change' : 'Choose release method'}
                        </Button>
                        <input
                            type="hidden"
                            name="release_method"
                            value={releaseMethod}
                        />
                        <input
                            type="hidden"
                            name="release_saved_account_id"
                            value={releaseAccountId ?? ''}
                        />
                    </div>

                    <InputError message={formErrors.release_method} />
                    <InputError message={formErrors.release_saved_account_id} />
                </div>

                <Separator />

                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">
                            Repayment Method
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Choose how your loan installments will be collected.
                            This becomes the default for new loan requests --
                            you can still change it per request.
                        </p>
                    </div>

                    <div
                        className={cn(
                            'flex flex-wrap items-center gap-3 rounded-md border border-input p-3',
                            isFieldMissing('payment_option') &&
                                MISSING_FIELD_CLASS,
                        )}
                    >
                        <div className="flex-1 space-y-1">
                            <div className="flex items-center gap-2">
                                <PaymentMethodIcon
                                    method={paymentOption || null}
                                    className="h-4 w-4 text-muted-foreground"
                                />
                                <p className="text-sm font-medium">
                                    {paymentOption || 'Not set'}
                                </p>
                            </div>
                            {paymentNeedsAccount && (
                                <p className="text-sm text-muted-foreground">
                                    {paymentAccountLabel ??
                                        'No account selected'}
                                </p>
                            )}
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                void loadAccounts();
                                setIsPaymentSheetOpen(true);
                            }}
                        >
                            {paymentOption
                                ? 'Change'
                                : 'Choose repayment method'}
                        </Button>
                        <input
                            type="hidden"
                            name="payment_option"
                            value={paymentOption}
                        />
                        <input
                            type="hidden"
                            name="payment_saved_account_id"
                            value={paymentAccountId ?? ''}
                        />
                    </div>

                    <InputError message={formErrors.payment_option} />
                    <InputError message={formErrors.payment_saved_account_id} />
                </div>

                <PaymentAccountPickerSheet
                    open={isReleaseSheetOpen}
                    onOpenChange={setIsReleaseSheetOpen}
                    title="Choose release method"
                    description="Select how you'd like to receive your loan proceeds."
                    accounts={accounts}
                    methodOptions={RELEASE_METHOD_OPTIONS_LIST}
                    initialMethod={releaseMethod || null}
                    initialAccountId={releaseAccountId}
                    isSaving={isSavingAccount || isLoadingAccounts}
                    onConfirm={confirmRelease}
                    onCreateAccount={createAccount}
                />
                <PaymentAccountPickerSheet
                    open={isPaymentSheetOpen}
                    onOpenChange={setIsPaymentSheetOpen}
                    title="Choose repayment method"
                    description="Select how you'd like to repay your loan."
                    accounts={accounts}
                    methodOptions={PAYMENT_OPTION_OPTIONS_LIST}
                    initialMethod={paymentOption || null}
                    initialAccountId={paymentAccountId}
                    isSaving={isSavingAccount || isLoadingAccounts}
                    onConfirm={confirmPayment}
                    onCreateAccount={createAccount}
                />

                <Separator />

                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">
                            Source of Funds &amp; Government ID
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Required before you can start a loan request.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="source_of_fund_wealth">
                                Source of fund / wealth
                            </Label>

                            <Input
                                id="source_of_fund_wealth"
                                className={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('source_of_fund_wealth') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                defaultValue={
                                    memberApplicationProfile?.source_of_fund_wealth ??
                                    ''
                                }
                                name="source_of_fund_wealth"
                                placeholder="e.g. Salary, business income"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.source_of_fund_wealth}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="id_type">Government ID type</Label>

                            <Select
                                value={idTypeSelection || undefined}
                                onValueChange={(value) => {
                                    setIdTypeSelection(value);

                                    if (value !== ID_TYPE_OTHER_VALUE) {
                                        setIdTypeOther('');
                                    }
                                }}
                            >
                                <SelectTrigger
                                    id="id_type"
                                    className={cn(
                                        'mt-1 w-full',
                                        isFieldMissing('id_type') &&
                                            MISSING_FIELD_CLASS,
                                    )}
                                >
                                    <SelectValue placeholder="Select ID type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ID_TYPE_OPTIONS.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <input
                                type="hidden"
                                name="id_type"
                                value={idTypeSelection}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.id_type}
                            />
                        </div>

                        {idTypeSelection === ID_TYPE_OTHER_VALUE && (
                            <div className="grid gap-2">
                                <Label htmlFor="id_type_other">
                                    Specify ID type
                                </Label>

                                <Input
                                    id="id_type_other"
                                    className="mt-1 block w-full"
                                    value={idTypeOther}
                                    name="id_type_other"
                                    placeholder="Describe your ID type"
                                    onChange={(event) => {
                                        setIdTypeOther(event.target.value);
                                    }}
                                />

                                <InputError
                                    className="mt-2"
                                    message={formErrors.id_type_other}
                                />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="id_number">ID number</Label>

                            <Input
                                id="id_number"
                                className={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('id_number') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                defaultValue={
                                    memberApplicationProfile?.id_number ?? ''
                                }
                                name="id_number"
                                placeholder="ID number"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.id_number}
                            />
                        </div>
                    </div>
                </div>

                <Separator />

                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">
                            Physical details
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Required for the Generali Health Statement.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="height_cm">Height</Label>

                            <div className="relative">
                                <Input
                                    id="height_cm"
                                    className={cn(
                                        'mt-1 block w-full pr-10',
                                        isFieldMissing('height_cm') &&
                                            MISSING_FIELD_CLASS,
                                    )}
                                    defaultValue={
                                        memberApplicationProfile?.height_cm ??
                                        ''
                                    }
                                    name="height_cm"
                                    inputMode="numeric"
                                    placeholder="e.g. 165"
                                />

                                <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
                                    cm
                                </span>
                            </div>

                            <InputError
                                className="mt-2"
                                message={formErrors.height_cm}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="weight_kg">Weight</Label>

                            <div className="relative">
                                <Input
                                    id="weight_kg"
                                    className={cn(
                                        'mt-1 block w-full pr-10',
                                        isFieldMissing('weight_kg') &&
                                            MISSING_FIELD_CLASS,
                                    )}
                                    defaultValue={
                                        memberApplicationProfile?.weight_kg ??
                                        ''
                                    }
                                    name="weight_kg"
                                    inputMode="numeric"
                                    placeholder="e.g. 65"
                                />

                                <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
                                    kg
                                </span>
                            </div>

                            <InputError
                                className="mt-2"
                                message={formErrors.weight_kg}
                            />
                        </div>
                    </div>
                </div>
            </SurfaceCard>
        </TabsContent>
    );
}
