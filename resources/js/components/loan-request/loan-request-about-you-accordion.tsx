import { Briefcase, ChevronDown, MapPin, User } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    composeAddress,
    composeBirthplace,
    formatCivilStatus,
    formatCurrency,
    formatDisplayText,
    formatHousingStatus,
    formatPayday,
} from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { LoanRequestPersonFormData } from '@/types/loan-requests';

type Props = {
    values: LoanRequestPersonFormData;
};

type GroupDef = {
    id: string;
    title: string;
    icon: typeof User;
    summary: (values: LoanRequestPersonFormData) => string;
    rows: (values: LoanRequestPersonFormData) => { k: string; v: string }[];
};

const display = (value?: string | null): string => {
    const normalized = formatDisplayText(value);

    return normalized !== '' ? normalized : '--';
};

const GROUPS: GroupDef[] = [
    {
        id: 'basic',
        title: 'Basic information',
        icon: User,
        summary: (v) =>
            [v.first_name, v.last_name].filter(Boolean).join(' ') ||
            'Not filled in',
        rows: (v) => [
            { k: 'First name', v: display(v.first_name) },
            { k: 'Middle name', v: display(v.middle_name) },
            { k: 'Last name', v: display(v.last_name) },
            { k: 'Nickname', v: display(v.nickname) },
            { k: 'Birthdate', v: display(v.birthdate) },
            {
                k: 'Birthplace',
                v: display(
                    composeBirthplace(v.birthplace_city, v.birthplace_province),
                ),
            },
            { k: 'Sex', v: display(v.sex) },
            { k: 'Cell no.', v: display(v.cell_no) },
        ],
    },
    {
        id: 'contact',
        title: 'Address & family',
        icon: MapPin,
        summary: (v) =>
            [v.address1, v.civil_status].filter(Boolean).join(' · ') ||
            'Not filled in',
        rows: (v) => [
            {
                k: 'Home address',
                v: display(
                    composeAddress(
                        v.address1,
                        v.address2,
                        v.address3,
                        v.address_barangay,
                    ),
                ),
            },
            { k: 'Length of stay', v: display(v.length_of_stay) },
            {
                k: 'Housing status',
                v: display(formatHousingStatus(v.housing_status)),
            },
            {
                k: 'Civil status',
                v: display(formatCivilStatus(v.civil_status)),
            },
            { k: 'Spouse name', v: display(v.spouse_name) },
            {
                k: 'Educational attainment',
                v: display(v.educational_attainment),
            },
        ],
    },
    {
        id: 'work',
        title: 'Work & income',
        icon: Briefcase,
        summary: (v) =>
            [v.employment_type, v.employer_business_name]
                .filter(Boolean)
                .join(' · ') || 'Not filled in',
        rows: (v) => [
            { k: 'Employment type', v: display(v.employment_type) },
            {
                k: 'Employer/Business name',
                v: display(v.employer_business_name),
            },
            {
                k: 'Employer/Business address',
                v: display(
                    composeAddress(
                        v.employer_business_address1,
                        v.employer_business_address2,
                        v.employer_business_address3,
                        v.employer_business_address_barangay,
                    ),
                ),
            },
            { k: 'Telephone no.', v: display(v.telephone_no) },
            { k: 'Current position', v: display(v.current_position) },
            { k: 'Nature of business', v: display(v.nature_of_business) },
            {
                k: 'Years in work/business',
                v: display(v.years_in_work_business),
            },
            {
                k: 'Gross monthly income',
                v:
                    v.gross_monthly_income.trim() !== ''
                        ? formatCurrency(Number(v.gross_monthly_income))
                        : '--',
            },
            { k: 'Payday', v: display(formatPayday(v.payday)) },
        ],
    },
];

export function LoanRequestAboutYouAccordion({ values }: Props) {
    const [open, setOpen] = useState<Set<string>>(() => new Set(['basic']));

    const toggle = (id: string) =>
        setOpen((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    return (
        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
            {GROUPS.map((group, index) => {
                const isOpen = open.has(group.id);

                return (
                    <div
                        key={group.id}
                        className={cn(index > 0 && 'border-t border-border')}
                    >
                        <Button variant="ghost"
                            type="button"
                            onClick={() => toggle(group.id)}
                            aria-expanded={isOpen}
                            className="h-auto md:h-auto justify-start gap-0 whitespace-normal rounded-none px-0 py-0 has-[>svg]:px-0 font-normal hover:bg-transparent hover:text-current flex w-full items-center gap-3 px-5 has-[>svg]:px-5 py-4 text-left transition-colors hover:bg-muted"
                        >
                            <group.icon
                                aria-hidden="true"
                                className="size-5 shrink-0 text-muted-foreground"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="text-[15px] font-semibold">
                                    {group.title}
                                </p>
                                <p className="truncate text-[13px] text-muted-foreground">
                                    {group.summary(values)}
                                </p>
                            </div>
                            <ChevronDown
                                aria-hidden="true"
                                className={cn(
                                    'size-[18px] shrink-0 text-muted-foreground transition-transform duration-200',
                                    isOpen && 'rotate-180',
                                )}
                            />
                        </Button>
                        {isOpen ? (
                            <div className="border-t border-border px-5 py-5">
                                <div className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                                    {group.rows(values).map((row) => (
                                        <div key={row.k} className="min-w-0">
                                            <p className="text-xs text-muted-foreground">
                                                {row.k}
                                            </p>
                                            <p className="text-sm font-semibold wrap-break-word">
                                                {row.v}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : null}
                    </div>
                );
            })}
        </div>
    );
}
