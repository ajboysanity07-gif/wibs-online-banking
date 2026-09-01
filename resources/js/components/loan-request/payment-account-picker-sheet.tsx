import {
    Banknote,
    Building2,
    CreditCard,
    FileCheck2,
    Landmark,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { createElement, useState } from 'react';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { getAccountDisplayLabel } from '@/lib/payment-accounts';

export type PaymentMethodOption = {
    value: string;
    label: string;
    needsAccount: boolean;
    icon?: LucideIcon;
};

const METHOD_FALLBACK_ICONS: Record<string, LucideIcon> = {
    ATM: CreditCard,
    'ATM Deduction': CreditCard,
    'Bank Transfer': Landmark,
    Check: FileCheck2,
    Cash: Banknote,
    'Salary Deduction': Wallet,
};

export const resolveMethodIcon = (method: string | null): LucideIcon | null => {
    if (method === null) {
        return null;
    }

    return METHOD_FALLBACK_ICONS[method] ?? Building2;
};

export function PaymentMethodIcon({
    method,
    className = 'h-4 w-4',
}: {
    method: string | null;
    className?: string;
}) {
    const Icon = resolveMethodIcon(method);

    if (Icon === null) {
        return null;
    }

    return createElement(Icon, { className, 'aria-hidden': true });
}

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
    onUpdateAccount: (
        id: number,
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
    onUpdateAccount,
}: PaymentAccountPickerSheetProps) {
    const [method, setMethod] = useState(
        initialMethod ?? methodOptions[0]?.value ?? '',
    );
    const [accountId, setAccountId] = useState<number | null>(
        initialAccountId ?? null,
    );
    const [isAddingAccount, setIsAddingAccount] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
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
    const [editForm, setEditForm] = useState<SavedPaymentAccountFormPayload>({
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

    const startEditing = (account: SavedPaymentAccount) => {
        setEditingId(account.id);
        setEditForm({
            label: account.label ?? '',
            bank_name: account.bank_name ?? '',
            account_name: account.account_name ?? '',
            account_number: account.account_number ?? '',
            account_type: account.account_type ?? '',
            atm_number: account.atm_number ?? '',
            bank_branch: account.bank_branch ?? '',
            atm_holder_name: account.atm_holder_name ?? '',
        });
    };

    const cancelEditing = () => {
        setEditingId(null);
    };

    const handleUpdateAccount = async () => {
        if (editingId === null) return;

        const updated = await onUpdateAccount(editingId, editForm);

        if (updated) {
            setEditingId(null);
        }
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
                                <Card
                                    className={`cursor-pointer py-0 transition-colors ${
                                        method === option.value
                                            ? 'border-primary ring-1 ring-primary'
                                            : ''
                                    }`}
                                    onClick={() => setMethod(option.value)}
                                >
                                    <CardContent className="flex items-center gap-3 p-3">
                                        <RadioGroupItem
                                            value={option.value}
                                            id={`method-${option.value}`}
                                        />
                                        <span className="shrink-0 text-muted-foreground">
                                            <PaymentMethodIcon
                                                method={option.value}
                                            />
                                        </span>
                                        <Label
                                            htmlFor={`method-${option.value}`}
                                            className="flex-1 cursor-pointer font-normal"
                                        >
                                            {option.label}
                                        </Label>
                                    </CardContent>
                                </Card>
                                {method === option.value &&
                                option.needsAccount ? (
                                    <div className="space-y-2 pt-1">
                                        {accounts.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No saved accounts yet. Add one
                                                below.
                                            </p>
                                        ) : (
                                            <Accordion
                                                type="single"
                                                collapsible
                                                value={
                                                    accountId
                                                        ? String(accountId)
                                                        : ''
                                                }
                                                onValueChange={(value) =>
                                                    setAccountId(
                                                        value
                                                            ? Number(value)
                                                            : null,
                                                    )
                                                }
                                                className="space-y-2"
                                            >
                                                {accounts.map((account) => (
                                                    <AccordionItem
                                                        key={account.id}
                                                        value={String(
                                                            account.id,
                                                        )}
                                                    >
                                                        <AccordionTrigger className="px-3 py-2">
                                                            <div className="flex items-center gap-3">
                                                                <div
                                                                    className={`h-2 w-2 shrink-0 rounded-full border border-input ${accountId === account.id ? 'bg-primary' : ''}`}
                                                                />
                                                                <span className="flex-1 text-sm font-normal">
                                                                    {getAccountDisplayLabel(
                                                                        account,
                                                                        method,
                                                                    )}
                                                                </span>
                                                                {account.last_used_at && (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {new Date(
                                                                            account.last_used_at,
                                                                        ).toLocaleDateString()}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </AccordionTrigger>
                                                        <AccordionContent className="px-3 pb-4">
                                                            {editingId ===
                                                            account.id ? (
                                                                <div className="space-y-2 pl-4">
                                                                    <div className="grid gap-2">
                                                                        <Label
                                                                            htmlFor={`edit-label-${account.id}`}
                                                                        >
                                                                            Label
                                                                        </Label>
                                                                        <Input
                                                                            id={`edit-label-${account.id}`}
                                                                            value={
                                                                                editForm.label ??
                                                                                ''
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setEditForm(
                                                                                    (
                                                                                        current,
                                                                                    ) => ({
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
                                                                        <Label
                                                                            htmlFor={`edit-bank_name-${account.id}`}
                                                                        >
                                                                            Bank
                                                                            name
                                                                        </Label>
                                                                        <Input
                                                                            id={`edit-bank_name-${account.id}`}
                                                                            required
                                                                            value={
                                                                                editForm.bank_name
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setEditForm(
                                                                                    (
                                                                                        current,
                                                                                    ) => ({
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
                                                                        <Label
                                                                            htmlFor={`edit-account_name-${account.id}`}
                                                                        >
                                                                            Account
                                                                            name
                                                                        </Label>
                                                                        <Input
                                                                            id={`edit-account_name-${account.id}`}
                                                                            value={
                                                                                editForm.account_name ??
                                                                                ''
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setEditForm(
                                                                                    (
                                                                                        current,
                                                                                    ) => ({
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
                                                                        <Label
                                                                            htmlFor={`edit-account_number-${account.id}`}
                                                                        >
                                                                            Account
                                                                            number
                                                                        </Label>
                                                                        <Input
                                                                            id={`edit-account_number-${account.id}`}
                                                                            required
                                                                            value={
                                                                                editForm.account_number
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setEditForm(
                                                                                    (
                                                                                        current,
                                                                                    ) => ({
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
                                                                    <div className="flex gap-2">
                                                                        <Button
                                                                            size="sm"
                                                                            disabled={
                                                                                isSaving
                                                                            }
                                                                            onClick={
                                                                                handleUpdateAccount
                                                                            }
                                                                        >
                                                                            Save
                                                                        </Button>
                                                                        <Button
                                                                            size="sm"
                                                                            variant="outline"
                                                                            onClick={
                                                                                cancelEditing
                                                                            }
                                                                        >
                                                                            Cancel
                                                                        </Button>
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <>
                                                                    <div className="grid grid-cols-2 gap-x-4 gap-y-1 pl-4 text-sm">
                                                                        {account.bank_name && (
                                                                            <>
                                                                                <span className="text-muted-foreground">
                                                                                    Bank
                                                                                </span>
                                                                                <span>
                                                                                    {
                                                                                        account.bank_name
                                                                                    }
                                                                                </span>
                                                                            </>
                                                                        )}
                                                                        {account.account_name && (
                                                                            <>
                                                                                <span className="text-muted-foreground">
                                                                                    Account
                                                                                    name
                                                                                </span>
                                                                                <span>
                                                                                    {
                                                                                        account.account_name
                                                                                    }
                                                                                </span>
                                                                            </>
                                                                        )}
                                                                        {account.account_number && (
                                                                            <>
                                                                                <span className="text-muted-foreground">
                                                                                    Account
                                                                                    #
                                                                                </span>
                                                                                <span>
                                                                                    {
                                                                                        account.account_number
                                                                                    }
                                                                                </span>
                                                                            </>
                                                                        )}
                                                                        {account.account_type && (
                                                                            <>
                                                                                <span className="text-muted-foreground">
                                                                                    Type
                                                                                </span>
                                                                                <span>
                                                                                    {
                                                                                        account.account_type
                                                                                    }
                                                                                </span>
                                                                            </>
                                                                        )}
                                                                        {account.atm_number && (
                                                                            <>
                                                                                <span className="text-muted-foreground">
                                                                                    ATM
                                                                                    #
                                                                                </span>
                                                                                <span>
                                                                                    {
                                                                                        account.atm_number
                                                                                    }
                                                                                </span>
                                                                            </>
                                                                        )}
                                                                        {account.bank_branch && (
                                                                            <>
                                                                                <span className="text-muted-foreground">
                                                                                    Branch
                                                                                </span>
                                                                                <span>
                                                                                    {
                                                                                        account.bank_branch
                                                                                    }
                                                                                </span>
                                                                            </>
                                                                        )}
                                                                        {account.atm_holder_name && (
                                                                            <>
                                                                                <span className="text-muted-foreground">
                                                                                    ATM
                                                                                    holder
                                                                                </span>
                                                                                <span>
                                                                                    {
                                                                                        account.atm_holder_name
                                                                                    }
                                                                                </span>
                                                                            </>
                                                                        )}
                                                                    </div>
                                                                    <div className="mt-2 flex gap-2 pl-4">
                                                                        <Button
                                                                            size="sm"
                                                                            variant="outline"
                                                                            onClick={() =>
                                                                                startEditing(
                                                                                    account,
                                                                                )
                                                                            }
                                                                        >
                                                                            Edit
                                                                        </Button>
                                                                    </div>
                                                                </>
                                                            )}
                                                        </AccordionContent>
                                                    </AccordionItem>
                                                ))}
                                            </Accordion>
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
                                                <div className="grid gap-2">
                                                    <Label htmlFor="new_account_atm_holder_name">
                                                        ATM card holder name
                                                        (optional)
                                                    </Label>
                                                    <Input
                                                        id="new_account_atm_holder_name"
                                                        value={
                                                            newAccount.atm_holder_name ??
                                                            ''
                                                        }
                                                        onChange={(event) =>
                                                            setNewAccount(
                                                                (current) => ({
                                                                    ...current,
                                                                    atm_holder_name:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="new_account_bank_branch">
                                                        Bank branch (optional)
                                                    </Label>
                                                    <Input
                                                        id="new_account_bank_branch"
                                                        value={
                                                            newAccount.bank_branch ??
                                                            ''
                                                        }
                                                        onChange={(event) =>
                                                            setNewAccount(
                                                                (current) => ({
                                                                    ...current,
                                                                    bank_branch:
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
