import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { NumericFormat } from 'react-number-format';
import Heading from '@/components/heading';
import { LoanRequestPersonalFields, LoanRequestWorkFields } from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
import AppLayout from '@/layouts/app-layout';
import { loans as clientLoans } from '@/routes/client';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestMemberSummary,
    LoanRequestPersonData,
    LoanRequestReadOnlyMap,
    LoanTypeOption,
} from '@/types/loan-requests';

type Props = {
    loanTypes: LoanTypeOption[];
    applicant: LoanRequestPersonData | null;
    applicantReadOnly: LoanRequestReadOnlyMap | null;
    member: LoanRequestMemberSummary;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Loans', href: clientLoans().url },
    { title: 'Loan request', href: LoanRequestController.create().url },
];

const AVAILMENT_OPTIONS = ['New', 'Re-Loan', 'Restructured'] as const;

export default function LoanRequestPage({
    loanTypes,
    applicant,
    applicantReadOnly,
    member,
}: Props) {
    const [loanType, setLoanType] = useState(
        loanTypes[0]?.typecode ?? '',
    );
    const [availmentStatus, setAvailmentStatus] = useState('');
    const [requestedAmount, setRequestedAmount] = useState('');
    const [undertakingAccepted, setUndertakingAccepted] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <Heading
                            title="Loan request"
                            description="Complete the application form to request a new loan."
                        />
                        <p className="text-sm text-muted-foreground">
                            Account No: {member.acctno ?? '--'}
                        </p>
                    </div>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={clientLoans().url}>Back to loans</Link>
                    </Button>
                </div>

                {loanTypes.length === 0 ? (
                    <Alert variant="destructive">
                        <AlertTitle>Loan types unavailable</AlertTitle>
                        <AlertDescription>
                            Please contact support to load available loan
                            options before submitting a request.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Form
                    {...LoanRequestController.store.form()}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <LoanRequestSectionCard
                                title="Application details"
                                description="Select your preferred loan type and request details."
                            >
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="loan_type">
                                            Loan type
                                        </Label>
                                        <Select
                                            value={loanType || undefined}
                                            onValueChange={(value) =>
                                                setLoanType(value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="loan_type"
                                                className="mt-1 w-full"
                                            >
                                                <SelectValue placeholder="Select loan type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {loanTypes.map((option) => (
                                                    <SelectItem
                                                        key={option.typecode}
                                                        value={option.typecode}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="typecode"
                                            value={loanType}
                                        />
                                        <InputError
                                            message={errors.typecode}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="requested_amount">
                                            Requested amount
                                        </Label>
                                        <div className="relative">
                                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground">
                                                PHP
                                            </span>
                                            <NumericFormat
                                                id="requested_amount"
                                                className="mt-1 block w-full pl-12"
                                                value={requestedAmount}
                                                onValueChange={(values) => {
                                                    setRequestedAmount(
                                                        values.value,
                                                    );
                                                }}
                                                thousandSeparator
                                                decimalScale={2}
                                                fixedDecimalScale
                                                allowNegative={false}
                                                placeholder="0.00"
                                                inputMode="decimal"
                                                valueIsNumericString
                                                customInput={Input}
                                            />
                                        </div>
                                        <input
                                            type="hidden"
                                            name="requested_amount"
                                            value={requestedAmount}
                                        />
                                        <InputError
                                            message={errors.requested_amount}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="requested_term">
                                            Loan term (months)
                                        </Label>
                                        <Input
                                            id="requested_term"
                                            type="number"
                                            name="requested_term"
                                            className="mt-1 block w-full"
                                            placeholder="e.g. 12"
                                            required
                                        />
                                        <InputError
                                            message={errors.requested_term}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="availment_status">
                                            Availment status
                                        </Label>
                                        <Select
                                            value={
                                                availmentStatus || undefined
                                            }
                                            onValueChange={(value) =>
                                                setAvailmentStatus(value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="availment_status"
                                                className="mt-1 w-full"
                                            >
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {AVAILMENT_OPTIONS.map(
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
                                        <input
                                            type="hidden"
                                            name="availment_status"
                                            value={availmentStatus}
                                        />
                                        <InputError
                                            message={errors.availment_status}
                                        />
                                    </div>

                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="loan_purpose">
                                            Loan purpose
                                        </Label>
                                        <Input
                                            id="loan_purpose"
                                            name="loan_purpose"
                                            className="mt-1 block w-full"
                                            placeholder="Describe your loan purpose"
                                            required
                                        />
                                        <InputError
                                            message={errors.loan_purpose}
                                        />
                                    </div>
                                </div>
                            </LoanRequestSectionCard>

                            <LoanRequestSectionCard
                                title="My personal data"
                                description="Confirm your personal details."
                            >
                                <LoanRequestPersonalFields
                                    prefix="applicant"
                                    values={applicant}
                                    errors={errors}
                                    readOnly={applicantReadOnly}
                                    includeSpouse
                                    includeChildren
                                />
                            </LoanRequestSectionCard>

                            <LoanRequestSectionCard
                                title="My work & finances"
                                description="Share your current employment and income details."
                            >
                                <LoanRequestWorkFields
                                    prefix="applicant"
                                    values={applicant}
                                    errors={errors}
                                />
                            </LoanRequestSectionCard>

                            <LoanRequestSectionCard
                                title="My co maker 1"
                                description="Provide details for your first co-maker."
                            >
                                <LoanRequestPersonalFields
                                    prefix="co_maker_1"
                                    errors={errors}
                                />
                                <Separator />
                                <LoanRequestWorkFields
                                    prefix="co_maker_1"
                                    errors={errors}
                                />
                            </LoanRequestSectionCard>

                            <LoanRequestSectionCard
                                title="My co maker 2"
                                description="Provide details for your second co-maker."
                            >
                                <LoanRequestPersonalFields
                                    prefix="co_maker_2"
                                    errors={errors}
                                />
                                <Separator />
                                <LoanRequestWorkFields
                                    prefix="co_maker_2"
                                    errors={errors}
                                />
                            </LoanRequestSectionCard>

                            <LoanRequestSectionCard title="Undertaking">
                                <div className="space-y-4 text-sm text-muted-foreground">
                                    <p>
                                        I/We hereby undertake that all
                                        information provided here in this
                                        application form and in all supporting
                                        document are true and correct. I/We
                                        hereby authorized MRDINC to verify any
                                        and all information furnished by me/us
                                        including previous credit transactions
                                        with other institution. In this
                                        connection, I/We hereby expressly waive
                                        any and all statutory or regulatory
                                        provisions governing confidentiality of
                                        such information. I fully understand
                                        that any misrepresentation or failure
                                        to disclose information on my/our part
                                        as required herein, may cause the
                                        disapproval of my application.
                                    </p>
                                    <p>
                                        Upon acceptance of my application, I/We
                                        legally and validly bind to the terms
                                        and conditions of MRDINC including, but
                                        not limited to, join and several
                                        liability for all charges, fees and
                                        other obligations incurred through the
                                        use of my loan. In case of disapproval
                                        of this application, I understand that
                                        MRDINC is not obligated to disclose the
                                        reasons for such disapproval.
                                    </p>
                                    <p>
                                        In the event of future delinquency, I
                                        hereby authorized MRDINC to report and
                                        or include my name in the negative
                                        listing of any bureau or institution.
                                    </p>
                                </div>

                                <div className="mt-6 flex items-start gap-3">
                                    <Checkbox
                                        id="undertaking_accepted"
                                        checked={undertakingAccepted}
                                        onCheckedChange={(checked) =>
                                            setUndertakingAccepted(
                                                checked === true,
                                            )
                                        }
                                    />
                                    <div className="space-y-2">
                                        <Label htmlFor="undertaking_accepted">
                                            I confirm that I have read and
                                            agree to the undertaking above.
                                        </Label>
                                        <InputError
                                            message={errors.undertaking_accepted}
                                        />
                                    </div>
                                </div>

                                <input
                                    type="hidden"
                                    name="undertaking_accepted"
                                    value={undertakingAccepted ? '1' : '0'}
                                />
                            </LoanRequestSectionCard>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing || loanTypes.length === 0}
                                >
                                    Submit loan request
                                </Button>
                                <p className="text-xs text-muted-foreground">
                                    Reviewing details before submission helps
                                    avoid delays.
                                </p>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
