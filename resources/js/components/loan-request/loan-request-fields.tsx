import type { ChangeEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { BirthdateInput } from '@/components/loan-request/birthdate-input';
import { CurrencyInput } from '@/components/loan-request/numeric-adorned-inputs';
import { LocationCombobox } from '@/components/location-combobox';
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
const PENSIONER_EMPLOYMENT_TYPE = 'Pensioner';
const SELF_EMPLOYED_EMPLOYMENT_TYPE = 'Self Employed';
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
            <span className="rounded-full border border-border/40 bg-muted/30 px-2 py-0.5 text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
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

type PersonalFieldsProps = {
    prefix: string;
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    readOnly?: LoanRequestReadOnlyMap | null;
    includeSpouse?: boolean;
    includeChildren?: boolean;
    includeCivilHousing?: boolean;
    section?: 'all' | 'basic' | 'contact' | 'family';
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
};

export function LoanRequestPersonalFields({
    prefix,
    values,
    errors,
    readOnly = null,
    includeSpouse = false,
    includeChildren = false,
    includeCivilHousing = false,
    section = 'all',
    onChange,
}: PersonalFieldsProps) {
    const educationalAttainment = values.educational_attainment;

    const educationalAttainmentOptions =
        educationalAttainment !== '' &&
        !EDUCATIONAL_ATTAINMENT_OPTIONS.includes(educationalAttainment)
            ? [educationalAttainment, ...EDUCATIONAL_ATTAINMENT_OPTIONS]
            : EDUCATIONAL_ATTAINMENT_OPTIONS;

    const isReadOnly = (field: string) => Boolean(readOnly?.[field]);
    const hasReadOnlyFields = Object.values(readOnly ?? {}).some(Boolean);
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
                <div className="rounded-lg border border-border/40 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
                    Verified profile fields are locked. Update your profile if
                    you need changes.
                </div>
            ) : null}
            {section === 'all' || section === 'basic' ? (
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
                        />
                        <InputError
                            message={fieldError(errors, prefix, 'birthdate')}
                        />
                    </div>

                    <div className="grid gap-2">
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
                        />
                        <InputError
                            message={fieldError(
                                errors,
                                prefix,
                                'birthplace_province',
                            )}
                        />
                    </div>

                    <div className="grid gap-2">
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
                        />
                        <InputError
                            message={fieldError(
                                errors,
                                prefix,
                                'birthplace_city',
                            )}
                        />
                    </div>

                    {includeCivilHousing ? (
                        <div className="grid gap-2">
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
                            <InputError
                                message={fieldError(errors, prefix, 'sex')}
                            />
                        </div>
                    ) : null}
                </div>
            ) : null}

            {section === 'all' ? <Separator className="bg-border/40" /> : null}

            {section === 'all' || section === 'contact' ? (
                <div className="grid gap-5 md:grid-cols-2">
                    <div className="grid gap-2">
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
                        />
                        <InputError
                            message={fieldError(errors, prefix, 'address3')}
                        />
                    </div>

                    <div className="grid gap-2">
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
                        />
                        <InputError
                            message={fieldError(errors, prefix, 'address2')}
                        />
                    </div>

                    <div className="grid gap-2">
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
                            readOnly
                            placeholder="Auto-filled from city"
                            className={cn(
                                'mt-1 block w-full',
                                readOnlyInputClass,
                            )}
                        />
                        <InputError
                            message={fieldError(errors, prefix, 'address_zip')}
                        />
                    </div>

                    <div className="grid gap-2">
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
                        />
                        <InputError
                            message={fieldError(
                                errors,
                                prefix,
                                'address_barangay',
                            )}
                        />
                    </div>

                    <div className="grid gap-2 md:col-span-2">
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
                        />
                        <InputError
                            message={fieldError(errors, prefix, 'address1')}
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
                            message={fieldError(
                                errors,
                                prefix,
                                'length_of_stay',
                            )}
                        />
                    </div>

                    {includeCivilHousing ? (
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
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'housing_status',
                                )}
                            />
                        </div>
                    ) : null}

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
                            maxLength={11}
                            placeholder="09XXXXXXXXX"
                            required
                            onChange={updateMobileField('cell_no')}
                        />
                        <InputError
                            message={fieldError(errors, prefix, 'cell_no')}
                        />
                    </div>

                    {!hasFamilySection ? (
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
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'educational_attainment',
                                )}
                            />
                        </div>
                    ) : null}
                </div>
            ) : null}

            {section === 'all' ? <Separator className="bg-border/40" /> : null}

            {section === 'all' || section === 'family' ? (
                <div className="grid gap-5 md:grid-cols-2">
                    {includeCivilHousing ? (
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
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'civil_status',
                                )}
                            />
                        </div>
                    ) : null}

                    {hasFamilySection ? (
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
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'educational_attainment',
                                )}
                            />
                        </div>
                    ) : null}

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
                                    maxLength={11}
                                    placeholder="09XXXXXXXXX"
                                    onChange={updateMobileField(
                                        'spouse_cell_no',
                                    )}
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
            ) : null}
        </div>
    );
}

type WorkFieldsProps = {
    prefix: string;
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    section?: 'all' | 'employment' | 'income';
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
};

export function LoanRequestWorkFields({
    prefix,
    values,
    errors,
    section = 'all',
    onChange,
}: WorkFieldsProps) {
    const employmentType = values.employment_type;
    const isPensioner = employmentType === PENSIONER_EMPLOYMENT_TYPE;
    const isSelfEmployed = employmentType === SELF_EMPLOYED_EMPLOYMENT_TYPE;

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
                            message={fieldError(
                                errors,
                                prefix,
                                'employment_type',
                            )}
                        />
                    </div>

                    {!isPensioner ? (
                        <div className="grid gap-2">
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
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'employer_business_name',
                                )}
                            />
                        </div>
                    ) : null}

                    {!isPensioner ? (
                        <div className="grid gap-2">
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
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'employer_business_address3',
                                )}
                            />
                        </div>
                    ) : null}

                    {!isPensioner ? (
                        <div className="grid gap-2">
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
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'employer_business_address2',
                                )}
                            />
                        </div>
                    ) : null}

                    {!isPensioner ? (
                        <div className="grid gap-2">
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
                                readOnly
                                placeholder="Auto-filled from city"
                                className={cn(
                                    'mt-1 block w-full',
                                    readOnlyInputClass,
                                )}
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'employer_business_address_zip',
                                )}
                            />
                        </div>
                    ) : null}

                    {!isPensioner ? (
                        <div className="grid gap-2">
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
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'employer_business_address_barangay',
                                )}
                            />
                        </div>
                    ) : null}

                    {!isPensioner ? (
                        <div className="grid gap-2 md:col-span-2">
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
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'employer_business_address1',
                                )}
                            />
                        </div>
                    ) : null}
                </div>
            ) : null}

            {(section === 'all' || section === 'income') && !isPensioner ? (
                <>
                    {section === 'all' ? (
                        <Separator className="bg-border/40" />
                    ) : null}

                    <div className="grid gap-5 md:grid-cols-2">
                        <div className="grid gap-2">
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
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'telephone_no',
                                )}
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
                                onChange={(event) =>
                                    onChange(
                                        'current_position',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={fieldError(
                                    errors,
                                    prefix,
                                    'current_position',
                                )}
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
                                name={fieldName(
                                    prefix,
                                    'years_in_work_business',
                                )}
                                value={values.years_in_work_business}
                                className="mt-1 block w-full"
                                placeholder="e.g. 5 years"
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

                        {prefix === 'applicant' && !isSelfEmployed ? (
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`${prefix}_employer_date_employed`}
                                >
                                    Date employed
                                </Label>
                                <Input
                                    id={`${prefix}_employer_date_employed`}
                                    name={fieldName(
                                        prefix,
                                        'employer_date_employed',
                                    )}
                                    type="date"
                                    value={values.employer_date_employed}
                                    className="mt-1 block w-full"
                                    onChange={(event) =>
                                        onChange(
                                            'employer_date_employed',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={fieldError(
                                        errors,
                                        prefix,
                                        'employer_date_employed',
                                    )}
                                />
                            </div>
                        ) : null}
                    </div>
                </>
            ) : null}

            {section === 'all' || (section === 'income' && !isPensioner) ? (
                <Separator className="bg-border/40" />
            ) : null}

            {section === 'all' || section === 'income' ? (
                <div className="grid gap-5 md:grid-cols-2">
                    <div className="grid gap-2">
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
                        />
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
            ) : null}
        </div>
    );
}
