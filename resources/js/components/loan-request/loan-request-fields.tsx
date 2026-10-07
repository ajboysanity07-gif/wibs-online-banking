import type { ChangeEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { BirthdateInput } from '@/components/loan-request/birthdate-input';
import { DateInputWithPicker } from '@/components/loan-request/date-input-with-picker';
import {
    CurrencyInput,
    YearsInput,
} from '@/components/loan-request/numeric-adorned-inputs';
import { LocationCombobox } from '@/components/location-combobox';
import { InlineEditRow } from '@/components/settings/inline-edit-row';
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
import { useLocationSearch } from '@/hooks/use-location-search';
import api from '@/lib/api';
import {
    isPensionerType,
    isSelfEmployedType,
    PENSIONER_EMPLOYMENT_TYPE,
    SELF_EMPLOYED_EMPLOYMENT_TYPE,
} from '@/lib/employment-type';
import { calculateAge } from '@/lib/formatters';
import { APPLICANT_REQUIRED_FIELDS } from '@/lib/loan-request-step-validation';
import { normalizeMobileNumberInput } from '@/lib/phone';
import { cn } from '@/lib/utils';
import { barangays, cities, provinces, zip } from '@/routes/api/locations';
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
const EMPLOYMENT_TYPE_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'Private', label: 'Private' },
    { value: 'Government', label: 'Government' },
    { value: SELF_EMPLOYED_EMPLOYMENT_TYPE, label: 'Self Employed' },
    { value: PENSIONER_EMPLOYMENT_TYPE, label: 'Pensioner / Retired' },
    { value: 'OFW', label: 'OFW' },
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
const SEX_OPTIONS = ['Male', 'Female'] as const;
export const PAYDAY_OPTIONS = [
    'Daily',
    'Weekly',
    'Monthly',
    'Quincenal',
    'Semi-annual',
    'Yearly',
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
const readOnlyInputClass = 'bg-muted text-muted-foreground border-border';

const fieldName = (prefix: string, field: string) => `${prefix}[${field}]`;

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
            <span className="rounded-full border border-border bg-muted/30 px-2 py-0.5 text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                Verified
            </span>
        ) : null}
    </div>
);

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

type FieldRowProps = {
    rows: boolean;
    label: string;
    field: string;
    values: LoanRequestPersonFormData;
    readOnlyFields?: LoanRequestReadOnlyMap | null;
    readOnly?: boolean;
    group?: boolean;
    startOpen?: boolean;
    className?: string;
    children: ReactNode;
};

// One field. With `rows` it becomes a Settings-style label/value row with its
// own Edit button (verified fields stay read-only); without, the plain
// stacked field the co-maker steps and correction dialog use.
function FieldRow({
    rows,
    label,
    field,
    values,
    readOnlyFields,
    readOnly = false,
    group,
    startOpen,
    className,
    children,
}: FieldRowProps) {
    if (!rows) {
        return <div className={cn('grid min-w-0 gap-2', className)}>{children}</div>;
    }

    const blank =
        String(
            values[field as keyof LoanRequestPersonFormData] ?? '',
        ).trim() === '';

    return (
        <InlineEditRow
            label={label}
            group={group}
            readOnly={readOnly || Boolean(readOnlyFields?.[field])}
            startOpen={
                startOpen ?? (blank && APPLICANT_REQUIRED_FIELDS.has(field))
            }
        >
            <div className="grid gap-2">{children}</div>
        </InlineEditRow>
    );
}

type PersonalFieldsProps = {
    prefix: string;
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    readOnly?: LoanRequestReadOnlyMap | null;
    includeSpouse?: boolean;
    includeChildren?: boolean;
    includeCivilHousing?: boolean;
    portal?: boolean;
    // Settings-style per-field Edit rows (applicant wizard only).
    rows?: boolean;
    section?: 'all' | 'basic' | 'contact' | 'family';
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
    // Applicant-only: the member's contact number on file (wmaster.telephone)
    // -- shown as a hint under Cell no. since that's what actually prints on
    // the Generali/Grepalife insurance documents, not this wizard field.
    contactNumberOnFile?: string | null;
};

export function LoanRequestPersonalFields({
    prefix,
    values,
    errors,
    readOnly = null,
    includeSpouse = false,
    includeChildren = false,
    includeCivilHousing = false,
    portal = true,
    rows = false,
    section = 'all',
    onChange,
    contactNumberOnFile = null,
}: PersonalFieldsProps) {
    const educationalAttainment = values.educational_attainment;

    const educationalAttainmentOptions =
        educationalAttainment !== '' &&
        !EDUCATIONAL_ATTAINMENT_OPTIONS.includes(educationalAttainment)
            ? [educationalAttainment, ...EDUCATIONAL_ATTAINMENT_OPTIONS]
            : EDUCATIONAL_ATTAINMENT_OPTIONS;

    const isReadOnly = (field: string) => Boolean(readOnly?.[field]);
    const hasReadOnlyFields = Object.values(readOnly ?? {}).some(Boolean);
    const [lengthOfStaySinceBirth, setLengthOfStaySinceBirth] = useState(false);

    useEffect(() => {
        if (!lengthOfStaySinceBirth) {
            return;
        }

        const age = calculateAge(values.birthdate);
        const nextValue = age !== null ? String(age) : '';

        if (nextValue !== values.length_of_stay) {
            onChange('length_of_stay', nextValue);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [lengthOfStaySinceBirth, values.birthdate]);

    const showSpouseFields = includeSpouse && values.civil_status === 'Married';
    const hasFamilySection =
        includeCivilHousing || includeChildren || includeSpouse;
    const birthplaceProvinceSearch = useLocationSearch({
        initialQuery: values.birthplace_province,
        searchUrl: provinces.url(),
    });
    const birthplaceCitySearch = useLocationSearch({
        initialQuery: values.birthplace_city,
        searchUrl: cities.url(),
        params: {
            province: values.birthplace_province || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
    const addressProvinceSearch = useLocationSearch({
        initialQuery: values.address3,
        searchUrl: provinces.url(),
    });
    const addressCitySearch = useLocationSearch({
        initialQuery: values.address2,
        searchUrl: cities.url(),
        params: {
            province: values.address3 || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
    const addressBarangaySearch = useLocationSearch({
        initialQuery: values.address_barangay,
        searchUrl: barangays.url(),
        params: {
            municipality: values.address2 || undefined,
            province: values.address3 || undefined,
        },
        clientFilter: true,
        limit: 500,
    });

    const handleAddressCitySelect = async (code: string) => {
        if (!code) {
            return;
        }

        try {
            const response = await api.get(zip.url(), {
                params: { locality_code: code },
            });
            const resolvedZip = (
                response.data as { zip?: string | null }
            ).zip?.trim();

            onChange('address_zip', resolvedZip ?? '');
        } catch {
            // Intentionally left empty: ZIP lookup is best-effort.
        }
    };
    const birthplaceProvinceInputClass = cn(
        'mt-1 block w-full',
        isReadOnly('birthplace_province') && readOnlyInputClass,
    );
    const birthplaceCityInputClass = cn(
        'mt-1 block w-full',
        isReadOnly('birthplace_city') && readOnlyInputClass,
    );
    const addressCityInputClass = cn(
        'mt-1 block w-full',
        isReadOnly('address2') && readOnlyInputClass,
    );
    const addressProvinceInputClass = cn(
        'mt-1 block w-full',
        isReadOnly('address3') && readOnlyInputClass,
    );
    const updateField =
        (field: keyof LoanRequestPersonFormData) =>
        (event: ChangeEvent<HTMLInputElement>) => {
            onChange(field, event.target.value);
        };
    const updateMobileField =
        (field: keyof LoanRequestPersonFormData) =>
        (event: ChangeEvent<HTMLInputElement>) => {
            onChange(field, normalizeMobileNumberInput(event.target.value));
        };

    return (
        <div className="space-y-7">
            {hasReadOnlyFields ? (
                <div className="rounded-lg border border-border bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
                    Verified profile fields are locked. To change this, visit
                    the office.
                </div>
            ) : null}
            {section === 'all' || section === 'basic' ? (
                <div className={rows ? undefined : 'grid min-w-0 gap-5 md:grid-cols-2'}>
                    <FieldRow
                        rows={rows}
                        label="First name"
                        field="first_name"
                        values={values}
                        readOnlyFields={readOnly}
                    >
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
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'first_name'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Last name"
                        field="last_name"
                        values={values}
                        readOnlyFields={readOnly}
                    >
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
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'last_name'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Middle name"
                        field="middle_name"
                        values={values}
                        readOnlyFields={readOnly}
                    >
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
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'middle_name'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Nickname"
                        field="nickname"
                        values={values}
                        readOnlyFields={readOnly}
                    >
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
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'nickname'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Birthdate"
                        field="birthdate"
                        values={values}
                        readOnlyFields={readOnly}
                    >
                        <FieldLabel
                            htmlFor={`${prefix}_birthdate`}
                            label="Birthdate"
                            isReadOnly={isReadOnly('birthdate')}
                        />
                        <BirthdateInput
                            id={`${prefix}_birthdate`}
                            name={fieldName(prefix, 'birthdate')}
                            value={values.birthdate}
                            readOnly={isReadOnly('birthdate')}
                            required
                            className={cn(
                                isReadOnly('birthdate') && readOnlyInputClass,
                            )}
                            onValueChange={(value) =>
                                onChange('birthdate', value)
                            }
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'birthdate'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Birthplace province"
                        field="birthplace_province"
                        values={values}
                        readOnlyFields={readOnly}
                    >
                        <FieldLabel
                            htmlFor={`${prefix}_birthplace_province`}
                            label="Birthplace province"
                            isReadOnly={isReadOnly('birthplace_province')}
                        />
                        <LocationCombobox
                            id={`${prefix}_birthplace_province`}
                            name={fieldName(prefix, 'birthplace_province')}
                            search={birthplaceProvinceSearch}
                            placeholder="Select province"
                            required
                            readOnly={isReadOnly('birthplace_province')}
                            portal={portal}
                            inputClassName={birthplaceProvinceInputClass}
                            loadingMessage="Searching province suggestions..."
                            errorMessage="Province suggestions are temporarily unavailable."
                            promptMessage="Type at least 2 characters to search provinces."
                            onValueChange={(value) =>
                                onChange('birthplace_province', value)
                            }
                            onSelect={() => {
                                if (!isReadOnly('birthplace_city')) {
                                    birthplaceCitySearch.setSelectedValue('');
                                    onChange('birthplace_city', '');
                                }
                            }}
                            onClear={() => {
                                if (!isReadOnly('birthplace_city')) {
                                    birthplaceCitySearch.setSelectedValue('');
                                    onChange('birthplace_city', '');
                                }
                            }}
                            aria-invalid={Boolean(
                                fieldError(
                                    errors,
                                    prefix,
                                    'birthplace_province',
                                ),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Birthplace city/municipality"
                        field="birthplace_city"
                        values={values}
                        readOnlyFields={readOnly}
                    >
                        <FieldLabel
                            htmlFor={`${prefix}_birthplace_city`}
                            label="Birthplace city/municipality"
                            isReadOnly={isReadOnly('birthplace_city')}
                        />
                        <LocationCombobox
                            id={`${prefix}_birthplace_city`}
                            name={fieldName(prefix, 'birthplace_city')}
                            search={birthplaceCitySearch}
                            placeholder="Select city or municipality"
                            required
                            readOnly={isReadOnly('birthplace_city')}
                            disabled={!values.birthplace_province}
                            portal={portal}
                            inputClassName={birthplaceCityInputClass}
                            loadingMessage="Searching city suggestions..."
                            errorMessage="City suggestions are temporarily unavailable."
                            promptMessage="Select a province first."
                            onSelect={(suggestion) => {
                                if (suggestion.province) {
                                    birthplaceProvinceSearch.setSelectedValue(
                                        suggestion.province,
                                    );
                                    onChange(
                                        'birthplace_province',
                                        suggestion.province,
                                    );
                                }
                            }}
                            onValueChange={(value) =>
                                onChange('birthplace_city', value)
                            }
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'birthplace_city'),
                            )}
                        />
                    </FieldRow>

                    {includeCivilHousing ? (
                        <FieldRow
                            rows={rows}
                            label="Sex"
                            field="sex"
                            values={values}
                            readOnlyFields={readOnly}
                        >
                            <FieldLabel
                                htmlFor={`${prefix}_sex`}
                                label="Sex"
                                isReadOnly={isReadOnly('sex')}
                            />
                            <Select
                                value={values.sex || undefined}
                                onValueChange={(value) =>
                                    onChange('sex', value)
                                }
                                disabled={isReadOnly('sex')}
                            >
                                <SelectTrigger
                                    id={`${prefix}_sex`}
                                    className={cn(
                                        'mt-1 w-full',
                                        isReadOnly('sex') && readOnlyInputClass,
                                    )}
                                    aria-invalid={Boolean(
                                        fieldError(errors, prefix, 'sex'),
                                    )}
                                >
                                    <SelectValue placeholder="Select sex" />
                                </SelectTrigger>
                                <SelectContent>
                                    {SEX_OPTIONS.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FieldRow>
                    ) : null}
                </div>
            ) : null}

            {section === 'all' ? <Separator className="bg-border/40" /> : null}

            {section === 'all' || section === 'contact' ? (
                <div className={rows ? undefined : 'grid min-w-0 gap-5 md:grid-cols-2'}>
                    <FieldRow
                        rows={rows}
                        label="Province"
                        field="address3"
                        values={values}
                        readOnlyFields={readOnly}
                    >
                        <FieldLabel
                            htmlFor={`${prefix}_address3`}
                            label="Province"
                            isReadOnly={isReadOnly('address3')}
                        />
                        <LocationCombobox
                            id={`${prefix}_address3`}
                            name={fieldName(prefix, 'address3')}
                            search={addressProvinceSearch}
                            placeholder="Select province"
                            required
                            readOnly={isReadOnly('address3')}
                            portal={portal}
                            inputClassName={addressProvinceInputClass}
                            loadingMessage="Searching province suggestions..."
                            errorMessage="Province suggestions are temporarily unavailable."
                            promptMessage="Type at least 2 characters to search provinces."
                            onValueChange={(value) =>
                                onChange('address3', value)
                            }
                            onSelect={() => {
                                if (!isReadOnly('address2')) {
                                    addressCitySearch.setSelectedValue('');
                                    onChange('address2', '');
                                }
                                if (!isReadOnly('address_barangay')) {
                                    addressBarangaySearch.setSelectedValue('');
                                    onChange('address_barangay', '');
                                }
                                onChange('address_zip', '');
                            }}
                            onClear={() => {
                                if (!isReadOnly('address2')) {
                                    addressCitySearch.setSelectedValue('');
                                    onChange('address2', '');
                                }
                                if (!isReadOnly('address_barangay')) {
                                    addressBarangaySearch.setSelectedValue('');
                                    onChange('address_barangay', '');
                                }
                                onChange('address_zip', '');
                            }}
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'address3'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="City/Municipality"
                        field="address2"
                        values={values}
                        readOnlyFields={readOnly}
                    >
                        <FieldLabel
                            htmlFor={`${prefix}_address2`}
                            label="City/Municipality"
                            isReadOnly={isReadOnly('address2')}
                        />
                        <LocationCombobox
                            id={`${prefix}_address2`}
                            name={fieldName(prefix, 'address2')}
                            search={addressCitySearch}
                            placeholder="Select city or municipality"
                            required
                            readOnly={isReadOnly('address2')}
                            disabled={!values.address3}
                            portal={portal}
                            inputClassName={addressCityInputClass}
                            loadingMessage="Searching city suggestions..."
                            errorMessage="City suggestions are temporarily unavailable."
                            promptMessage="Select a province first."
                            onValueChange={(value) =>
                                onChange('address2', value)
                            }
                            onSelect={(suggestion) => {
                                if (suggestion.province) {
                                    addressProvinceSearch.setSelectedValue(
                                        suggestion.province,
                                    );
                                    onChange('address3', suggestion.province);
                                }

                                if (!isReadOnly('address_barangay')) {
                                    addressBarangaySearch.setSelectedValue('');
                                    onChange('address_barangay', '');
                                }
                                void handleAddressCitySelect(suggestion.code);
                            }}
                            onClear={() => {
                                if (!isReadOnly('address_barangay')) {
                                    addressBarangaySearch.setSelectedValue('');
                                    onChange('address_barangay', '');
                                }
                                onChange('address_zip', '');
                            }}
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'address2'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="ZIP code"
                        field="address_zip"
                        values={values}
                        readOnlyFields={readOnly}
                    >
                        <FieldLabel
                            htmlFor={`${prefix}_address_zip`}
                            label="ZIP code"
                            isReadOnly={isReadOnly('address_zip')}
                        />
                        <Input
                            id={`${prefix}_address_zip`}
                            name={fieldName(prefix, 'address_zip')}
                            value={values.address_zip}
                            inputMode="numeric"
                            autoComplete="postal-code"
                            readOnly={isReadOnly('address_zip')}
                            placeholder="Auto-filled from city"
                            onChange={(event) =>
                                onChange('address_zip', event.target.value)
                            }
                            className={cn(
                                'mt-1 block w-full',
                                isReadOnly('address_zip') && readOnlyInputClass,
                            )}
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'address_zip'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Barangay"
                        field="address_barangay"
                        values={values}
                        readOnlyFields={readOnly}
                    >
                        <FieldLabel
                            htmlFor={`${prefix}_address_barangay`}
                            label="Barangay"
                            isReadOnly={isReadOnly('address_barangay')}
                        />
                        <LocationCombobox
                            id={`${prefix}_address_barangay`}
                            name={fieldName(prefix, 'address_barangay')}
                            search={addressBarangaySearch}
                            placeholder="Select barangay"
                            readOnly={isReadOnly('address_barangay')}
                            disabled={!values.address2}
                            portal={portal}
                            inputClassName={cn(
                                'mt-1 block w-full',
                                isReadOnly('address_barangay') &&
                                    readOnlyInputClass,
                            )}
                            loadingMessage="Loading barangays..."
                            errorMessage="Barangay suggestions are temporarily unavailable."
                            promptMessage="Select a city or municipality first."
                            onValueChange={(value) =>
                                onChange('address_barangay', value)
                            }
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'address_barangay'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Address (street)"
                        field="address1"
                        values={values}
                        readOnlyFields={readOnly}
                    >
                        <FieldLabel
                            htmlFor={`${prefix}_address1`}
                            label="Address (street)"
                            isReadOnly={isReadOnly('address1')}
                        />
                        <Input
                            id={`${prefix}_address1`}
                            name={fieldName(prefix, 'address1')}
                            value={values.address1}
                            readOnly={isReadOnly('address1')}
                            required
                            className={cn(
                                'mt-1 block w-full',
                                isReadOnly('address1') && readOnlyInputClass,
                            )}
                            onChange={updateField('address1')}
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'address1'),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Length of stay"
                        field="length_of_stay"
                        values={values}
                        readOnlyFields={readOnly}
                        group
                    >
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor={`${prefix}_length_of_stay`}>
                                Length of stay
                            </Label>
                            <Label
                                htmlFor={`${prefix}_length_of_stay_since_birth`}
                                className="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Checkbox
                                    id={`${prefix}_length_of_stay_since_birth`}
                                    checked={lengthOfStaySinceBirth}
                                    disabled={!values.birthdate}
                                    onCheckedChange={(checked) => {
                                        const isChecked = checked === true;
                                        setLengthOfStaySinceBirth(isChecked);

                                        if (isChecked) {
                                            const age = calculateAge(
                                                values.birthdate,
                                            );
                                            onChange(
                                                'length_of_stay',
                                                age !== null ? String(age) : '',
                                            );
                                        }
                                    }}
                                />
                                Since birth
                            </Label>
                        </div>
                        <YearsInput
                            id={`${prefix}_length_of_stay`}
                            value={values.length_of_stay}
                            className="mt-1 block w-full"
                            placeholder="e.g. 2"
                            required
                            disabled={lengthOfStaySinceBirth}
                            onChange={(value) =>
                                onChange('length_of_stay', value)
                            }
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'length_of_stay'),
                            )}
                        />
                    </FieldRow>

                    {includeCivilHousing ? (
                        <FieldRow
                            rows={rows}
                            label="Housing status"
                            field="housing_status"
                            values={values}
                            readOnlyFields={readOnly}
                        >
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
                                    aria-invalid={Boolean(
                                        fieldError(
                                            errors,
                                            prefix,
                                            'housing_status',
                                        ),
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
                        </FieldRow>
                    ) : null}

                    <FieldRow
                        rows={rows}
                        label="Cell no."
                        field="cell_no"
                        values={values}
                        readOnlyFields={readOnly}
                    >
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
                            maxLength={11}
                            placeholder="09XXXXXXXXX"
                            required
                            onChange={updateMobileField('cell_no')}
                            aria-invalid={Boolean(
                                fieldError(errors, prefix, 'cell_no'),
                            )}
                        />
                        {contactNumberOnFile ? (
                            <p className="text-xs text-muted-foreground">
                                On file: {contactNumberOnFile} -- this is what
                                prints on the insurance documents (Generali,
                                Grepalife), not this field.
                            </p>
                        ) : null}
                    </FieldRow>

                    {!hasFamilySection ? (
                        <FieldRow
                            rows={rows}
                            label="Educational attainment"
                            field="educational_attainment"
                            values={values}
                            readOnlyFields={readOnly}
                        >
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
                                    aria-invalid={Boolean(
                                        fieldError(
                                            errors,
                                            prefix,
                                            'educational_attainment',
                                        ),
                                    )}
                                >
                                    <SelectValue placeholder="Select attainment" />
                                </SelectTrigger>
                                <SelectContent>
                                    {educationalAttainmentOptions.map(
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
                        </FieldRow>
                    ) : null}
                </div>
            ) : null}

            {section === 'all' ? <Separator className="bg-border/40" /> : null}

            {section === 'all' || section === 'family' ? (
                <div className={rows ? undefined : 'grid min-w-0 gap-5 md:grid-cols-2'}>
                    {includeCivilHousing ? (
                        <FieldRow
                            rows={rows}
                            label="Civil status"
                            field="civil_status"
                            values={values}
                            readOnlyFields={readOnly}
                        >
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
                                    aria-invalid={Boolean(
                                        fieldError(
                                            errors,
                                            prefix,
                                            'civil_status',
                                        ),
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
                        </FieldRow>
                    ) : null}

                    {hasFamilySection ? (
                        <FieldRow
                            rows={rows}
                            label="Educational attainment"
                            field="educational_attainment"
                            values={values}
                            readOnlyFields={readOnly}
                        >
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
                                    aria-invalid={Boolean(
                                        fieldError(
                                            errors,
                                            prefix,
                                            'educational_attainment',
                                        ),
                                    )}
                                >
                                    <SelectValue placeholder="Select attainment" />
                                </SelectTrigger>
                                <SelectContent>
                                    {educationalAttainmentOptions.map(
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
                        </FieldRow>
                    ) : null}

                    {includeChildren ? (
                        <FieldRow
                            rows={rows}
                            label="No. of children"
                            field="number_of_children"
                            values={values}
                            readOnlyFields={readOnly}
                        >
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
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'number_of_children',
                                    ),
                                )}
                            />
                        </FieldRow>
                    ) : null}

                    {showSpouseFields ? (
                        <>
                            <FieldRow
                                rows={rows}
                                label="Spouse name"
                                field="spouse_name"
                                values={values}
                                readOnlyFields={readOnly}
                            >
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
                                    aria-invalid={Boolean(
                                        fieldError(
                                            errors,
                                            prefix,
                                            'spouse_name',
                                        ),
                                    )}
                                />
                            </FieldRow>

                            <FieldRow
                                rows={rows}
                                label="Spouse birthdate"
                                field="spouse_birthdate"
                                values={values}
                                readOnlyFields={readOnly}
                            >
                                <FieldLabel
                                    htmlFor={`${prefix}_spouse_birthdate`}
                                    label="Spouse birthdate"
                                />
                                <BirthdateInput
                                    id={`${prefix}_spouse_birthdate`}
                                    name={fieldName(prefix, 'spouse_birthdate')}
                                    value={values.spouse_birthdate}
                                    onValueChange={(value) =>
                                        onChange('spouse_birthdate', value)
                                    }
                                    aria-invalid={Boolean(
                                        fieldError(
                                            errors,
                                            prefix,
                                            'spouse_birthdate',
                                        ),
                                    )}
                                />
                            </FieldRow>

                            <FieldRow
                                rows={rows}
                                label="Spouse age"
                                field="spouse_age_display"
                                values={values}
                                readOnlyFields={readOnly}
                                readOnly
                            >
                                <FieldLabel
                                    htmlFor={`${prefix}_spouse_age_display`}
                                    label="Spouse age"
                                />
                                <Input
                                    id={`${prefix}_spouse_age_display`}
                                    type="number"
                                    className="mt-1 block w-full"
                                    value={
                                        calculateAge(values.spouse_birthdate) ??
                                        ''
                                    }
                                    placeholder="Computed from birthdate"
                                    disabled
                                    readOnly
                                />
                            </FieldRow>

                            <FieldRow
                                rows={rows}
                                label="Spouse cell no."
                                field="spouse_cell_no"
                                values={values}
                                readOnlyFields={readOnly}
                            >
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
                                    maxLength={11}
                                    placeholder="09XXXXXXXXX"
                                    onChange={updateMobileField(
                                        'spouse_cell_no',
                                    )}
                                    aria-invalid={Boolean(
                                        fieldError(
                                            errors,
                                            prefix,
                                            'spouse_cell_no',
                                        ),
                                    )}
                                />
                            </FieldRow>
                        </>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

type WorkFieldsProps = {
    prefix: string;
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    portal?: boolean;
    rows?: boolean;
    section?: 'all' | 'employment' | 'income';
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
};

export function LoanRequestWorkFields({
    prefix,
    values,
    errors,
    portal = true,
    rows = false,
    section = 'all',
    onChange,
}: WorkFieldsProps) {
    const employmentType = values.employment_type;
    const isPensioner = isPensionerType(employmentType);
    const isSelfEmployed = isSelfEmployedType(employmentType);

    const employmentTypeOptions =
        employmentType !== '' &&
        !EMPLOYMENT_TYPE_OPTIONS.some(
            (option) => option.value === employmentType,
        )
            ? [
                  { value: employmentType, label: employmentType },
                  ...EMPLOYMENT_TYPE_OPTIONS,
              ]
            : EMPLOYMENT_TYPE_OPTIONS;
    const employerProvinceSearch = useLocationSearch({
        initialQuery: values.employer_business_address3,
        searchUrl: provinces.url(),
    });
    const employerCitySearch = useLocationSearch({
        initialQuery: values.employer_business_address2,
        searchUrl: cities.url(),
        params: {
            province: values.employer_business_address3 || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
    const employerBarangaySearch = useLocationSearch({
        initialQuery: values.employer_business_address_barangay,
        searchUrl: barangays.url(),
        params: {
            municipality: values.employer_business_address2 || undefined,
            province: values.employer_business_address3 || undefined,
        },
        clientFilter: true,
        limit: 500,
    });

    const handleEmployerCitySelect = async (code: string) => {
        if (!code) {
            return;
        }

        try {
            const response = await api.get(zip.url(), {
                params: { locality_code: code },
            });
            const resolvedZip = (
                response.data as { zip?: string | null }
            ).zip?.trim();

            if (resolvedZip) {
                onChange('employer_business_address_zip', resolvedZip);
            }
        } catch {
            // Intentionally left empty: ZIP lookup is best-effort.
        }
    };

    const [natureOfBusinessSelection, setNatureOfBusinessSelection] =
        useState<string>(() =>
            resolveNatureOfBusinessSelection(values.nature_of_business),
        );
    const [natureOfBusinessOther, setNatureOfBusinessOther] = useState<string>(
        () => resolveNatureOfBusinessOther(values.nature_of_business),
    );

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
            {section === 'all' || section === 'employment' ? (
                <div className={rows ? undefined : 'grid min-w-0 gap-5 md:grid-cols-2'}>
                    <FieldRow
                        rows={rows}
                        label="Employment"
                        field="employment_type"
                        values={values}
                    >
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
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'employment_type',
                                    ),
                                )}
                            >
                                <SelectValue placeholder="Select employment" />
                            </SelectTrigger>
                            <SelectContent>
                                {employmentTypeOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FieldRow>

                    {!isPensioner ? (
                        <FieldRow
                            rows={rows}
                            label="Employer/Business name"
                            field="employer_business_name"
                            values={values}
                        >
                            <Label htmlFor={`${prefix}_employer_business_name`}>
                                Employer/Business name
                            </Label>
                            <Input
                                id={`${prefix}_employer_business_name`}
                                name={fieldName(
                                    prefix,
                                    'employer_business_name',
                                )}
                                value={values.employer_business_name}
                                className="mt-1 block w-full"
                                onChange={(event) =>
                                    onChange(
                                        'employer_business_name',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'employer_business_name',
                                    ),
                                )}
                            />
                        </FieldRow>
                    ) : null}

                    {!isPensioner && prefix === 'applicant' ? (
                        <FieldRow
                            rows={rows}
                            label="Business province"
                            field="employer_business_address3"
                            values={values}
                        >
                            <Label
                                htmlFor={`${prefix}_employer_business_address3`}
                            >
                                Province
                            </Label>
                            <LocationCombobox
                                id={`${prefix}_employer_business_address3`}
                                name={fieldName(
                                    prefix,
                                    'employer_business_address3',
                                )}
                                search={employerProvinceSearch}
                                placeholder="Select province"
                                portal={portal}
                                inputClassName="mt-1 block w-full"
                                loadingMessage="Searching province suggestions..."
                                errorMessage="Province suggestions are temporarily unavailable."
                                promptMessage="Type at least 2 characters to search provinces."
                                onValueChange={(value) =>
                                    onChange(
                                        'employer_business_address3',
                                        value,
                                    )
                                }
                                onSelect={() => {
                                    employerCitySearch.setSelectedValue('');
                                    onChange('employer_business_address2', '');
                                    employerBarangaySearch.setSelectedValue('');
                                    onChange(
                                        'employer_business_address_barangay',
                                        '',
                                    );
                                    onChange(
                                        'employer_business_address_zip',
                                        '',
                                    );
                                }}
                                onClear={() => {
                                    employerCitySearch.setSelectedValue('');
                                    onChange('employer_business_address2', '');
                                    employerBarangaySearch.setSelectedValue('');
                                    onChange(
                                        'employer_business_address_barangay',
                                        '',
                                    );
                                    onChange(
                                        'employer_business_address_zip',
                                        '',
                                    );
                                }}
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'employer_business_address3',
                                    ),
                                )}
                            />
                        </FieldRow>
                    ) : null}

                    {!isPensioner && prefix === 'applicant' ? (
                        <FieldRow
                            rows={rows}
                            label="Business city/municipality"
                            field="employer_business_address2"
                            values={values}
                        >
                            <Label
                                htmlFor={`${prefix}_employer_business_address2`}
                            >
                                City/Municipality
                            </Label>
                            <LocationCombobox
                                id={`${prefix}_employer_business_address2`}
                                name={fieldName(
                                    prefix,
                                    'employer_business_address2',
                                )}
                                search={employerCitySearch}
                                placeholder="Select city or municipality"
                                disabled={!values.employer_business_address3}
                                portal={portal}
                                inputClassName="mt-1 block w-full"
                                loadingMessage="Searching city suggestions..."
                                errorMessage="City suggestions are temporarily unavailable."
                                promptMessage="Select a province first."
                                onValueChange={(value) =>
                                    onChange(
                                        'employer_business_address2',
                                        value,
                                    )
                                }
                                onSelect={(suggestion) => {
                                    if (suggestion.province) {
                                        employerProvinceSearch.setSelectedValue(
                                            suggestion.province,
                                        );
                                        onChange(
                                            'employer_business_address3',
                                            suggestion.province,
                                        );
                                    }

                                    employerBarangaySearch.setSelectedValue('');
                                    onChange(
                                        'employer_business_address_barangay',
                                        '',
                                    );
                                    void handleEmployerCitySelect(
                                        suggestion.code,
                                    );
                                }}
                                onClear={() => {
                                    employerBarangaySearch.setSelectedValue('');
                                    onChange(
                                        'employer_business_address_barangay',
                                        '',
                                    );
                                    onChange(
                                        'employer_business_address_zip',
                                        '',
                                    );
                                }}
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'employer_business_address2',
                                    ),
                                )}
                            />
                        </FieldRow>
                    ) : null}

                    {!isPensioner && prefix === 'applicant' ? (
                        <FieldRow
                            rows={rows}
                            label="Business ZIP code"
                            field="employer_business_address_zip"
                            values={values}
                        >
                            <Label
                                htmlFor={`${prefix}_employer_business_address_zip`}
                            >
                                ZIP code
                            </Label>
                            <Input
                                id={`${prefix}_employer_business_address_zip`}
                                name={fieldName(
                                    prefix,
                                    'employer_business_address_zip',
                                )}
                                value={values.employer_business_address_zip}
                                inputMode="numeric"
                                autoComplete="postal-code"
                                placeholder="Auto-filled from city"
                                onChange={(event) =>
                                    onChange(
                                        'employer_business_address_zip',
                                        event.target.value,
                                    )
                                }
                                className="mt-1 block w-full"
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'employer_business_address_zip',
                                    ),
                                )}
                            />
                        </FieldRow>
                    ) : null}

                    {!isPensioner && prefix === 'applicant' ? (
                        <FieldRow
                            rows={rows}
                            label="Business barangay"
                            field="employer_business_address_barangay"
                            values={values}
                        >
                            <Label
                                htmlFor={`${prefix}_employer_business_address_barangay`}
                            >
                                Barangay
                            </Label>
                            <LocationCombobox
                                id={`${prefix}_employer_business_address_barangay`}
                                name={fieldName(
                                    prefix,
                                    'employer_business_address_barangay',
                                )}
                                search={employerBarangaySearch}
                                placeholder="Select barangay"
                                disabled={!values.employer_business_address2}
                                portal={portal}
                                inputClassName="mt-1 block w-full"
                                loadingMessage="Loading barangays..."
                                errorMessage="Barangay suggestions are temporarily unavailable."
                                promptMessage="Select a city or municipality first."
                                onValueChange={(value) =>
                                    onChange(
                                        'employer_business_address_barangay',
                                        value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'employer_business_address_barangay',
                                    ),
                                )}
                            />
                        </FieldRow>
                    ) : null}

                    {!isPensioner && prefix === 'applicant' ? (
                        <FieldRow
                            rows={rows}
                            label="Employer/Business address (street)"
                            field="employer_business_address1"
                            values={values}
                        >
                            <Label
                                htmlFor={`${prefix}_employer_business_address1`}
                            >
                                Employer/Business address (street)
                            </Label>
                            <Input
                                id={`${prefix}_employer_business_address1`}
                                name={fieldName(
                                    prefix,
                                    'employer_business_address1',
                                )}
                                value={values.employer_business_address1}
                                className="mt-1 block w-full"
                                onChange={(event) =>
                                    onChange(
                                        'employer_business_address1',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'employer_business_address1',
                                    ),
                                )}
                            />
                        </FieldRow>
                    ) : null}
                </div>
            ) : null}

            {(section === 'all' || section === 'income') && !isPensioner ? (
                <>
                    {section === 'all' ? (
                        <Separator className="bg-border/40" />
                    ) : null}

                    <div
                        className={
                            rows ? undefined : 'grid gap-5 md:grid-cols-2'
                        }
                    >
                        <FieldRow
                            rows={rows}
                            label="Tel. no."
                            field="telephone_no"
                            values={values}
                        >
                            <Label htmlFor={`${prefix}_telephone_no`}>
                                Tel. no.
                            </Label>
                            <Input
                                id={`${prefix}_telephone_no`}
                                name={fieldName(prefix, 'telephone_no')}
                                value={values.telephone_no}
                                className="mt-1 block w-full"
                                onChange={(event) =>
                                    onChange('telephone_no', event.target.value)
                                }
                                aria-invalid={Boolean(
                                    fieldError(errors, prefix, 'telephone_no'),
                                )}
                            />
                        </FieldRow>

                        <FieldRow
                            rows={rows}
                            label="Current position"
                            field="current_position"
                            values={values}
                        >
                            <Label htmlFor={`${prefix}_current_position`}>
                                Current position
                            </Label>
                            <Input
                                id={`${prefix}_current_position`}
                                name={fieldName(prefix, 'current_position')}
                                value={values.current_position}
                                className="mt-1 block w-full"
                                onChange={(event) =>
                                    onChange(
                                        'current_position',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'current_position',
                                    ),
                                )}
                            />
                        </FieldRow>

                        <FieldRow
                            rows={rows}
                            label="Nature of business"
                            field="nature_of_business"
                            values={values}
                        >
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
                                    aria-invalid={Boolean(
                                        fieldError(
                                            errors,
                                            prefix,
                                            'nature_of_business',
                                        ),
                                    )}
                                >
                                    <SelectValue placeholder="Select nature of business" />
                                </SelectTrigger>
                                <SelectContent>
                                    {NATURE_OF_BUSINESS_OPTIONS.map(
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
                        </FieldRow>

                        {natureOfBusinessSelection ===
                        NATURE_OF_BUSINESS_OTHER_VALUE ? (
                            <FieldRow
                                rows={rows}
                                label="Specify industry"
                                field="nature_of_business_other"
                                values={values}
                                startOpen
                            >
                                <Label
                                    htmlFor={`${prefix}_nature_of_business_other`}
                                >
                                    Specify industry
                                </Label>
                                <Input
                                    id={`${prefix}_nature_of_business_other`}
                                    className="mt-1 w-full"
                                    value={natureOfBusinessOther}
                                    placeholder="Specify industry"
                                    onChange={handleNatureOfBusinessOtherChange}
                                />
                            </FieldRow>
                        ) : null}

                        <FieldRow
                            rows={rows}
                            label="Total years in work/business"
                            field="years_in_work_business"
                            values={values}
                        >
                            <Label htmlFor={`${prefix}_years_in_work_business`}>
                                Total years in work/business
                            </Label>
                            <YearsInput
                                id={`${prefix}_years_in_work_business`}
                                value={values.years_in_work_business}
                                className="mt-1 block w-full"
                                placeholder="e.g. 5"
                                onChange={(value) =>
                                    onChange('years_in_work_business', value)
                                }
                                aria-invalid={Boolean(
                                    fieldError(
                                        errors,
                                        prefix,
                                        'years_in_work_business',
                                    ),
                                )}
                            />
                        </FieldRow>

                        {prefix === 'applicant' && !isSelfEmployed ? (
                            <FieldRow
                                rows={rows}
                                label="Date employed"
                                field="employer_date_employed"
                                values={values}
                            >
                                <Label
                                    htmlFor={`${prefix}_employer_date_employed`}
                                >
                                    Date employed
                                </Label>
                                <DateInputWithPicker
                                    id={`${prefix}_employer_date_employed`}
                                    name={fieldName(
                                        prefix,
                                        'employer_date_employed',
                                    )}
                                    value={values.employer_date_employed}
                                    onChange={(value) =>
                                        onChange(
                                            'employer_date_employed',
                                            value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        fieldError(
                                            errors,
                                            prefix,
                                            'employer_date_employed',
                                        ),
                                    )}
                                    aria-label="Date employed"
                                />
                            </FieldRow>
                        ) : null}
                    </div>
                </>
            ) : null}

            {section === 'all' || (section === 'income' && !isPensioner) ? (
                <Separator className="bg-border/40" />
            ) : null}

            {section === 'all' || section === 'income' ? (
                <div className={rows ? undefined : 'grid min-w-0 gap-5 md:grid-cols-2'}>
                    <FieldRow
                        rows={rows}
                        label="Gross monthly income"
                        field="gross_monthly_income"
                        values={values}
                    >
                        <Label htmlFor={`${prefix}_gross_monthly_income`}>
                            Gross monthly income
                        </Label>
                        <CurrencyInput
                            id={`${prefix}_gross_monthly_income`}
                            value={values.gross_monthly_income}
                            onValueChange={(value) =>
                                onChange('gross_monthly_income', value)
                            }
                            required
                            aria-invalid={Boolean(
                                fieldError(
                                    errors,
                                    prefix,
                                    'gross_monthly_income',
                                ),
                            )}
                        />
                    </FieldRow>

                    <FieldRow
                        rows={rows}
                        label="Payday"
                        field="payday"
                        values={values}
                    >
                        <Label htmlFor={`${prefix}_payday`}>Payday</Label>
                        <Select
                            value={values.payday || undefined}
                            onValueChange={(value) => onChange('payday', value)}
                        >
                            <SelectTrigger
                                id={`${prefix}_payday`}
                                className="mt-1 w-full"
                                aria-invalid={Boolean(
                                    fieldError(errors, prefix, 'payday'),
                                )}
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
                    </FieldRow>
                </div>
            ) : null}
        </div>
    );
}
