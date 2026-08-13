import { useState } from 'react';
import InputError from '@/components/input-error';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
    memberDisplayName: string;
    releaseMethod: string;
    setReleaseMethod: (value: string) => void;
    isOwnAtmCard: boolean;
    setIsOwnAtmCard: (value: boolean) => void;
    atmHolderName: string;
    setAtmHolderName: (value: string) => void;
    idTypeSelection: string;
    setIdTypeSelection: (value: string) => void;
    idTypeOther: string;
    setIdTypeOther: (value: string) => void;
    paymentOption: string;
    setPaymentOption: (value: string) => void;
    isOwnPaymentAtmCard: boolean;
    setIsOwnPaymentAtmCard: (value: boolean) => void;
    paymentAtmHolderName: string;
    setPaymentAtmHolderName: (value: string) => void;
    bankingValues: Record<string, string>;
    setBankingValue: (key: string, value: string) => void;
};

// A single controlled bank/ATM detail input, shared by the release
// (payout_*) and repayment (payment_*) sides. readOnly (not disabled) when
// mirrored by the "use same details" checkbox -- disabled inputs are
// excluded from native form submission, readOnly inputs still submit.
function BankDetailField({
    fieldKey,
    label,
    placeholder,
    value,
    onChange,
    error,
    missing,
    readOnly = false,
}: {
    fieldKey: string;
    label: string;
    placeholder: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    missing?: boolean;
    readOnly?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={fieldKey}>{label}</Label>

            <Input
                id={fieldKey}
                className={cn(
                    'mt-1 block w-full',
                    missing && MISSING_FIELD_CLASS,
                    readOnly && 'bg-muted/50',
                )}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                name={fieldKey}
                placeholder={placeholder}
                readOnly={readOnly}
            />

            <InputError className="mt-2" message={error} />
        </div>
    );
}

// The 4 base bank account fields, shared between the release side
// (payout_*, required for ATM/Bank Transfer) and the repayment side
// (payment_*, required for ATM Deduction) -- same shape either way, just a
// different field-name prefix and requiredness condition (see
// ProfileUpdateRequest::rules()).
function BankAccountFields({
    prefix,
    formErrors,
    bankingValues,
    setBankingValue,
    isFieldMissing,
    readOnly = false,
}: {
    prefix: 'payout' | 'payment';
    formErrors: Record<string, string>;
    bankingValues: Record<string, string>;
    setBankingValue: (key: string, value: string) => void;
    isFieldMissing: (field: string) => boolean;
    readOnly?: boolean;
}) {
    const fields = [
        {
            key: `${prefix}_bank_name`,
            label: 'Bank name',
            placeholder: 'Bank name',
        },
        {
            key: `${prefix}_account_name`,
            label: 'Account name',
            placeholder: 'Account name',
        },
        {
            key: `${prefix}_account_number`,
            label: 'Account number',
            placeholder: 'Account number',
        },
        {
            key: `${prefix}_account_type`,
            label: 'Account type',
            placeholder: 'e.g. Savings',
        },
    ] as const;

    return (
        <>
            {fields.map(({ key, label, placeholder }) => (
                <BankDetailField
                    key={key}
                    fieldKey={key}
                    label={label}
                    placeholder={placeholder}
                    value={bankingValues[key] ?? ''}
                    onChange={(value) => setBankingValue(key, value)}
                    error={formErrors[key]}
                    missing={isFieldMissing(key)}
                    readOnly={readOnly}
                />
            ))}
        </>
    );
}

// Payout (release) field -> its payment (repayment) counterpart, used by
// the "use the same details" checkbox to mirror values live while checked.
const PAYOUT_TO_PAYMENT_MIRROR_FIELDS: [string, string][] = [
    ['payout_bank_name', 'payment_bank_name'],
    ['payout_account_name', 'payment_account_name'],
    ['payout_account_number', 'payment_account_number'],
    ['payout_account_type', 'payment_account_type'],
    ['payout_atm_number', 'payment_atm_number'],
    ['payout_bank_branch', 'payment_bank_branch'],
];

export function BankTab({
    formErrors,
    memberApplicationProfile,
    isFieldMissing,
    memberDisplayName,
    releaseMethod,
    setReleaseMethod,
    isOwnAtmCard,
    setIsOwnAtmCard,
    atmHolderName,
    setAtmHolderName,
    idTypeSelection,
    setIdTypeSelection,
    idTypeOther,
    setIdTypeOther,
    paymentOption,
    setPaymentOption,
    isOwnPaymentAtmCard,
    setIsOwnPaymentAtmCard,
    paymentAtmHolderName,
    setPaymentAtmHolderName,
    bankingValues,
    setBankingValue,
}: Props) {
    const hasSavedBankDetails = Boolean(
        memberApplicationProfile?.payout_bank_name ||
        memberApplicationProfile?.payout_account_name ||
        memberApplicationProfile?.payout_account_number ||
        memberApplicationProfile?.payout_account_type,
    );
    const [showOptionalBankDetails, setShowOptionalBankDetails] =
        useState(hasSavedBankDetails);

    const showReleaseBankFields =
        releaseMethod === 'ATM' || releaseMethod === 'Bank Transfer';
    const showPaymentAtmFields = paymentOption === 'ATM Deduction';
    const canUseSameDetails =
        releaseMethod === 'ATM' && paymentOption === 'ATM Deduction';

    const [useSameDetailsChecked, setUseSameDetailsChecked] = useState(false);
    // Derived rather than synced via effect: once the checkbox's
    // precondition (both channels ATM) stops holding, it should just read
    // as unchecked again without a render-triggering effect.
    const useSameDetails = canUseSameDetails && useSameDetailsChecked;

    const handleUseSameDetailsChange = (checked: boolean) => {
        setUseSameDetailsChecked(checked);

        if (checked) {
            PAYOUT_TO_PAYMENT_MIRROR_FIELDS.forEach(
                ([payoutField, paymentField]) => {
                    setBankingValue(
                        paymentField,
                        bankingValues[payoutField] ?? '',
                    );
                },
            );
            setIsOwnPaymentAtmCard(isOwnAtmCard);
            setPaymentAtmHolderName(atmHolderName);
        }
    };

    const handlePayoutBankingValueChange = (key: string, value: string) => {
        setBankingValue(key, value);

        if (useSameDetails) {
            const mirror = PAYOUT_TO_PAYMENT_MIRROR_FIELDS.find(
                ([payoutField]) => payoutField === key,
            );

            if (mirror) {
                setBankingValue(mirror[1], value);
            }
        }
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

                    <div className="grid gap-2">
                        <Label htmlFor="release_method">Release method</Label>

                        <Select
                            value={releaseMethod || undefined}
                            onValueChange={(value) => {
                                setReleaseMethod(value);
                            }}
                        >
                            <SelectTrigger
                                id="release_method"
                                className={cn(
                                    'mt-1 w-full',
                                    isFieldMissing('release_method') &&
                                        MISSING_FIELD_CLASS,
                                )}
                            >
                                <SelectValue placeholder="Select release method" />
                            </SelectTrigger>
                            <SelectContent>
                                {RELEASE_METHOD_OPTIONS.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="release_method"
                            value={releaseMethod}
                        />

                        <InputError
                            className="mt-2"
                            message={formErrors.release_method}
                        />
                    </div>

                    {showReleaseBankFields && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <BankAccountFields
                                prefix="payout"
                                formErrors={formErrors}
                                bankingValues={bankingValues}
                                setBankingValue={handlePayoutBankingValueChange}
                                isFieldMissing={isFieldMissing}
                            />
                        </div>
                    )}

                    {releaseMethod === 'ATM' && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <BankDetailField
                                fieldKey="payout_bank_branch"
                                label="Bank branch"
                                placeholder="Bank branch"
                                value={bankingValues.payout_bank_branch ?? ''}
                                onChange={(value) =>
                                    handlePayoutBankingValueChange(
                                        'payout_bank_branch',
                                        value,
                                    )
                                }
                                error={formErrors.payout_bank_branch}
                            />

                            <BankDetailField
                                fieldKey="payout_atm_number"
                                label="ATM card number"
                                placeholder="ATM card number"
                                value={bankingValues.payout_atm_number ?? ''}
                                onChange={(value) =>
                                    handlePayoutBankingValueChange(
                                        'payout_atm_number',
                                        value,
                                    )
                                }
                                error={formErrors.payout_atm_number}
                                missing={isFieldMissing('payout_atm_number')}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="payout_atm_holder_name">
                                    ATM card holder name{' '}
                                    <span className="text-muted-foreground">
                                        (if not you)
                                    </span>
                                </Label>

                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="payout_atm_holder_name_is_own"
                                        checked={isOwnAtmCard}
                                        onCheckedChange={(checked) => {
                                            const next = checked === true;
                                            setIsOwnAtmCard(next);
                                            const nextName = next
                                                ? memberDisplayName
                                                : '';
                                            setAtmHolderName(nextName);

                                            if (useSameDetails) {
                                                setIsOwnPaymentAtmCard(next);
                                                setPaymentAtmHolderName(
                                                    nextName,
                                                );
                                            }
                                        }}
                                    />
                                    <Label
                                        htmlFor="payout_atm_holder_name_is_own"
                                        className="text-sm font-normal"
                                    >
                                        This is my own ATM card
                                    </Label>
                                </div>

                                {isOwnAtmCard ? (
                                    <input
                                        type="hidden"
                                        name="payout_atm_holder_name"
                                        value={memberDisplayName}
                                    />
                                ) : (
                                    <>
                                        <Input
                                            id="payout_atm_holder_name"
                                            className={cn(
                                                'mt-1 block w-full',
                                                isFieldMissing(
                                                    'payout_atm_holder_name',
                                                ) && MISSING_FIELD_CLASS,
                                            )}
                                            value={atmHolderName}
                                            onChange={(event) => {
                                                setAtmHolderName(
                                                    event.target.value,
                                                );

                                                if (useSameDetails) {
                                                    setPaymentAtmHolderName(
                                                        event.target.value,
                                                    );
                                                }
                                            }}
                                            name="payout_atm_holder_name"
                                            placeholder="ATM card holder name"
                                        />

                                        <InputError
                                            className="mt-2"
                                            message={
                                                formErrors.payout_atm_holder_name
                                            }
                                        />
                                    </>
                                )}
                            </div>
                        </div>
                    )}

                    {releaseMethod && !showReleaseBankFields ? (
                        <Collapsible
                            open={showOptionalBankDetails}
                            onOpenChange={setShowOptionalBankDetails}
                        >
                            {!showOptionalBankDetails ? (
                                <CollapsibleTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="link"
                                        className="h-auto p-0 text-sm"
                                    >
                                        + Add bank account details (optional)
                                    </Button>
                                </CollapsibleTrigger>
                            ) : null}
                            <CollapsibleContent className="grid gap-4 pt-4 md:grid-cols-2">
                                <p className="text-xs text-muted-foreground md:col-span-2">
                                    Optional -- add a bank account on file for
                                    future use. Not required while your release
                                    method is {releaseMethod}.
                                </p>
                                <BankAccountFields
                                    prefix="payout"
                                    formErrors={formErrors}
                                    bankingValues={bankingValues}
                                    setBankingValue={
                                        handlePayoutBankingValueChange
                                    }
                                    isFieldMissing={isFieldMissing}
                                />
                            </CollapsibleContent>
                        </Collapsible>
                    ) : null}
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

                    <div className="grid gap-2">
                        <Label htmlFor="payment_option">Payment option</Label>

                        <Select
                            value={paymentOption || undefined}
                            onValueChange={(value) => {
                                setPaymentOption(value);
                            }}
                        >
                            <SelectTrigger
                                id="payment_option"
                                className={cn(
                                    'mt-1 w-full',
                                    isFieldMissing('payment_option') &&
                                        MISSING_FIELD_CLASS,
                                )}
                            >
                                <SelectValue placeholder="Select payment option" />
                            </SelectTrigger>
                            <SelectContent>
                                {PAYMENT_OPTION_OPTIONS.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="payment_option"
                            value={paymentOption}
                        />

                        <InputError
                            className="mt-2"
                            message={formErrors.payment_option}
                        />
                    </div>

                    {canUseSameDetails && (
                        <div className="flex items-start gap-3 rounded-md border border-border/50 bg-muted/10 p-3">
                            <Checkbox
                                id="use_same_payment_details"
                                checked={useSameDetails}
                                onCheckedChange={(checked) =>
                                    handleUseSameDetailsChange(checked === true)
                                }
                            />
                            <Label
                                htmlFor="use_same_payment_details"
                                className="text-sm leading-snug font-normal"
                            >
                                Use the same ATM/bank details for repayment as
                                for release
                            </Label>
                        </div>
                    )}

                    {showPaymentAtmFields && (
                        <>
                            <div className="grid gap-4 md:grid-cols-2">
                                <BankAccountFields
                                    prefix="payment"
                                    formErrors={formErrors}
                                    bankingValues={bankingValues}
                                    setBankingValue={setBankingValue}
                                    isFieldMissing={isFieldMissing}
                                    readOnly={useSameDetails}
                                />
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <BankDetailField
                                    fieldKey="payment_bank_branch"
                                    label="Bank branch"
                                    placeholder="Bank branch"
                                    value={
                                        bankingValues.payment_bank_branch ?? ''
                                    }
                                    onChange={(value) =>
                                        setBankingValue(
                                            'payment_bank_branch',
                                            value,
                                        )
                                    }
                                    error={formErrors.payment_bank_branch}
                                    readOnly={useSameDetails}
                                />

                                <BankDetailField
                                    fieldKey="payment_atm_number"
                                    label="ATM card number"
                                    placeholder="ATM card number"
                                    value={
                                        bankingValues.payment_atm_number ?? ''
                                    }
                                    onChange={(value) =>
                                        setBankingValue(
                                            'payment_atm_number',
                                            value,
                                        )
                                    }
                                    error={formErrors.payment_atm_number}
                                    missing={isFieldMissing(
                                        'payment_atm_number',
                                    )}
                                    readOnly={useSameDetails}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="payment_atm_holder_name">
                                        ATM card holder name{' '}
                                        <span className="text-muted-foreground">
                                            (if not you)
                                        </span>
                                    </Label>

                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="payment_atm_holder_name_is_own"
                                            checked={isOwnPaymentAtmCard}
                                            disabled={useSameDetails}
                                            onCheckedChange={(checked) => {
                                                const next = checked === true;
                                                setIsOwnPaymentAtmCard(next);
                                                setPaymentAtmHolderName(
                                                    next
                                                        ? memberDisplayName
                                                        : '',
                                                );
                                            }}
                                        />
                                        <Label
                                            htmlFor="payment_atm_holder_name_is_own"
                                            className="text-sm font-normal"
                                        >
                                            This is my own ATM card
                                        </Label>
                                    </div>

                                    {isOwnPaymentAtmCard ? (
                                        <input
                                            type="hidden"
                                            name="payment_atm_holder_name"
                                            value={paymentAtmHolderName}
                                        />
                                    ) : (
                                        <>
                                            <Input
                                                id="payment_atm_holder_name"
                                                className={cn(
                                                    'mt-1 block w-full',
                                                    isFieldMissing(
                                                        'payment_atm_holder_name',
                                                    ) && MISSING_FIELD_CLASS,
                                                    useSameDetails &&
                                                        'bg-muted/50',
                                                )}
                                                value={paymentAtmHolderName}
                                                onChange={(event) =>
                                                    setPaymentAtmHolderName(
                                                        event.target.value,
                                                    )
                                                }
                                                name="payment_atm_holder_name"
                                                placeholder="ATM card holder name"
                                                readOnly={useSameDetails}
                                            />

                                            <InputError
                                                className="mt-2"
                                                message={
                                                    formErrors.payment_atm_holder_name
                                                }
                                            />
                                        </>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </div>

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
