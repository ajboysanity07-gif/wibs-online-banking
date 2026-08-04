import { useState } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    value: string;
    applicantFullName: string;
    error?: string;
    onChange: (value: string) => void;
};

// "This is my own ATM card" defaults to checked when the stored value is
// empty or already matches the applicant's own name (e.g. prefilled from a
// prior submission) -- unchecked only when it holds a genuinely different
// name. Checking it disables manual entry and writes the applicant's name;
// unchecking clears the field for manual entry.
export function AtmHolderCheckboxField({
    id,
    label,
    value,
    applicantFullName,
    error,
    onChange,
}: Props) {
    const [isOwnCard, setIsOwnCard] = useState(
        () => value.trim() === '' || value.trim() === applicantFullName.trim(),
    );

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`${id}_is_own`}
                    checked={isOwnCard}
                    onCheckedChange={(checked) => {
                        const next = checked === true;
                        setIsOwnCard(next);
                        onChange(next ? applicantFullName : '');
                    }}
                />
                <Label htmlFor={`${id}_is_own`} className="text-sm font-normal">
                    This is my own ATM card
                </Label>
            </div>
            <Input
                id={id}
                value={value}
                disabled={isOwnCard}
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} />
        </div>
    );
}
