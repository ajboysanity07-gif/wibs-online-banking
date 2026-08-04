import axios from 'axios';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { AtmHolderCheckboxField } from '@/components/loan-request/atm-holder-checkbox-field';
import { ReleaseAccountFields } from '@/components/loan-request/release-account-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import client from '@/lib/api/client';
import { cn } from '@/lib/utils';

export const ID_TYPE_OTHER_VALUE = 'Others';
const ID_TYPE_OPTIONS = ['SSS', 'GSIS', 'TIN', 'Phil ID', ID_TYPE_OTHER_VALUE];
const RELEASE_METHOD_OPTIONS = ['ATM', 'Bank Transfer', 'Check', 'Cash'];

export type LoanPrerequisiteProfile = {
    payout_bank_name: string | null;
    payout_account_name: string | null;
    payout_account_number: string | null;
    payout_account_type: string | null;
    release_method: string | null;
    payout_atm_number: string | null;
    payout_bank_branch: string | null;
    payout_atm_holder_name: string | null;
    release_uses_payout_account: boolean | null;
    release_bank_name: string | null;
    release_account_name: string | null;
    release_account_number: string | null;
    release_account_type: string | null;
    source_of_fund_wealth: string | null;
    id_type: string | null;
    id_type_other: string | null;
    id_number: string | null;
    height_cm: string | null;
    weight_kg: string | null;
};

type FormState = Record<keyof LoanPrerequisiteProfile, string>;

const toFormState = (profile: LoanPrerequisiteProfile): FormState => ({
    payout_bank_name: profile.payout_bank_name ?? '',
    payout_account_name: profile.payout_account_name ?? '',
    payout_account_number: profile.payout_account_number ?? '',
    payout_account_type: profile.payout_account_type ?? '',
    release_method: profile.release_method ?? '',
    payout_atm_number: profile.payout_atm_number ?? '',
    payout_bank_branch: profile.payout_bank_branch ?? '',
    payout_atm_holder_name: profile.payout_atm_holder_name ?? '',
    release_uses_payout_account:
        (profile.release_uses_payout_account ?? true) ? '1' : '0',
    release_bank_name: profile.release_bank_name ?? '',
    release_account_name: profile.release_account_name ?? '',
    release_account_number: profile.release_account_number ?? '',
    release_account_type: profile.release_account_type ?? '',
    source_of_fund_wealth: profile.source_of_fund_wealth ?? '',
    id_type: profile.id_type ?? '',
    id_type_other: profile.id_type_other ?? '',
    id_number: profile.id_number ?? '',
    height_cm: profile.height_cm ?? '',
    weight_kg: profile.weight_kg ?? '',
});

type Props = {
    open: boolean;
    profile: LoanPrerequisiteProfile;
    applicantFullName?: string;
    onSaved: (profile: LoanPrerequisiteProfile) => void;
};

export function LoanRequestPrerequisiteModal({
    open,
    profile,
    applicantFullName,
    onSaved,
}: Props) {
    const [data, setData] = useState<FormState>(() => toFormState(profile));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const setField = (field: keyof FormState, value: string) => {
        setData((current) => ({ ...current, [field]: value }));
    };

    const handleSubmit = async () => {
        setProcessing(true);
        setErrors({});

        try {
            const response = await client.post<{
                ok: boolean;
                data: {
                    loanPrerequisitesMet: boolean;
                    loanPrerequisiteProfile: LoanPrerequisiteProfile;
                };
            }>('/client/loans/request/prerequisites', data);

            onSaved(response.data.data.loanPrerequisiteProfile);
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const rawErrors = (error.response.data?.errors ?? {}) as Record<
                    string,
                    string[] | string
                >;
                const flattened: Record<string, string> = {};

                for (const [field, message] of Object.entries(rawErrors)) {
                    flattened[field] = Array.isArray(message)
                        ? message[0]
                        : message;
                }

                setErrors(flattened);
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={open}>
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-xl [&>button:last-child]:hidden"
                onEscapeKeyDown={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>
                        Complete your Bank &amp; Payout and ID details
                    </DialogTitle>
                    <DialogDescription>
                        These details are required before you can request a
                        loan. Fill them in now to continue -- you can also
                        update them later in Settings.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Bank &amp; Payout
                        </h3>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="prereq_payout_bank_name">
                                    Bank name
                                </Label>
                                <Input
                                    id="prereq_payout_bank_name"
                                    value={data.payout_bank_name}
                                    onChange={(event) =>
                                        setField(
                                            'payout_bank_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors.payout_bank_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="prereq_payout_account_name">
                                    Account name
                                </Label>
                                <Input
                                    id="prereq_payout_account_name"
                                    value={data.payout_account_name}
                                    onChange={(event) =>
                                        setField(
                                            'payout_account_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.payout_account_name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="prereq_payout_account_number">
                                    Account number
                                </Label>
                                <Input
                                    id="prereq_payout_account_number"
                                    value={data.payout_account_number}
                                    onChange={(event) =>
                                        setField(
                                            'payout_account_number',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.payout_account_number}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="prereq_payout_account_type">
                                    Account type
                                </Label>
                                <Input
                                    id="prereq_payout_account_type"
                                    placeholder="e.g. Savings"
                                    value={data.payout_account_type}
                                    onChange={(event) =>
                                        setField(
                                            'payout_account_type',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.payout_account_type}
                                />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="prereq_release_method">
                                    Release method
                                </Label>
                                <Select
                                    value={data.release_method || undefined}
                                    onValueChange={(value) =>
                                        setField('release_method', value)
                                    }
                                >
                                    <SelectTrigger id="prereq_release_method">
                                        <SelectValue placeholder="Select release method" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {RELEASE_METHOD_OPTIONS.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option}
                                                    value={option}
                                                >
                                                    {option}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.release_method} />
                            </div>

                            {data.release_method === 'ATM' ? (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="prereq_payout_bank_branch">
                                            Bank branch
                                        </Label>
                                        <Input
                                            id="prereq_payout_bank_branch"
                                            value={data.payout_bank_branch}
                                            onChange={(event) =>
                                                setField(
                                                    'payout_bank_branch',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.payout_bank_branch}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="prereq_payout_atm_number">
                                            ATM card number
                                        </Label>
                                        <Input
                                            id="prereq_payout_atm_number"
                                            placeholder="ATM card number"
                                            value={data.payout_atm_number}
                                            onChange={(event) =>
                                                setField(
                                                    'payout_atm_number',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.payout_atm_number}
                                        />
                                    </div>

                                    <AtmHolderCheckboxField
                                        id="prereq_payout_atm_holder_name"
                                        label="ATM card holder name"
                                        value={data.payout_atm_holder_name}
                                        applicantFullName={
                                            applicantFullName ?? ''
                                        }
                                        error={errors.payout_atm_holder_name}
                                        onChange={(value) =>
                                            setField(
                                                'payout_atm_holder_name',
                                                value,
                                            )
                                        }
                                    />
                                </>
                            ) : null}

                            {data.release_method === 'Bank Transfer' ? (
                                <ReleaseAccountFields
                                    idPrefix="prereq_release_account"
                                    useSameAccount={
                                        data.release_uses_payout_account !== '0'
                                    }
                                    onToggleSameAccount={(useSameAccount) =>
                                        setField(
                                            'release_uses_payout_account',
                                            useSameAccount ? '1' : '0',
                                        )
                                    }
                                    values={{
                                        bank_name: data.release_bank_name,
                                        account_name: data.release_account_name,
                                        account_number:
                                            data.release_account_number,
                                        account_type: data.release_account_type,
                                    }}
                                    errors={{
                                        bank_name: errors.release_bank_name,
                                        account_name:
                                            errors.release_account_name,
                                        account_number:
                                            errors.release_account_number,
                                        account_type:
                                            errors.release_account_type,
                                    }}
                                    onChange={(field, value) => {
                                        const fieldMap = {
                                            bank_name: 'release_bank_name',
                                            account_name:
                                                'release_account_name',
                                            account_number:
                                                'release_account_number',
                                            account_type:
                                                'release_account_type',
                                        } as const;

                                        setField(fieldMap[field], value);
                                    }}
                                />
                            ) : null}
                        </div>
                    </div>

                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Source of Funds &amp; Government ID
                        </h3>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="prereq_source_of_fund_wealth">
                                    Source of fund / wealth
                                </Label>
                                <Input
                                    id="prereq_source_of_fund_wealth"
                                    placeholder="e.g. Salary, business income"
                                    value={data.source_of_fund_wealth}
                                    onChange={(event) =>
                                        setField(
                                            'source_of_fund_wealth',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.source_of_fund_wealth}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="prereq_id_type">
                                    Government ID type
                                </Label>
                                <Select
                                    value={data.id_type || undefined}
                                    onValueChange={(value) => {
                                        setField('id_type', value);

                                        if (value !== ID_TYPE_OTHER_VALUE) {
                                            setField('id_type_other', '');
                                        }
                                    }}
                                >
                                    <SelectTrigger id="prereq_id_type">
                                        <SelectValue placeholder="Select ID type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ID_TYPE_OPTIONS.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={option}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.id_type} />
                            </div>

                            {data.id_type === ID_TYPE_OTHER_VALUE ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="prereq_id_type_other">
                                        Specify ID type
                                    </Label>
                                    <Input
                                        id="prereq_id_type_other"
                                        value={data.id_type_other}
                                        onChange={(event) =>
                                            setField(
                                                'id_type_other',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.id_type_other}
                                    />
                                </div>
                            ) : null}

                            <div className="grid gap-2">
                                <Label htmlFor="prereq_id_number">
                                    ID number
                                </Label>
                                <Input
                                    id="prereq_id_number"
                                    value={data.id_number}
                                    onChange={(event) =>
                                        setField(
                                            'id_number',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors.id_number} />
                            </div>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Physical details
                        </h3>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="prereq_height_cm">
                                    Height (cm)
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="prereq_height_cm"
                                        inputMode="numeric"
                                        value={data.height_cm}
                                        onChange={(event) =>
                                            setField(
                                                'height_cm',
                                                event.target.value,
                                            )
                                        }
                                        className="pr-10"
                                    />
                                    <span
                                        className={cn(
                                            'pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground',
                                        )}
                                    >
                                        cm
                                    </span>
                                </div>
                                <InputError message={errors.height_cm} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="prereq_weight_kg">
                                    Weight (kg)
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="prereq_weight_kg"
                                        inputMode="numeric"
                                        value={data.weight_kg}
                                        onChange={(event) =>
                                            setField(
                                                'weight_kg',
                                                event.target.value,
                                            )
                                        }
                                        className="pr-10"
                                    />
                                    <span
                                        className={cn(
                                            'pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground',
                                        )}
                                    >
                                        kg
                                    </span>
                                </div>
                                <InputError message={errors.weight_kg} />
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button onClick={handleSubmit} disabled={processing}>
                        {processing ? 'Saving...' : 'Save and continue'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
