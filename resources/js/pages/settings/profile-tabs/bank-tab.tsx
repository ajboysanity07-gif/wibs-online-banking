import {
    Banknote,
    Briefcase,
    CreditCard,
    DollarSign,
    FileCheck2,
    Landmark,
    PenTool,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import {
    PaymentAccountPickerSheet,
    PaymentMethodIcon,
} from '@/components/loan-request/payment-account-picker-sheet';
import type { PaymentMethodOption } from '@/components/loan-request/payment-account-picker-sheet';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import { TabsContent } from '@/components/ui/tabs';
import { useSavedPaymentAccounts } from '@/hooks/use-saved-payment-accounts';
import { cn } from '@/lib/utils';
import {
    MISSING_FIELD_CLASS,
    PAYMENT_OPTION_OPTIONS,
    RELEASE_METHOD_OPTIONS,
} from '../profile-shared';

type Props = {
    formErrors: Record<string, string>;
    isFieldMissing: (field: string) => boolean;
    releaseMethod: string;
    setReleaseMethod: (value: string) => void;
    releaseAccountId: number | null;
    setReleaseAccountId: (value: number | null) => void;
    paymentOption: string;
    setPaymentOption: (value: string) => void;
    paymentAccountId: number | null;
    setPaymentAccountId: (value: number | null) => void;
};

const RELEASE_METHOD_LABELS: Record<string, string> = {
    ATM: 'ATM / Debit Card',
    'Bank Transfer': 'Bank Transfer',
    Check: 'Check',
    Cash: 'Cash Release',
};

const RELEASE_METHOD_ICONS: Record<string, LucideIcon> = {
    ATM: CreditCard,
    'Bank Transfer': Landmark,
    Check: FileCheck2,
    Cash: Banknote,
};

const PAYMENT_OPTION_LABELS: Record<string, string> = {
    'Salary Deduction': 'Salary Deduction',
    'ATM Deduction': 'Auto-Debit / Bank Account (ADA)',
    'Bank Transfer': 'Bank Transfer',
    Check: 'Post-Dated Check (PDC)',
    Cash: 'Over-the-Counter Cash',
};

const PAYMENT_OPTION_ICONS: Record<string, LucideIcon> = {
    'Salary Deduction': Briefcase,
    'ATM Deduction': CreditCard,
    'Bank Transfer': Landmark,
    Check: PenTool,
    Cash: DollarSign,
};

const RELEASE_METHOD_OPTIONS_LIST: PaymentMethodOption[] =
    RELEASE_METHOD_OPTIONS.map((value) => ({
        value,
        label: RELEASE_METHOD_LABELS[value] ?? value,
        icon: RELEASE_METHOD_ICONS[value],
        needsAccount: value === 'ATM' || value === 'Bank Transfer',
    }));

const PAYMENT_OPTION_OPTIONS_LIST: PaymentMethodOption[] =
    PAYMENT_OPTION_OPTIONS.map((value) => ({
        value,
        label: PAYMENT_OPTION_LABELS[value] ?? value,
        icon: PAYMENT_OPTION_ICONS[value],
        needsAccount: value === 'ATM Deduction' || value === 'Bank Transfer',
    }));

export function BankTab({
    formErrors,
    isFieldMissing,
    releaseMethod,
    setReleaseMethod,
    releaseAccountId,
    setReleaseAccountId,
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
        updateAccount,
    } = useSavedPaymentAccounts();
    const [isReleaseSheetOpen, setIsReleaseSheetOpen] = useState(false);
    const [isPaymentSheetOpen, setIsPaymentSheetOpen] = useState(false);

    useEffect(() => {
        void loadAccounts();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const releaseNeedsAccount =
        releaseMethod === 'ATM' || releaseMethod === 'Bank Transfer';
    const paymentNeedsAccount =
        paymentOption === 'ATM Deduction' || paymentOption === 'Bank Transfer';

    const releaseAccountLabel = useMemo(
        () =>
            accounts.find(
                (account) => Number(account.id) === Number(releaseAccountId),
            )?.label,
        [accounts, releaseAccountId],
    );
    const paymentAccountLabel = useMemo(
        () =>
            accounts.find(
                (account) => Number(account.id) === Number(paymentAccountId),
            )?.label,
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
                            Release Method
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Choose how you&apos;d like to receive your loan and
                            how your installments will be collected.
                        </p>
                    </div>

                    <LoanRequestSectionCard
                        title="Loan Disbursement"
                        description="Bank account details are required for ATM and Bank Transfer."
                        icon={Landmark}
                    >
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
                                        {(releaseMethod &&
                                            RELEASE_METHOD_LABELS[
                                                releaseMethod
                                            ]) ||
                                            releaseMethod ||
                                            'Not set'}
                                    </p>
                                </div>
                                {releaseNeedsAccount && (
                                    <p className="text-sm text-muted-foreground">
                                        {releaseAccountLabel ??
                                            (releaseAccountId !== null &&
                                            isLoadingAccounts
                                                ? 'Loading account…'
                                                : 'No account selected')}
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
                                {releaseMethod
                                    ? 'Change'
                                    : 'Choose release method'}
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
                        <InputError
                            message={formErrors.release_saved_account_id}
                        />
                    </LoanRequestSectionCard>

                    <LoanRequestSectionCard
                        title="Repayment Method"
                        description="This becomes the default for new loan requests -- you can still change it per request."
                        icon={Wallet}
                    >
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
                                        {(paymentOption &&
                                            PAYMENT_OPTION_LABELS[
                                                paymentOption
                                            ]) ||
                                            paymentOption ||
                                            'Not set'}
                                    </p>
                                </div>
                                {paymentNeedsAccount && (
                                    <p className="text-sm text-muted-foreground">
                                        {paymentAccountLabel ??
                                            (paymentAccountId !== null &&
                                            isLoadingAccounts
                                                ? 'Loading account…'
                                                : 'No account selected')}
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
                        <InputError
                            message={formErrors.payment_saved_account_id}
                        />
                    </LoanRequestSectionCard>
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
                    onUpdateAccount={updateAccount}
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
                    onUpdateAccount={updateAccount}
                />
            </SurfaceCard>
        </TabsContent>
    );
}
