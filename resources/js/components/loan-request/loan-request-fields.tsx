import type { ChangeEvent } from 'react';
import { useMemo, useState } from 'react';
import { NumericFormat } from 'react-number-format';
import InputError from '@/components/input-error';
import { LocationAutocompleteInput } from '@/components/location-autocomplete-input';
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
import { useLocationSearch } from '@/hooks/use-location-search';
import { cn } from '@/lib/utils';
import { birthplaces } from '@/routes/api/locations';
import type {
    LoanRequestPersonFormData,
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
const CIVIL_STATUS_OPTIONS = [
    'Single',
    'Married',
    'Separated',
    'Widowed',
] as const;
const HOUSING_STATUS_OPTIONS = [
    { value: 'OWNED', label: 'Owned' },
    { value: 'RENT', label: 'Rent' },
] as const;
const PAYDAY_OPTIONS = [
    'Weekly',
    '15th',
    '30th',
    '15th & 30th',
    'Bi-Weekly',
    'Monthly',
] as const;
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
const readOnlyInputClass =
    'bg-muted/30 text-muted-foreground/80 border-border/40';

const fieldName = (prefix: string, field: string) =>
    `${prefix}[${field}]`;

const fieldError = (
    errors: Record<string, string | undefined>,
    prefix: string,
    field: string,
) => errors[`${prefix}.${field}`];

type FieldLabelProps = {
    htmlFor: string;
    label: string;
    isReadOnly?: boolean;
};

const FieldLabel = ({
    htmlFor,
    label,
    isReadOnly = false,
}: FieldLabelProps) => (
    <div className="flex items-center justify-between gap-2">
        <Label htmlFor={htmlFor}>{label}</Label>
        {isReadOnly ? (
            <span className="rounded-full border border-border/40 bg-muted/30 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                Verified
            </span>
        ) : null}
    </div>
);

const STREET_CUE_PATTERN =
    /\b(street|st\.?|ave\.?|avenue|rd\.?|road|blvd\.?|boulevard|drive|dr\.?|lane|ln\.?|highway|hiway|bldg\.?|building|unit|floor|lot|blk\.?|block|phase|purok|sitio|subd\.?|subdivision|village|compound|plaza|tower|mall|center|centre|brgy\.?|barangay)\b/i;
const LOCALITY_CUE_PATTERN =
    /\b(city|municipality|province|town)\b/i;

const looksLikeStreet = (segment: string): boolean =>
    STREET_CUE_PATTERN.test(segment) ||
    /\d/.test(segment) ||
    segment.includes('#');

const looksLikeLocality = (segment: string): boolean =>
    LOCALITY_CUE_PATTERN.test(segment);

const splitEmployerBusinessAddress = (
    address: string,
): { street: string; city: string } => {
    const trimmed = address.trim();

    if (trimmed === '') {
        return { street: '', city: '' };
    }

    const segments = trimmed
        .split(',')
        .map((segment) => segment.trim())
        .filter((segment) => segment !== '');

    if (segments.length === 1) {
        const [segment] = segments;

        if (looksLikeStreet(segment) && !looksLikeLocality(segment)) {
            return { street: segment, city: '' };
        }

        return { street: '', city: segment };
    }

    const [firstSegment, ...restSegments] = segments;

    if (
        looksLikeLocality(firstSegment) &&
        !looksLikeStreet(firstSegment)
    ) {
        return { street: '', city: segments.join(', ') };
    }

    return {
        street: firstSegment,
        city: restSegments.join(', '),
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

const isPresetNatureOfBusiness = (value: string): boolean =>
    value !== '' &&
    value !== NATURE_OF_BUSINESS_OTHER_VALUE &&
    NATURE_OF_BUSINESS_OPTIONS.includes(value);

const resolveNatureOfBusinessSelection = (value: string): string => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '';
    }

    if (isPresetNatureOfBusiness(trimmed)) {
        return trimmed;
    }

    return NATURE_OF_BUSINESS_OTHER_VALUE;
};

const resolveNatureOfBusinessOther = (value: string): string => {
    const trimmed = value.trim();

    if (
        trimmed === '' ||
        isPresetNatureOfBusiness(trimmed) ||
        trimmed === NATURE_OF_BUSINESS_OTHER_VALUE
    ) {
        return '';
    }

    return trimmed;
};

type PersonalFieldsProps = {
    prefix: string;
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    readOnly?: LoanRequestReadOnlyMap | null;
    includeSpouse?: boolean;
    includeChildren?: boolean;
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
};

export function LoanRequestPersonalFields({
    prefix,
    values,
    errors,
    readOnly = null,
    includeSpouse = false,
    includeChildren = false,
    onChange,
}: PersonalFieldsProps) {
    const educationalAttainment = values.educational_attainment;

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
    const hasReadOnlyFields = Object.values(readOnly ?? {}).some(Boolean);
    const birthplaceSearch = useLocationSearch({
        initialQuery: values.birthplace,
        searchUrl: birthplaces.url(),
    });
    const birthplaceInputClass = cn(
        'mt-1 block w-full',
        isReadOnly('birthplace') && readOnlyInputClass,
    );
    const updateField =
        (field: keyof LoanRequestPersonFormData) =>
        (event: ChangeEvent<HTMLInputElement>) => {
            onChange(field, event.target.value);
        };

    return (
        <div className="space-y-7">
            {hasReadOnlyFields ? (
                <div className="rounded-lg border border-border/40 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
                    Verified profile fields are locked. Update your profile if
                    you need changes.
                </div>
            ) : null}
            <div className="grid gap-5 md:grid-cols-2">
                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_first_name`}
                        label="First name"
                        isReadOnly={isReadOnly('first_name')}
                    />
                    <Input
                        id={`${prefix}_first_name`}
                        name={fieldName(prefix, 'first_name')}
                        value={values.first_name}
                        readOnly={isReadOnly('first_name')}
                        required
                        className={cn(
                            'mt-1 block w-full',
                            isReadOnly('first_name') && readOnlyInputClass,
                        )}
                        onChange={updateField('first_name')}
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'first_name')}
                    />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_last_name`}
                        label="Last name"
                        isReadOnly={isReadOnly('last_name')}
                    />
                    <Input
                        id={`${prefix}_last_name`}
                        name={fieldName(prefix, 'last_name')}
                        value={values.last_name}
                        readOnly={isReadOnly('last_name')}
                        required
                        className={cn(
                            'mt-1 block w-full',
                            isReadOnly('last_name') && readOnlyInputClass,
                        )}
                        onChange={updateField('last_name')}
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'last_name')}
                    />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_middle_name`}
                        label="Middle name"
                        isReadOnly={isReadOnly('middle_name')}
                    />
                    <Input
                        id={`${prefix}_middle_name`}
                        name={fieldName(prefix, 'middle_name')}
                        value={values.middle_name}
                        readOnly={isReadOnly('middle_name')}
                        className={cn(
                            'mt-1 block w-full',
                            isReadOnly('middle_name') && readOnlyInputClass,
                        )}
                        onChange={updateField('middle_name')}
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'middle_name')}
                    />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_nickname`}
                        label="Nickname"
                    />
                    <Input
                        id={`${prefix}_nickname`}
                        name={fieldName(prefix, 'nickname')}
                        value={values.nickname}
                        className="mt-1 block w-full"
                        onChange={updateField('nickname')}
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'nickname')}
                    />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_birthdate`}
                        label="Birthdate"
                        isReadOnly={isReadOnly('birthdate')}
                    />
                    <Input
                        id={`${prefix}_birthdate`}
                        type="date"
                        name={fieldName(prefix, 'birthdate')}
                        value={values.birthdate}
                        readOnly={isReadOnly('birthdate')}
                        required
                        className={cn(
                            'mt-1 block w-full',
                            isReadOnly('birthdate') && readOnlyInputClass,
                        )}
                        onChange={updateField('birthdate')}
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'birthdate')}
                    />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_birthplace`}
                        label="Birthplace"
                        isReadOnly={isReadOnly('birthplace')}
                    />
                    <LocationAutocompleteInput
                        id={`${prefix}_birthplace`}
                        name={fieldName(prefix, 'birthplace')}
                        search={birthplaceSearch}
                        placeholder="City or municipality"
                        required
                        readOnly={isReadOnly('birthplace')}
                        inputClassName={birthplaceInputClass}
                        loadingMessage="Searching birthplace suggestions..."
                        errorMessage="Birthplace suggestions are temporarily unavailable."
                        onValueChange={(value) =>
                            onChange('birthplace', value)
                        }
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'birthplace')}
                    />
                </div>
            </div>

            <Separator className="bg-border/40" />

            <div className="grid gap-5 md:grid-cols-2">
                <div className="grid gap-2 md:col-span-2">
                    <FieldLabel
                        htmlFor={`${prefix}_address`}
                        label="Address"
                        isReadOnly={isReadOnly('address')}
                    />
                    <Input
                        id={`${prefix}_address`}
                        name={fieldName(prefix, 'address')}
                        value={values.address}
                        readOnly={isReadOnly('address')}
                        required
                        className={cn(
                            'mt-1 block w-full',
                            isReadOnly('address') && readOnlyInputClass,
                        )}
                        onChange={updateField('address')}
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'address')}
                    />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_length_of_stay`}
                        label="Length of stay"
                    />
                    <Input
                        id={`${prefix}_length_of_stay`}
                        name={fieldName(prefix, 'length_of_stay')}
                        value={values.length_of_stay}
                        className="mt-1 block w-full"
                        placeholder="e.g. 2 years"
                        required
                        onChange={updateField('length_of_stay')}
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'length_of_stay')}
                    />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_housing_status`}
                        label="Housing status"
                        isReadOnly={isReadOnly('housing_status')}
                    />
                    <Select
                        value={values.housing_status || undefined}
                        onValueChange={(value) =>
                            onChange('housing_status', value)
                        }
                        disabled={isReadOnly('housing_status')}
                    >
                        <SelectTrigger
                            id={`${prefix}_housing_status`}
                            className={cn(
                                'mt-1 w-full',
                                isReadOnly('housing_status') &&
                                    readOnlyInputClass,
                            )}
                        >
                            <SelectValue placeholder="Select housing status" />
                        </SelectTrigger>
                        <SelectContent>
                            {HOUSING_STATUS_OPTIONS.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={fieldError(errors, prefix, 'housing_status')}
                    />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_cell_no`}
                        label="Cell no."
                    />
                    <Input
                        id={`${prefix}_cell_no`}
                        name={fieldName(prefix, 'cell_no')}
                        value={values.cell_no}
                        className="mt-1 block w-full"
                        inputMode="numeric"
                        required
                        onChange={updateField('cell_no')}
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'cell_no')}
                    />
                </div>
            </div>

            <Separator className="bg-border/40" />

            <div className="grid gap-5 md:grid-cols-2">
                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_civil_status`}
                        label="Civil status"
                        isReadOnly={isReadOnly('civil_status')}
                    />
                    <Select
                        value={values.civil_status || undefined}
                        onValueChange={(value) =>
                            onChange('civil_status', value)
                        }
                        disabled={isReadOnly('civil_status')}
                    >
                        <SelectTrigger
                            id={`${prefix}_civil_status`}
                            className={cn(
                                'mt-1 w-full',
                                isReadOnly('civil_status') &&
                                    readOnlyInputClass,
                            )}
                        >
                            <SelectValue placeholder="Select civil status" />
                        </SelectTrigger>
                        <SelectContent>
                            {CIVIL_STATUS_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={fieldError(errors, prefix, 'civil_status')}
                    />
                </div>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`${prefix}_educational_attainment`}
                        label="Educational attainment"
                    />
                    <Select
                        value={educationalAttainment || undefined}
                        onValueChange={(value) =>
                            onChange('educational_attainment', value)
                        }
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
                    <InputError
                        message={fieldError(
                            errors,
                            prefix,
                            'educational_attainment',
                        )}
                    />
                </div>

                {includeChildren ? (
                    <div className="grid gap-2">
                        <FieldLabel
                            htmlFor={`${prefix}_number_of_children`}
                            label="No. of children"
                            isReadOnly={isReadOnly('number_of_children')}
                        />
                        <Input
                            id={`${prefix}_number_of_children`}
                            type="number"
                            name={fieldName(prefix, 'number_of_children')}
                            value={values.number_of_children}
                            readOnly={isReadOnly('number_of_children')}
                            required
                            className={cn(
                                'mt-1 block w-full',
                                isReadOnly('number_of_children') &&
                                    readOnlyInputClass,
                            )}
                            onChange={updateField('number_of_children')}
                        />
                        <InputError
                            message={fieldError(
                                errors,
                                prefix,
                                'number_of_children',
                            )}
                        />
                    </div>
                ) : null}

                {includeSpouse ? (
                    <>
                        <div className="grid gap-2">
                            <FieldLabel
                                htmlFor={`${prefix}_spouse_name`}
                                label="Spouse name"
                                isReadOnly={isReadOnly('spouse_name')}
                            />
                            <Input
                                id={`${prefix}_spouse_name`}
                                name={fieldName(prefix, 'spouse_name')}
                                value={values.spouse_name}
                                readOnly={isReadOnly('spouse_name')}
                                className={cn(
                                    'mt-1 block w-full',
                                    isReadOnly('spouse_name') &&
                                        readOnlyInputClass,
                                )}
                                onChange={updateField('spouse_name')}
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'spouse_name',
                                )}
                            />
                        </div>

                        <div className="grid gap-2">
                            <FieldLabel
                                htmlFor={`${prefix}_spouse_age`}
                                label="Spouse age"
                            />
                            <Input
                                id={`${prefix}_spouse_age`}
                                type="number"
                                name={fieldName(prefix, 'spouse_age')}
                                value={values.spouse_age}
                                className="mt-1 block w-full"
                                onChange={updateField('spouse_age')}
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'spouse_age',
                                )}
                            />
                        </div>

                        <div className="grid gap-2">
                            <FieldLabel
                                htmlFor={`${prefix}_spouse_cell_no`}
                                label="Spouse cell no."
                            />
                            <Input
                                id={`${prefix}_spouse_cell_no`}
                                name={fieldName(prefix, 'spouse_cell_no')}
                                value={values.spouse_cell_no}
                                className="mt-1 block w-full"
                                inputMode="numeric"
                                onChange={updateField('spouse_cell_no')}
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'spouse_cell_no',
                                )}
                            />
                        </div>
                    </>
                ) : null}
            </div>
        </div>
    );
}

type WorkFieldsProps = {
    prefix: string;
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
};

export function LoanRequestWorkFields({
    prefix,
    values,
    errors,
    onChange,
}: WorkFieldsProps) {
    const employmentType = values.employment_type;

    const employmentTypeOptions = useMemo(() => {
        if (
            employmentType !== '' &&
            !EMPLOYMENT_TYPE_OPTIONS.includes(employmentType)
        ) {
            return [employmentType, ...EMPLOYMENT_TYPE_OPTIONS];
        }

        return EMPLOYMENT_TYPE_OPTIONS;
    }, [employmentType]);

    const [natureOfBusinessSelection, setNatureOfBusinessSelection] =
        useState<string>(() =>
            resolveNatureOfBusinessSelection(values.nature_of_business),
        );
    const [natureOfBusinessOther, setNatureOfBusinessOther] = useState<string>(
        () => resolveNatureOfBusinessOther(values.nature_of_business),
    );

    const { street: employerBusinessStreet, city: employerBusinessCity } =
        useMemo(
            () =>
                splitEmployerBusinessAddress(values.employer_business_address),
            [values.employer_business_address],
        );
    const employerBusinessCitySearch = useLocationSearch({
        initialQuery: employerBusinessCity,
        searchUrl: birthplaces.url(),
    });

    const handleNatureOfBusinessSelection = (value: string) => {
        setNatureOfBusinessSelection(value);

        if (value === NATURE_OF_BUSINESS_OTHER_VALUE) {
            onChange('nature_of_business', natureOfBusinessOther.trim());
            return;
        }

        onChange('nature_of_business', value);
    };

    const handleNatureOfBusinessOtherChange = (
        event: ChangeEvent<HTMLInputElement>,
    ) => {
        const nextValue = event.target.value;

        setNatureOfBusinessOther(nextValue);

        if (natureOfBusinessSelection === NATURE_OF_BUSINESS_OTHER_VALUE) {
            onChange('nature_of_business', nextValue);
        }
    };

    return (
        <div className="space-y-7">
            <div className="grid gap-5 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}_employment_type`}>
                        Employment
                    </Label>
                    <Select
                        value={employmentType || undefined}
                        onValueChange={(value) =>
                            onChange('employment_type', value)
                        }
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
                    <InputError
                        message={fieldError(errors, prefix, 'employment_type')}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}_employer_business_name`}>
                        Employer/Business name
                    </Label>
                    <Input
                        id={`${prefix}_employer_business_name`}
                        name={fieldName(prefix, 'employer_business_name')}
                        value={values.employer_business_name}
                        className="mt-1 block w-full"
                        required
                        onChange={(event) =>
                            onChange(
                                'employer_business_name',
                                event.target.value,
                            )
                        }
                    />
                    <InputError
                        message={fieldError(
                            errors,
                            prefix,
                            'employer_business_name',
                        )}
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
                        onChange={(event) => {
                            onChange(
                                'employer_business_address',
                                composeEmployerBusinessAddress(
                                    event.target.value,
                                    employerBusinessCity,
                                ),
                            );
                        }}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}_employer_business_city`}>
                        Business address (city/municipality)
                    </Label>
                    <LocationAutocompleteInput
                        id={`${prefix}_employer_business_city`}
                        search={employerBusinessCitySearch}
                        placeholder="City or municipality"
                        ariaLabel="City or municipality"
                        inputClassName="mt-1 block w-full"
                        required
                        onValueChange={(value) =>
                            onChange(
                                'employer_business_address',
                                composeEmployerBusinessAddress(
                                    employerBusinessStreet,
                                    value,
                                ),
                            )
                        }
                    />
                    <InputError
                        message={fieldError(
                            errors,
                            prefix,
                            'employer_business_address',
                        )}
                    />
                </div>
            </div>

            <Separator className="bg-border/40" />

            <div className="grid gap-5 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}_telephone_no`}>Tel. no.</Label>
                    <Input
                        id={`${prefix}_telephone_no`}
                        name={fieldName(prefix, 'telephone_no')}
                        value={values.telephone_no}
                        className="mt-1 block w-full"
                        onChange={(event) =>
                            onChange('telephone_no', event.target.value)
                        }
                    />
                    <InputError
                        message={fieldError(errors, prefix, 'telephone_no')}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}_current_position`}>
                        Current position
                    </Label>
                    <Input
                        id={`${prefix}_current_position`}
                        name={fieldName(prefix, 'current_position')}
                        value={values.current_position}
                        className="mt-1 block w-full"
                        required
                        onChange={(event) =>
                            onChange('current_position', event.target.value)
                        }
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
                        onValueChange={handleNatureOfBusinessSelection}
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
                    {natureOfBusinessSelection ===
                    NATURE_OF_BUSINESS_OTHER_VALUE ? (
                        <Input
                            className="mt-2 w-full"
                            value={natureOfBusinessOther}
                            placeholder="Specify industry"
                            onChange={handleNatureOfBusinessOtherChange}
                        />
                    ) : null}
                    <InputError
                        message={fieldError(
                            errors,
                            prefix,
                            'nature_of_business',
                        )}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}_years_in_work_business`}>
                        Total years in work/business
                    </Label>
                    <Input
                        id={`${prefix}_years_in_work_business`}
                        name={fieldName(prefix, 'years_in_work_business')}
                        value={values.years_in_work_business}
                        className="mt-1 block w-full"
                        placeholder="e.g. 5 years"
                        required
                        onChange={(event) =>
                            onChange(
                                'years_in_work_business',
                                event.target.value,
                            )
                        }
                    />
                    <InputError
                        message={fieldError(
                            errors,
                            prefix,
                            'years_in_work_business',
                        )}
                    />
                </div>
            </div>

            <Separator className="bg-border/40" />

            <div className="grid gap-5 md:grid-cols-2">
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
                            value={values.gross_monthly_income}
                            onValueChange={(value) => {
                                onChange(
                                    'gross_monthly_income',
                                    value.value,
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
                            required
                        />
                    </div>
                    <InputError
                        message={fieldError(
                            errors,
                            prefix,
                            'gross_monthly_income',
                        )}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}_payday`}>Payday</Label>
                    <Select
                        value={values.payday || undefined}
                        onValueChange={(value) => onChange('payday', value)}
                    >
                        <SelectTrigger
                            id={`${prefix}_payday`}
                            className="mt-1 w-full"
                        >
                            <SelectValue placeholder="Select payday" />
                        </SelectTrigger>
                        <SelectContent>
                            {PAYDAY_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={fieldError(errors, prefix, 'payday')}
                    />
                </div>
            </div>
        </div>
    );
}

