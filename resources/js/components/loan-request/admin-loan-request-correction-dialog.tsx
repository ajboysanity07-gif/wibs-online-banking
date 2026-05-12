import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { LoanRequestLoanDetailsStep } from '@/components/loan-request/loan-request-steps';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toDateInputValue } from '@/lib/formatters';
import type {
    LoanRequestCorrectionPayload,
    LoanRequestDetail,
    LoanRequestFormData,
    LoanRequestPersonData,
    LoanRequestPersonFormData,
    LoanTypeOption,
} from '@/types/loan-requests';

type CorrectionFormData = LoanRequestFormData & {
    change_reason: string;
};

type ValidationErrors = Record<string, string | undefined>;

type LoanDetailField =
    | 'typecode'
    | 'requested_amount'
    | 'requested_term'
    | 'loan_purpose'
    | 'availment_status';

type PersonSection = 'applicant' | 'co_maker_1' | 'co_maker_2';

type Props = {
    open: boolean;
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    loanTypes: LoanTypeOption[];
    errors: ValidationErrors;
    isProcessing: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (payload: LoanRequestCorrectionPayload) => void;
};

type CorrectionDialogFormProps = Omit<Props, 'open'>;

const emptyPerson: LoanRequestPersonFormData = {
    first_name: '',
    middle_name: '',
    last_name: '',
    nickname: '',
    birthdate: '',
    birthplace_city: '',
    birthplace_province: '',
    address1: '',
    address2: '',
    address3: '',
    length_of_stay: '',
    housing_status: '',
    cell_no: '',
    civil_status: '',
    educational_attainment: '',
    number_of_children: '',
    spouse_name: '',
    spouse_age: '',
    spouse_cell_no: '',
    employment_type: '',
    employer_business_name: '',
    employer_business_address1: '',
    employer_business_address2: '',
    employer_business_address3: '',
    telephone_no: '',
    current_position: '',
    nature_of_business: '',
    years_in_work_business: '',
    gross_monthly_income: '',
    payday: '',
};

const toStringValue = (
    value?: string | number | null,
    options?: { emptyIfZero?: boolean },
): string => {
    if (value === null || value === undefined) {
        return '';
    }

    const stringValue = `${value}`.trim();
    const emptyIfZero = options?.emptyIfZero ?? true;

    if (emptyIfZero && (stringValue === '0' || stringValue === '0.00')) {
        return '';
    }

    return stringValue;
};

const toPersonForm = (
    person: LoanRequestPersonData | null,
): LoanRequestPersonFormData => {
    if (!person) {
        return { ...emptyPerson };
    }

    return {
        ...emptyPerson,
        first_name: person.first_name ?? '',
        middle_name: person.middle_name ?? '',
        last_name: person.last_name ?? '',
        nickname: person.nickname ?? '',
        birthdate: toDateInputValue(person.birthdate),
        birthplace_city: person.birthplace_city ?? '',
        birthplace_province: person.birthplace_province ?? '',
        address1: person.address1 ?? '',
        address2: person.address2 ?? '',
        address3: person.address3 ?? '',
        length_of_stay: person.length_of_stay ?? '',
        housing_status: person.housing_status ?? '',
        cell_no: person.cell_no ?? '',
        civil_status: person.civil_status ?? '',
        educational_attainment: person.educational_attainment ?? '',
        number_of_children: toStringValue(person.number_of_children, {
            emptyIfZero: false,
        }),
        spouse_name: person.spouse_name ?? '',
        spouse_age: toStringValue(person.spouse_age),
        spouse_cell_no: person.spouse_cell_no ?? '',
        employment_type: person.employment_type ?? '',
        employer_business_name: person.employer_business_name ?? '',
        employer_business_address1: person.employer_business_address1 ?? '',
        employer_business_address2: person.employer_business_address2 ?? '',
        employer_business_address3: person.employer_business_address3 ?? '',
        telephone_no: person.telephone_no ?? '',
        current_position: person.current_position ?? '',
        nature_of_business: person.nature_of_business ?? '',
        years_in_work_business: person.years_in_work_business ?? '',
        gross_monthly_income: toStringValue(person.gross_monthly_income),
        payday: person.payday ?? '',
    };
};

const buildInitialFormData = (
    loanRequest: LoanRequestDetail,
    applicant: LoanRequestPersonData | null,
    coMakerOne: LoanRequestPersonData | null,
    coMakerTwo: LoanRequestPersonData | null,
): CorrectionFormData => ({
    typecode: loanRequest.typecode ?? '',
    requested_amount: toStringValue(loanRequest.requested_amount),
    requested_term: toStringValue(loanRequest.requested_term),
    loan_purpose: loanRequest.loan_purpose ?? '',
    availment_status: loanRequest.availment_status ?? '',
    undertaking_accepted: false,
    applicant: toPersonForm(applicant),
    co_maker_1: toPersonForm(coMakerOne),
    co_maker_2: toPersonForm(coMakerTwo),
    change_reason: '',
});

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[112px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

export function AdminLoanRequestCorrectionDialog({
    open,
    isProcessing,
    onOpenChange,
    ...formProps
}: Props) {
    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (isProcessing && !nextOpen) {
                    return;
                }

                onOpenChange(nextOpen);
            }}
        >
            {open ? (
                <CorrectionDialogForm
                    key={formProps.loanRequest.id}
                    {...formProps}
                    isProcessing={isProcessing}
                    onOpenChange={onOpenChange}
                />
            ) : null}
        </Dialog>
    );
}

function CorrectionDialogForm({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    loanTypes,
    errors,
    isProcessing,
    onOpenChange,
    onSubmit,
}: CorrectionDialogFormProps) {
    const [activeTab, setActiveTab] = useState('loan');
    const [formData, setFormData] = useState<CorrectionFormData>(() =>
        buildInitialFormData(loanRequest, applicant, coMakerOne, coMakerTwo),
    );

    const availableLoanTypes = useMemo(() => {
        if (
            !loanRequest.typecode ||
            loanTypes.some((type) => type.typecode === loanRequest.typecode)
        ) {
            return loanTypes;
        }

        return [
            {
                typecode: loanRequest.typecode,
                label:
                    loanRequest.loan_type_label_snapshot ??
                    loanRequest.typecode,
            },
            ...loanTypes,
        ];
    }, [loanRequest, loanTypes]);

    const handleLoanDetailChange = (field: LoanDetailField, value: string) => {
        setFormData((current) => ({
            ...current,
            [field]: value,
        }));
    };

    const updatePersonField =
        (section: PersonSection) =>
        (field: keyof LoanRequestPersonFormData, value: string) => {
            setFormData((current) => ({
                ...current,
                [section]: {
                    ...current[section],
                    [field]: value,
                },
            }));
        };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        onSubmit({
            typecode: formData.typecode,
            requested_amount: formData.requested_amount,
            requested_term: formData.requested_term,
            loan_purpose: formData.loan_purpose,
            availment_status: formData.availment_status,
            applicant: formData.applicant,
            co_maker_1: formData.co_maker_1,
            co_maker_2: formData.co_maker_2,
            change_reason: formData.change_reason,
        });
    };

    return (
        <DialogContent className="grid max-h-[calc(100vh-2rem)] grid-rows-[auto_minmax(0,1fr)] overflow-hidden sm:max-w-5xl">
            <DialogHeader>
                <DialogTitle>Edit request details</DialogTitle>
                <DialogDescription>
                    Correct submitted loan details and keep the decision
                    workflow separate.
                </DialogDescription>
            </DialogHeader>

            <form
                className="grid min-h-0 grid-rows-[minmax(0,1fr)_auto] gap-4"
                onSubmit={handleSubmit}
            >
                <Tabs
                    value={activeTab}
                    onValueChange={setActiveTab}
                    className="min-h-0 flex-col"
                >
                    <TabsList className="grid w-full grid-cols-2 gap-1 lg:grid-cols-4">
                        <TabsTrigger value="loan">Loan</TabsTrigger>
                        <TabsTrigger value="applicant">Applicant</TabsTrigger>
                        <TabsTrigger value="co-maker-1">Co-maker 1</TabsTrigger>
                        <TabsTrigger value="co-maker-2">Co-maker 2</TabsTrigger>
                    </TabsList>

                    <div className="mt-4 min-h-0 overflow-y-auto pr-1">
                        <TabsContent value="loan" className="mt-0 space-y-5">
                            <LoanRequestLoanDetailsStep
                                data={formData}
                                errors={errors}
                                loanTypes={availableLoanTypes}
                                onChange={handleLoanDetailChange}
                            />

                            <LoanRequestSectionCard
                                title="Correction reason"
                                description="This reason is saved in the audit trail."
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="change_reason">
                                        Change reason
                                    </Label>
                                    <textarea
                                        id="change_reason"
                                        className={textareaClassName}
                                        value={formData.change_reason}
                                        maxLength={1000}
                                        required
                                        disabled={isProcessing}
                                        onChange={(event) =>
                                            setFormData((current) => ({
                                                ...current,
                                                change_reason:
                                                    event.target.value,
                                            }))
                                        }
                                    />
                                    <InputError
                                        message={errors.change_reason}
                                    />
                                </div>
                            </LoanRequestSectionCard>
                        </TabsContent>

                        <TabsContent
                            value="applicant"
                            className="mt-0 space-y-5"
                        >
                            <LoanRequestSectionCard title="Applicant personal data">
                                <LoanRequestPersonalFields
                                    prefix="applicant"
                                    values={formData.applicant}
                                    errors={errors}
                                    includeSpouse
                                    includeChildren
                                    onChange={updatePersonField('applicant')}
                                />
                            </LoanRequestSectionCard>
                            <LoanRequestSectionCard title="Applicant work & finances">
                                <LoanRequestWorkFields
                                    prefix="applicant"
                                    values={formData.applicant}
                                    errors={errors}
                                    onChange={updatePersonField('applicant')}
                                />
                            </LoanRequestSectionCard>
                        </TabsContent>

                        <TabsContent
                            value="co-maker-1"
                            className="mt-0 space-y-5"
                        >
                            <LoanRequestSectionCard title="Co-maker 1">
                                <LoanRequestPersonalFields
                                    prefix="co_maker_1"
                                    values={formData.co_maker_1}
                                    errors={errors}
                                    onChange={updatePersonField('co_maker_1')}
                                />
                                <Separator className="bg-border/40" />
                                <LoanRequestWorkFields
                                    prefix="co_maker_1"
                                    values={formData.co_maker_1}
                                    errors={errors}
                                    onChange={updatePersonField('co_maker_1')}
                                />
                            </LoanRequestSectionCard>
                        </TabsContent>

                        <TabsContent
                            value="co-maker-2"
                            className="mt-0 space-y-5"
                        >
                            <LoanRequestSectionCard title="Co-maker 2">
                                <LoanRequestPersonalFields
                                    prefix="co_maker_2"
                                    values={formData.co_maker_2}
                                    errors={errors}
                                    onChange={updatePersonField('co_maker_2')}
                                />
                                <Separator className="bg-border/40" />
                                <LoanRequestWorkFields
                                    prefix="co_maker_2"
                                    values={formData.co_maker_2}
                                    errors={errors}
                                    onChange={updatePersonField('co_maker_2')}
                                />
                            </LoanRequestSectionCard>
                        </TabsContent>
                    </div>
                </Tabs>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={isProcessing}
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button type="submit" disabled={isProcessing}>
                        <Save />
                        Save corrections
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    );
}
