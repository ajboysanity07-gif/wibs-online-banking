import { useMemo, useState } from 'react';
import { NumericFormat } from 'react-number-format';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    LoanRequestPersonData,
    LoanRequestReadOnlyMap,
} from '@/types/loan-requests';

const EDUCATIONAL_ATTAINMENT_OPTIONS = [
    'Elementary',
    'High School',
    'Vocational',
    'College',
    'Postgraduate',
];
const EMPLOYMENT_TYPE_OPTIONS = [
    'Private',
    'Government',
    'Self Employed',
    'Retired',
];
const NATURE_OF_BUSINESS_OTHER_VALUE = 'Other';
const NATURE_OF_BUSINESS_OPTIONS = [
    'Retail',
    'Wholesale',
    'Manufacturing',
    'Transportation',
    'Construction',
    'Food & Beverage',
    'Agriculture',
    'Education',
    'Healthcare',
    'Finance',
    'Government',
    'Technology',
    'Services',
    NATURE_OF_BUSINESS_OTHER_VALUE,
];
const readOnlyInputClass = 'bg-muted/40 text-muted-foreground';

const fieldName = (prefix: string, field: string) =>
    `${prefix}[${field}]`;

const fieldError = (
    errors: Record<string, string | undefined>,
    prefix: string,
    field: string,
) => errors[`${prefix}.${field}`];

const splitEmployerBusinessAddress = (
    address: string,
): { street: string; city: string } => {
    const trimmed = address.trim();

    if (trimmed === '') {
        return { street: '', city: '' };
    }

    const separatorIndex = trimmed.indexOf(',');

    if (separatorIndex === -1) {
        return { street: trimmed, city: '' };
    }

    return {
        street: trimmed.slice(0, separatorIndex).trim(),
        city: trimmed.slice(separatorIndex + 1).trim(),
    };
};

const composeEmployerBusinessAddress = (
    street: string,
    city: string,
): string => {
    return [street, city]
        .map((value) => value.trim())
        .filter((value) => value !== '')
        .join(', ');
};

type PersonalFieldsProps = {
    prefix: string;
    values?: LoanRequestPersonData | null;
    errors: Record<string, string | undefined>;
    readOnly?: LoanRequestReadOnlyMap | null;
    includeSpouse?: boolean;
    includeChildren?: boolean;
};

export function LoanRequestPersonalFields({
    prefix,
    values = null,
    errors,
    readOnly = null,
    includeSpouse = false,
    includeChildren = false,
}: PersonalFieldsProps) {
    const [educationalAttainment, setEducationalAttainment] = useState(
        values?.educational_attainment?.trim() ?? '',
    );
    const educationalAttainmentOptions = useMemo(() => {
        if (
            educationalAttainment !== '' &&
            !EDUCATIONAL_ATTAINMENT_OPTIONS.includes(educationalAttainment)
        ) {
            return [educationalAttainment, ...EDUCATIONAL_ATTAINMENT_OPTIONS];
        }

        return EDUCATIONAL_ATTAINMENT_OPTIONS;
    }, [educationalAttainment]);

    const isReadOnly = (field: string) => Boolean(readOnly?.[field]);

    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_first_name`}>First name</Label>
                <Input
                    id={`${prefix}_first_name`}
                    name={fieldName(prefix, 'first_name')}
                    defaultValue={values?.first_name ?? ''}
                    readOnly={isReadOnly('first_name')}
                    required
                    className={cn(
                        'mt-1 block w-full',
                        isReadOnly('first_name') && readOnlyInputClass,
                    )}
                />
                <InputError message={fieldError(errors, prefix, 'first_name')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_last_name`}>Last name</Label>
                <Input
                    id={`${prefix}_last_name`}
                    name={fieldName(prefix, 'last_name')}
                    defaultValue={values?.last_name ?? ''}
                    readOnly={isReadOnly('last_name')}
                    required
                    className={cn(
                        'mt-1 block w-full',
                        isReadOnly('last_name') && readOnlyInputClass,
                    )}
                />
                <InputError message={fieldError(errors, prefix, 'last_name')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_middle_name`}>Middle name</Label>
                <Input
                    id={`${prefix}_middle_name`}
                    name={fieldName(prefix, 'middle_name')}
                    defaultValue={values?.middle_name ?? ''}
                    readOnly={isReadOnly('middle_name')}
                    className={cn(
                        'mt-1 block w-full',
                        isReadOnly('middle_name') && readOnlyInputClass,
                    )}
                />
                <InputError message={fieldError(errors, prefix, 'middle_name')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_nickname`}>Nickname</Label>
                <Input
                    id={`${prefix}_nickname`}
                    name={fieldName(prefix, 'nickname')}
                    defaultValue={values?.nickname ?? ''}
                    className="mt-1 block w-full"
                />
                <InputError message={fieldError(errors, prefix, 'nickname')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_birthdate`}>Birthdate</Label>
                <Input
                    id={`${prefix}_birthdate`}
                    type="date"
                    name={fieldName(prefix, 'birthdate')}
                    defaultValue={values?.birthdate ?? ''}
                    readOnly={isReadOnly('birthdate')}
                    required
                    className={cn(
                        'mt-1 block w-full',
                        isReadOnly('birthdate') && readOnlyInputClass,
                    )}
                />
                <InputError message={fieldError(errors, prefix, 'birthdate')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_birthplace`}>Birthplace</Label>
                <Input
                    id={`${prefix}_birthplace`}
                    name={fieldName(prefix, 'birthplace')}
                    defaultValue={values?.birthplace ?? ''}
                    className="mt-1 block w-full"
                    required
                />
                <InputError message={fieldError(errors, prefix, 'birthplace')} />
            </div>

            <div className="grid gap-2 md:col-span-2">
                <Label htmlFor={`${prefix}_address`}>Address</Label>
                <Input
                    id={`${prefix}_address`}
                    name={fieldName(prefix, 'address')}
                    defaultValue={values?.address ?? ''}
                    readOnly={isReadOnly('address')}
                    required
                    className={cn(
                        'mt-1 block w-full',
                        isReadOnly('address') && readOnlyInputClass,
                    )}
                />
                <InputError message={fieldError(errors, prefix, 'address')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_length_of_stay`}>
                    Length of stay
                </Label>
                <Input
                    id={`${prefix}_length_of_stay`}
                    name={fieldName(prefix, 'length_of_stay')}
                    defaultValue={values?.length_of_stay ?? ''}
                    className="mt-1 block w-full"
                    placeholder="e.g. 2 years"
                    required
                />
                <InputError message={fieldError(errors, prefix, 'length_of_stay')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_housing_status`}>Housing status</Label>
                <Input
                    id={`${prefix}_housing_status`}
                    name={fieldName(prefix, 'housing_status')}
                    defaultValue={values?.housing_status ?? ''}
                    readOnly={isReadOnly('housing_status')}
                    required
                    className={cn(
                        'mt-1 block w-full',
                        isReadOnly('housing_status') && readOnlyInputClass,
                    )}
                />
                <InputError message={fieldError(errors, prefix, 'housing_status')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_cell_no`}>Cell no.</Label>
                <Input
                    id={`${prefix}_cell_no`}
                    name={fieldName(prefix, 'cell_no')}
                    defaultValue={values?.cell_no ?? ''}
                    className="mt-1 block w-full"
                    inputMode="numeric"
                    required
                />
                <InputError message={fieldError(errors, prefix, 'cell_no')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_civil_status`}>Civil status</Label>
                <Input
                    id={`${prefix}_civil_status`}
                    name={fieldName(prefix, 'civil_status')}
                    defaultValue={values?.civil_status ?? ''}
                    readOnly={isReadOnly('civil_status')}
                    required
                    className={cn(
                        'mt-1 block w-full',
                        isReadOnly('civil_status') && readOnlyInputClass,
                    )}
                />
                <InputError message={fieldError(errors, prefix, 'civil_status')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_educational_attainment`}>
                    Educational attainment
                </Label>
                <Select
                    value={educationalAttainment || undefined}
                    onValueChange={(value) => setEducationalAttainment(value)}
                >
                    <SelectTrigger
                        id={`${prefix}_educational_attainment`}
                        className="mt-1 w-full"
                    >
                        <SelectValue placeholder="Select attainment" />
                    </SelectTrigger>
                    <SelectContent>
                        {educationalAttainmentOptions.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input
                    type="hidden"
                    name={fieldName(prefix, 'educational_attainment')}
                    value={educationalAttainment}
                />
                <InputError
                    message={fieldError(errors, prefix, 'educational_attainment')}
                />
            </div>

            {includeChildren ? (
                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}_number_of_children`}>
                        No. of children
                    </Label>
                    <Input
                        id={`${prefix}_number_of_children`}
                        type="number"
                        name={fieldName(prefix, 'number_of_children')}
                        defaultValue={values?.number_of_children ?? ''}
                        readOnly={isReadOnly('number_of_children')}
                        required
                        className={cn(
                            'mt-1 block w-full',
                            isReadOnly('number_of_children') &&
                                readOnlyInputClass,
                        )}
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'number_of_children')}
                    />
                </div>
            ) : null}

            {includeSpouse ? (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor={`${prefix}_spouse_name`}>
                            Spouse name
                        </Label>
                        <Input
                            id={`${prefix}_spouse_name`}
                            name={fieldName(prefix, 'spouse_name')}
                            defaultValue={values?.spouse_name ?? ''}
                            readOnly={isReadOnly('spouse_name')}
                            className={cn(
                                'mt-1 block w-full',
                                isReadOnly('spouse_name') &&
                                    readOnlyInputClass,
                            )}
                        />
                        <InputError
                            message={fieldError(errors, prefix, 'spouse_name')}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${prefix}_spouse_age`}>Spouse age</Label>
                        <Input
                            id={`${prefix}_spouse_age`}
                            type="number"
                            name={fieldName(prefix, 'spouse_age')}
                            defaultValue={values?.spouse_age ?? ''}
                            className="mt-1 block w-full"
                        />
                        <InputError
                            message={fieldError(errors, prefix, 'spouse_age')}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`${prefix}_spouse_cell_no`}>
                            Spouse cell no.
                        </Label>
                        <Input
                            id={`${prefix}_spouse_cell_no`}
                            name={fieldName(prefix, 'spouse_cell_no')}
                            defaultValue={values?.spouse_cell_no ?? ''}
                            className="mt-1 block w-full"
                            inputMode="numeric"
                        />
                        <InputError
                            message={fieldError(errors, prefix, 'spouse_cell_no')}
                        />
                    </div>
                </>
            ) : null}
        </div>
    );
}

type WorkFieldsProps = {
    prefix: string;
    values?: LoanRequestPersonData | null;
    errors: Record<string, string | undefined>;
};

export function LoanRequestWorkFields({
    prefix,
    values = null,
    errors,
}: WorkFieldsProps) {
    const [employmentType, setEmploymentType] = useState(
        values?.employment_type?.trim() ?? '',
    );
    const employmentTypeOptions = useMemo(() => {
        if (
            employmentType !== '' &&
            !EMPLOYMENT_TYPE_OPTIONS.includes(employmentType)
        ) {
            return [employmentType, ...EMPLOYMENT_TYPE_OPTIONS];
        }

        return EMPLOYMENT_TYPE_OPTIONS;
    }, [employmentType]);
    const initialNatureOfBusiness = values?.nature_of_business?.trim() ?? '';
    const hasPresetNatureOfBusiness =
        initialNatureOfBusiness !== '' &&
        initialNatureOfBusiness !== NATURE_OF_BUSINESS_OTHER_VALUE &&
        NATURE_OF_BUSINESS_OPTIONS.includes(initialNatureOfBusiness);
    const [natureOfBusinessSelection, setNatureOfBusinessSelection] =
        useState<string>(
            initialNatureOfBusiness === ''
                ? ''
                : hasPresetNatureOfBusiness
                  ? initialNatureOfBusiness
                  : NATURE_OF_BUSINESS_OTHER_VALUE,
        );
    const [natureOfBusinessOther, setNatureOfBusinessOther] = useState<string>(
        !hasPresetNatureOfBusiness && initialNatureOfBusiness !== ''
            ? initialNatureOfBusiness
            : '',
    );
    const resolvedNatureOfBusiness =
        natureOfBusinessSelection === NATURE_OF_BUSINESS_OTHER_VALUE
            ? natureOfBusinessOther.trim()
            : natureOfBusinessSelection;
    const [grossMonthlyIncome, setGrossMonthlyIncome] = useState<string>(
        values?.gross_monthly_income ?? '',
    );
    const initialEmployerAddress = splitEmployerBusinessAddress(
        values?.employer_business_address ?? '',
    );
    const [employerBusinessStreet, setEmployerBusinessStreet] = useState(
        initialEmployerAddress.street,
    );
    const [employerBusinessCity, setEmployerBusinessCity] = useState(
        initialEmployerAddress.city,
    );
    const employerBusinessAddress = composeEmployerBusinessAddress(
        employerBusinessStreet,
        employerBusinessCity,
    );

    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_employment_type`}>Employment</Label>
                <Select
                    value={employmentType || undefined}
                    onValueChange={(value) => setEmploymentType(value)}
                >
                    <SelectTrigger
                        id={`${prefix}_employment_type`}
                        className="mt-1 w-full"
                    >
                        <SelectValue placeholder="Select employment" />
                    </SelectTrigger>
                    <SelectContent>
                        {employmentTypeOptions.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input
                    type="hidden"
                    name={fieldName(prefix, 'employment_type')}
                    value={employmentType}
                />
                <InputError message={fieldError(errors, prefix, 'employment_type')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_employer_business_name`}>
                    Employer/Business name
                </Label>
                <Input
                    id={`${prefix}_employer_business_name`}
                    name={fieldName(prefix, 'employer_business_name')}
                    defaultValue={values?.employer_business_name ?? ''}
                    className="mt-1 block w-full"
                    required
                />
                <InputError
                    message={fieldError(errors, prefix, 'employer_business_name')}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_employer_business_street`}>
                    Business address (street)
                </Label>
                <Input
                    id={`${prefix}_employer_business_street`}
                    value={employerBusinessStreet}
                    className="mt-1 block w-full"
                    required
                    onChange={(event) =>
                        setEmployerBusinessStreet(event.target.value)
                    }
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_employer_business_city`}>
                    Business address (city/municipality)
                </Label>
                <Input
                    id={`${prefix}_employer_business_city`}
                    value={employerBusinessCity}
                    className="mt-1 block w-full"
                    required
                    onChange={(event) =>
                        setEmployerBusinessCity(event.target.value)
                    }
                />
                <input
                    type="hidden"
                    name={fieldName(prefix, 'employer_business_address')}
                    value={employerBusinessAddress}
                />
                <InputError
                    message={fieldError(errors, prefix, 'employer_business_address')}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_telephone_no`}>Tel. no.</Label>
                <Input
                    id={`${prefix}_telephone_no`}
                    name={fieldName(prefix, 'telephone_no')}
                    defaultValue={values?.telephone_no ?? ''}
                    className="mt-1 block w-full"
                />
                <InputError message={fieldError(errors, prefix, 'telephone_no')} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_current_position`}>
                    Current position
                </Label>
                <Input
                    id={`${prefix}_current_position`}
                    name={fieldName(prefix, 'current_position')}
                    defaultValue={values?.current_position ?? ''}
                    className="mt-1 block w-full"
                    required
                />
                <InputError
                    message={fieldError(errors, prefix, 'current_position')}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_nature_of_business`}>
                    Nature of business
                </Label>
                <Select
                    value={natureOfBusinessSelection || undefined}
                    onValueChange={(value) =>
                        setNatureOfBusinessSelection(value)
                    }
                >
                    <SelectTrigger
                        id={`${prefix}_nature_of_business`}
                        className="mt-1 w-full"
                    >
                        <SelectValue placeholder="Select nature of business" />
                    </SelectTrigger>
                    <SelectContent>
                        {NATURE_OF_BUSINESS_OPTIONS.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {natureOfBusinessSelection === NATURE_OF_BUSINESS_OTHER_VALUE ? (
                    <Input
                        className="mt-2 w-full"
                        value={natureOfBusinessOther}
                        placeholder="Specify industry"
                        onChange={(event) =>
                            setNatureOfBusinessOther(event.target.value)
                        }
                    />
                ) : null}
                <input
                    type="hidden"
                    name={fieldName(prefix, 'nature_of_business')}
                    value={resolvedNatureOfBusiness}
                />
                <InputError
                    message={fieldError(errors, prefix, 'nature_of_business')}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_years_in_work_business`}>
                    Total years in work/business
                </Label>
                <Input
                    id={`${prefix}_years_in_work_business`}
                    name={fieldName(prefix, 'years_in_work_business')}
                    defaultValue={values?.years_in_work_business ?? ''}
                    className="mt-1 block w-full"
                    placeholder="e.g. 5 years"
                    required
                />
                <InputError
                    message={fieldError(errors, prefix, 'years_in_work_business')}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_gross_monthly_income`}>
                    Gross monthly income
                </Label>
                <div className="relative">
                    <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground">
                        PHP
                    </span>
                <NumericFormat
                    id={`${prefix}_gross_monthly_income`}
                    className="mt-1 block w-full pl-12"
                    value={grossMonthlyIncome}
                    onValueChange={(values) => {
                            setGrossMonthlyIncome(values.value);
                        }}
                        thousandSeparator
                        decimalScale={2}
                        fixedDecimalScale
                        allowNegative={false}
                    placeholder="0.00"
                    inputMode="decimal"
                    valueIsNumericString
                    customInput={Input}
                    required
                />
                </div>
                <input
                    type="hidden"
                    name={fieldName(prefix, 'gross_monthly_income')}
                    value={grossMonthlyIncome}
                />
                <InputError
                    message={fieldError(errors, prefix, 'gross_monthly_income')}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}_payday`}>Payday</Label>
                <Input
                    id={`${prefix}_payday`}
                    name={fieldName(prefix, 'payday')}
                    defaultValue={values?.payday ?? ''}
                    className="mt-1 block w-full"
                    placeholder="15 / 30 / 15 & 30"
                    required
                />
                <InputError message={fieldError(errors, prefix, 'payday')} />
            </div>
        </div>
    );
}
