import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type ReleaseAccountValues = {
    bank_name: string;
    account_name: string;
    account_number: string;
    account_type: string;
};

export type ReleaseAccountErrors = Partial<
    Record<keyof ReleaseAccountValues, string>
>;

export type ReleaseAccountMissingFields = Partial<
    Record<keyof ReleaseAccountValues, boolean>
>;

type Props = {
    idPrefix: string;
    useSameAccount: boolean;
    onToggleSameAccount: (useSameAccount: boolean) => void;
    values: ReleaseAccountValues;
    errors?: ReleaseAccountErrors;
    missingFields?: ReleaseAccountMissingFields;
    missingFieldClassName?: string;
    onChange: (field: keyof ReleaseAccountValues, value: string) => void;
};

// "Release funds to this same account" defaults to checked -- unchecking it
// reveals a separate account (bank name/account name/number/type) to
// release funds to, distinct from the member's main payout account.
export function ReleaseAccountFields({
    idPrefix,
    useSameAccount,
    onToggleSameAccount,
    values,
    errors,
    missingFields,
    missingFieldClassName,
    onChange,
}: Props) {
    return (
        <div className="grid gap-3 sm:col-span-2">
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`${idPrefix}_same_account`}
                    checked={useSameAccount}
                    onCheckedChange={(checked) =>
                        onToggleSameAccount(checked === true)
                    }
                />
                <Label
                    htmlFor={`${idPrefix}_same_account`}
                    className="text-sm font-normal"
                >
                    Release funds to this same account
                </Label>
            </div>

            {!useSameAccount && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}_bank_name`}>
                            Release bank name
                        </Label>
                        <Input
                            id={`${idPrefix}_bank_name`}
                            className={cn(
                                missingFields?.bank_name &&
                                    missingFieldClassName,
                            )}
                            value={values.bank_name}
                            onChange={(event) =>
                                onChange('bank_name', event.target.value)
                            }
                        />
                        <InputError message={errors?.bank_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}_account_name`}>
                            Release account name
                        </Label>
                        <Input
                            id={`${idPrefix}_account_name`}
                            className={cn(
                                missingFields?.account_name &&
                                    missingFieldClassName,
                            )}
                            value={values.account_name}
                            onChange={(event) =>
                                onChange('account_name', event.target.value)
                            }
                        />
                        <InputError message={errors?.account_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}_account_number`}>
                            Release account number
                        </Label>
                        <Input
                            id={`${idPrefix}_account_number`}
                            className={cn(
                                missingFields?.account_number &&
                                    missingFieldClassName,
                            )}
                            value={values.account_number}
                            onChange={(event) =>
                                onChange('account_number', event.target.value)
                            }
                        />
                        <InputError message={errors?.account_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${idPrefix}_account_type`}>
                            Release account type
                        </Label>
                        <Input
                            id={`${idPrefix}_account_type`}
                            className={cn(
                                missingFields?.account_type &&
                                    missingFieldClassName,
                            )}
                            placeholder="e.g. Savings"
                            value={values.account_type}
                            onChange={(event) =>
                                onChange('account_type', event.target.value)
                            }
                        />
                        <InputError message={errors?.account_type} />
                    </div>
                </div>
            )}
        </div>
    );
}
