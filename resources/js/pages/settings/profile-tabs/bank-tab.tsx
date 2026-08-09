import InputError from '@/components/input-error';
import { ReleaseAccountFields } from '@/components/loan-request/release-account-fields';
import { SurfaceCard } from '@/components/surface-card';
import { Checkbox } from '@/components/ui/checkbox';
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
    useSameReleaseAccount: boolean;
    setUseSameReleaseAccount: (value: boolean) => void;
    releaseBankName: string;
    setReleaseBankName: (value: string) => void;
    releaseAccountName: string;
    setReleaseAccountName: (value: string) => void;
    releaseAccountNumber: string;
    setReleaseAccountNumber: (value: string) => void;
    releaseAccountType: string;
    setReleaseAccountType: (value: string) => void;
    idTypeSelection: string;
    setIdTypeSelection: (value: string) => void;
    idTypeOther: string;
    setIdTypeOther: (value: string) => void;
};

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
    useSameReleaseAccount,
    setUseSameReleaseAccount,
    releaseBankName,
    setReleaseBankName,
    releaseAccountName,
    setReleaseAccountName,
    releaseAccountNumber,
    setReleaseAccountNumber,
    releaseAccountType,
    setReleaseAccountType,
    idTypeSelection,
    setIdTypeSelection,
    idTypeOther,
    setIdTypeOther,
}: Props) {
    return (
        <TabsContent value="bank" forceMount className="mt-0">
            <SurfaceCard variant="muted" padding="md" className="space-y-6">
                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">
                            Bank &amp; Payout
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Bank name, account name, account number, account
                            type, and release method are required before you can
                            start a loan request.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
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
                                    memberApplicationProfile?.payout_bank_name ??
                                    ''
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
                            <Label htmlFor="payout_account_name">
                                Account name
                            </Label>

                            <Input
                                id="payout_account_name"
                                className={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('payout_account_name') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                defaultValue={
                                    memberApplicationProfile?.payout_account_name ??
                                    ''
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
                            <Label htmlFor="payout_account_number">
                                Account number
                            </Label>

                            <Input
                                id="payout_account_number"
                                className={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('payout_account_number') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                defaultValue={
                                    memberApplicationProfile?.payout_account_number ??
                                    ''
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
                            <Label htmlFor="payout_account_type">
                                Account type
                            </Label>

                            <Input
                                id="payout_account_type"
                                className={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('payout_account_type') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                defaultValue={
                                    memberApplicationProfile?.payout_account_type ??
                                    ''
                                }
                                name="payout_account_type"
                                placeholder="e.g. Savings"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.payout_account_type}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="release_method">
                                Release method
                            </Label>

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
                            <>
                                <input
                                    type="hidden"
                                    name="release_uses_payout_account"
                                    value={useSameReleaseAccount ? '1' : '0'}
                                />
                                <input
                                    type="hidden"
                                    name="release_bank_name"
                                    value={releaseBankName}
                                />
                                <input
                                    type="hidden"
                                    name="release_account_name"
                                    value={releaseAccountName}
                                />
                                <input
                                    type="hidden"
                                    name="release_account_number"
                                    value={releaseAccountNumber}
                                />
                                <input
                                    type="hidden"
                                    name="release_account_type"
                                    value={releaseAccountType}
                                />
                                <ReleaseAccountFields
                                    idPrefix="release_account"
                                    useSameAccount={useSameReleaseAccount}
                                    onToggleSameAccount={
                                        setUseSameReleaseAccount
                                    }
                                    values={{
                                        bank_name: releaseBankName,
                                        account_name: releaseAccountName,
                                        account_number: releaseAccountNumber,
                                        account_type: releaseAccountType,
                                    }}
                                    errors={{
                                        bank_name: formErrors.release_bank_name,
                                        account_name:
                                            formErrors.release_account_name,
                                        account_number:
                                            formErrors.release_account_number,
                                        account_type:
                                            formErrors.release_account_type,
                                    }}
                                    missingFields={{
                                        bank_name:
                                            isFieldMissing('release_bank_name'),
                                        account_name: isFieldMissing(
                                            'release_account_name',
                                        ),
                                        account_number: isFieldMissing(
                                            'release_account_number',
                                        ),
                                        account_type: isFieldMissing(
                                            'release_account_type',
                                        ),
                                    }}
                                    missingFieldClassName={MISSING_FIELD_CLASS}
                                    onChange={(field, value) => {
                                        if (field === 'bank_name')
                                            setReleaseBankName(value);
                                        if (field === 'account_name')
                                            setReleaseAccountName(value);
                                        if (field === 'account_number')
                                            setReleaseAccountNumber(value);
                                        if (field === 'account_type')
                                            setReleaseAccountType(value);
                                    }}
                                />
                            </>
                        ) : null}
                    </div>
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
                                className="mt-1 block w-full"
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
                                    className="mt-1 w-full"
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
                                className="mt-1 block w-full"
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
                                    className="mt-1 block w-full pr-10"
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
                                    className="mt-1 block w-full pr-10"
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
