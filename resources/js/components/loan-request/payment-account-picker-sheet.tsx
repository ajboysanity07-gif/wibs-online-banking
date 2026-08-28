import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type {
    SavedPaymentAccount,
    SavedPaymentAccountFormPayload,
} from '@/hooks/use-saved-payment-accounts';

export type PaymentMethodOption = {
    value: string;
    label: string;
    needsAccount: boolean;
};

type PaymentAccountPickerSheetProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    accounts: SavedPaymentAccount[];
    methodOptions: PaymentMethodOption[];
    initialMethod?: string | null;
    initialAccountId?: number | null;
    isSaving: boolean;
    onConfirm: (method: string, accountId: number | null) => Promise<boolean>;
    onCreateAccount: (
        payload: SavedPaymentAccountFormPayload,
    ) => Promise<SavedPaymentAccount | null>;
};

export function PaymentAccountPickerSheet({
    open,
    onOpenChange,
    title,
    description,
    accounts,
    methodOptions,
    initialMethod,
    initialAccountId,
    isSaving,
    onConfirm,
    onCreateAccount,
}: PaymentAccountPickerSheetProps) {
    const [method, setMethod] = useState(
        initialMethod ?? methodOptions[0]?.value ?? '',
    );
    const [accountId, setAccountId] = useState<number | null>(
        initialAccountId ?? null,
    );
    const [isAddingAccount, setIsAddingAccount] = useState(false);
    const [newAccount, setNewAccount] =
        useState<SavedPaymentAccountFormPayload>({
            label: '',
            bank_name: '',
            account_name: '',
            account_number: '',
            account_type: '',
            atm_number: '',
            bank_branch: '',
            atm_holder_name: '',
        });

    const selectedMethodNeedsAccount =
        methodOptions.find((option) => option.value === method)?.needsAccount ??
        false;

    const resetAddAccountForm = () => {
        setIsAddingAccount(false);
        setNewAccount({
            label: '',
            bank_name: '',
            account_name: '',
            account_number: '',
            account_type: '',
            atm_number: '',
            bank_branch: '',
            atm_holder_name: '',
        });
    };

    const handleCreateAccount = async () => {
        const created = await onCreateAccount(newAccount);

        if (created) {
            setAccountId(created.id);
            resetAddAccountForm();
        }
    };

    const handleConfirm = async () => {
        const success = await onConfirm(
            method,
            selectedMethodNeedsAccount ? accountId : null,
        );

        if (success) {
            onOpenChange(false);
        }
    };

    return (
        <Sheet
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    resetAddAccountForm();
                }

                onOpenChange(next);
            }}
        >
            <SheetContent side="right" className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>
                <div className="flex-1 space-y-4 overflow-y-auto px-4">
                    <RadioGroup value={method} onValueChange={setMethod}>
                        {methodOptions.map((option) => (
                            <div key={option.value} className="space-y-2">
                                <div className="flex items-center gap-3 rounded-md border border-input p-3">
                                    <RadioGroupItem
                                        value={option.value}
                                        id={`method-${option.value}`}
                                    />
                                    <Label
                                        htmlFor={`method-${option.value}`}
                                        className="flex-1 cursor-pointer font-normal"
                                    >
                                        {option.label}
                                    </Label>
                                </div>
                                {method === option.value &&
                                option.needsAccount ? (
                                    <div className="ml-4 space-y-2 border-l border-input pl-4">
                                        {accounts.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No saved accounts yet. Add one
                                                below.
                                            </p>
                                        ) : (
                                            <RadioGroup
                                                value={
                                                    accountId
                                                        ? String(accountId)
                                                        : ''
                                                }
                                                onValueChange={(value) =>
                                                    setAccountId(Number(value))
                                                }
                                            >
                                                {accounts.map((account) => (
                                                    <div
                                                        key={account.id}
                                                        className="flex items-center gap-3 rounded-md border border-input p-3"
                                                    >
                                                        <RadioGroupItem
                                                            value={String(
                                                                account.id,
                                                            )}
                                                            id={`account-${account.id}`}
                                                        />
                                                        <Label
                                                            htmlFor={`account-${account.id}`}
                                                            className="flex-1 cursor-pointer font-normal"
                                                        >
                                                            {account.label}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </RadioGroup>
                                        )}
                                        {isAddingAccount ? (
                                            <div className="space-y-3 rounded-md border border-input p-3">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="new_account_label">
                                                        Account label (optional)
                                                    </Label>
                                                    <Input
                                                        id="new_account_label"
                                                        value={
                                                            newAccount.label ??
                                                            ''
                                                        }
                                                        onChange={(event) =>
                                                            setNewAccount(
                                                                (current) => ({
                                                                    ...current,
                                                                    label: event
                                                                        .target
                                                                        .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="new_account_bank_name">
                                                        Bank name
                                                    </Label>
                                                    <Input
                                                        id="new_account_bank_name"
                                                        required
                                                        value={
                                                            newAccount.bank_name
                                                        }
                                                        onChange={(event) =>
                                                            setNewAccount(
                                                                (current) => ({
                                                                    ...current,
                                                                    bank_name:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="new_account_account_name">
                                                        Account name
                                                    </Label>
                                                    <Input
                                                        id="new_account_account_name"
                                                        value={
                                                            newAccount.account_name ??
                                                            ''
                                                        }
                                                        onChange={(event) =>
                                                            setNewAccount(
                                                                (current) => ({
                                                                    ...current,
                                                                    account_name:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="new_account_account_number">
                                                        Account number
                                                    </Label>
                                                    <Input
                                                        id="new_account_account_number"
                                                        required
                                                        value={
                                                            newAccount.account_number
                                                        }
                                                        onChange={(event) =>
                                                            setNewAccount(
                                                                (current) => ({
                                                                    ...current,
                                                                    account_number:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="new_account_atm_number">
                                                        ATM card number
                                                        (optional)
                                                    </Label>
                                                    <Input
                                                        id="new_account_atm_number"
                                                        value={
                                                            newAccount.atm_number ??
                                                            ''
                                                        }
                                                        onChange={(event) =>
                                                            setNewAccount(
                                                                (current) => ({
                                                                    ...current,
                                                                    atm_number:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={
                                                            isSaving ||
                                                            newAccount.bank_name.trim() ===
                                                                '' ||
                                                            newAccount.account_number.trim() ===
                                                                ''
                                                        }
                                                        onClick={
                                                            handleCreateAccount
                                                        }
                                                    >
                                                        Save account
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={
                                                            resetAddAccountForm
                                                        }
                                                    >
                                                        Cancel
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setIsAddingAccount(true)
                                                }
                                            >
                                                Add new account
                                            </Button>
                                        )}
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </RadioGroup>
                </div>
                <SheetFooter>
                    <Button
                        type="button"
                        disabled={
                            isSaving ||
                            method === '' ||
                            (selectedMethodNeedsAccount && accountId === null)
                        }
                        onClick={handleConfirm}
                    >
                        Confirm
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
