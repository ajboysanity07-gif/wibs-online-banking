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
};

// The 4 base payout account fields, shared between the required Bank
// Transfer block and the optional "add bank details for later" disclosure
// shown for the other release methods -- same fields either way, just
// different requiredness depending on release_method (see
// ProfileUpdateRequest::rules()).
function PayoutBankAccountFields({
    formErrors,
    memberApplicationProfile,
    isFieldMissing,
}: {
    formErrors: Record<string, string>;
    memberApplicationProfile: MemberApplicationProfileData | null;
    isFieldMissing: (field: string) => boolean;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="payout_bank_name">Bank name</Label>

                <Input
                    id="payout_bank_name"
                    className={cn(
                        'mt-1 block w-full',
                        isFieldMissing('payout_bank_name') &&
                            MISSING_FIELD_CLASS,
                    )}
                    defaultValue={
                        memberApplicationProfile?.payout_bank_name ?? ''
                    }
                    name="payout_bank_name"
                    placeholder="Bank name"
                />

                <InputError
                    className="mt-2"
                    message={formErrors.payout_bank_name}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="payout_account_name">Account name</Label>

                <Input
                    id="payout_account_name"
                    className={cn(
                        'mt-1 block w-full',
                        isFieldMissing('payout_account_name') &&
                            MISSING_FIELD_CLASS,
                    )}
                    defaultValue={
                        memberApplicationProfile?.payout_account_name ?? ''
                    }
                    name="payout_account_name"
                    placeholder="Account name"
                />

                <InputError
                    className="mt-2"
                    message={formErrors.payout_account_name}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="payout_account_number">Account number</Label>

                <Input
                    id="payout_account_number"
                    className={cn(
                        'mt-1 block w-full',
                        isFieldMissing('payout_account_number') &&
                            MISSING_FIELD_CLASS,
                    )}
                    defaultValue={
                        memberApplicationProfile?.payout_account_number ?? ''
                    }
                    name="payout_account_number"
                    placeholder="Account number"
                />

                <InputError
                    className="mt-2"
                    message={formErrors.payout_account_number}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="payout_account_type">Account type</Label>

                <Input
                    id="payout_account_type"
                    className={cn(
                        'mt-1 block w-full',
                        isFieldMissing('payout_account_type') &&
                            MISSING_FIELD_CLASS,
                    )}
                    defaultValue={
                        memberApplicationProfile?.payout_account_type ?? ''
                    }
                    name="payout_account_type"
                    placeholder="e.g. Savings"
                />

                <InputError
                    className="mt-2"
                    message={formErrors.payout_account_type}
                />
            </div>
        </>
    );
}

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
}: Props) {
    const hasSavedBankDetails = Boolean(
        memberApplicationProfile?.payout_bank_name ||
        memberApplicationProfile?.payout_account_name ||
        memberApplicationProfile?.payout_account_number ||
        memberApplicationProfile?.payout_account_type,
    );
    const [showOptionalBankDetails, setShowOptionalBankDetails] =
        useState(hasSavedBankDetails);

    return (
        <TabsContent value="bank" forceMount className="mt-0">
            <SurfaceCard variant="muted" padding="md" className="space-y-6">
                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">
                            Release Method
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Choose how you'd like to receive your loan. Bank
                            account details are required only for Bank Transfer.
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

                    <div className="grid gap-4 md:grid-cols-2">
                        {releaseMethod === 'ATM' ? (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="payout_bank_branch">
                                        Bank branch
                                    </Label>

                                    <Input
                                        id="payout_bank_branch"
                                        className="mt-1 block w-full"
                                        defaultValue={
                                            memberApplicationProfile?.payout_bank_branch ??
                                            ''
                                        }
                                        name="payout_bank_branch"
                                        placeholder="Bank branch"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={formErrors.payout_bank_branch}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="payout_atm_number">
                                        ATM card number
                                    </Label>

                                    <Input
                                        id="payout_atm_number"
                                        className={cn(
                                            'mt-1 block w-full',
                                            isFieldMissing(
                                                'payout_atm_number',
                                            ) && MISSING_FIELD_CLASS,
                                        )}
                                        defaultValue={
                                            memberApplicationProfile?.payout_atm_number ??
                                            ''
                                        }
                                        name="payout_atm_number"
                                        placeholder="ATM card number"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={formErrors.payout_atm_number}
                                    />
                                </div>

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
                                                setAtmHolderName(
                                                    next
                                                        ? memberDisplayName
                                                        : '',
                                                );
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
                                                onChange={(event) =>
                                                    setAtmHolderName(
                                                        event.target.value,
                                                    )
                                                }
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
                            </>
                        ) : null}

                        {releaseMethod === 'Bank Transfer' ? (
                            <PayoutBankAccountFields
                                formErrors={formErrors}
                                memberApplicationProfile={
                                    memberApplicationProfile
                                }
                                isFieldMissing={isFieldMissing}
                            />
                        ) : null}
                    </div>

                    {releaseMethod && releaseMethod !== 'Bank Transfer' ? (
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
                                <PayoutBankAccountFields
                                    formErrors={formErrors}
                                    memberApplicationProfile={
                                        memberApplicationProfile
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
