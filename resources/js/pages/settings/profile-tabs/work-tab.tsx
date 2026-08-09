import InputError from '@/components/input-error';
import { CurrencyInput } from '@/components/loan-request/numeric-adorned-inputs';
import { LocationCombobox } from '@/components/location-combobox';
import { SurfaceCard } from '@/components/surface-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { TabsContent } from '@/components/ui/tabs';
import type { useLocationSearch } from '@/hooks/use-location-search';
import { cn } from '@/lib/utils';
import {
    MISSING_FIELD_CLASS,
    NATURE_OF_BUSINESS_OPTIONS,
    NATURE_OF_BUSINESS_OTHER_VALUE,
    PAYDAY_OPTIONS,
    WMASTER_VALUE_CLASS,
    type MemberApplicationProfileData,
} from '../profile-shared';

type LocationSearchState = ReturnType<typeof useLocationSearch>;

type Props = {
    formErrors: Record<string, string>;
    memberApplicationProfile: MemberApplicationProfileData | null;
    isFieldMissing: (field: string) => boolean;
    employmentType: string;
    setEmploymentType: (value: string) => void;
    employmentTypeOptions: string[];
    isPensioner: boolean;
    isCurrentPositionFromWmaster: boolean;
    resolvedCurrentPosition: string;
    employerBusinessAddress1: string;
    employerBusinessProvinceSearch: LocationSearchState;
    employerBusinessCitySearch: LocationSearchState;
    employerBusinessBarangaySearch: LocationSearchState;
    employerBusinessAddressZipValue: string;
    setEmployerBusinessAddressZipValue: (value: string) => void;
    handleEmployerCitySelect: (code: string) => void;
    natureOfBusinessSelection: string;
    setNatureOfBusinessSelection: (value: string) => void;
    natureOfBusinessOther: string;
    setNatureOfBusinessOther: (value: string) => void;
    resolvedNatureOfBusiness: string;
    grossMonthlyIncome: string;
    setGrossMonthlyIncome: (value: string) => void;
    paydaySelection: string;
    setPaydaySelection: (value: string) => void;
};

export function WorkTab({
    formErrors,
    memberApplicationProfile,
    isFieldMissing,
    employmentType,
    setEmploymentType,
    employmentTypeOptions,
    isPensioner,
    isCurrentPositionFromWmaster,
    resolvedCurrentPosition,
    employerBusinessAddress1,
    employerBusinessProvinceSearch,
    employerBusinessCitySearch,
    employerBusinessBarangaySearch,
    employerBusinessAddressZipValue,
    setEmployerBusinessAddressZipValue,
    handleEmployerCitySelect,
    natureOfBusinessSelection,
    setNatureOfBusinessSelection,
    natureOfBusinessOther,
    setNatureOfBusinessOther,
    resolvedNatureOfBusiness,
    grossMonthlyIncome,
    setGrossMonthlyIncome,
    paydaySelection,
    setPaydaySelection,
}: Props) {
    return (
        <TabsContent value="work" forceMount className="mt-0">
            <SurfaceCard variant="muted" padding="md" className="space-y-6">
                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">
                            Work &amp; Finances
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Keep your employment and income details up to date.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="employment_type">
                                Employment type
                            </Label>

                            <Select
                                value={employmentType || undefined}
                                onValueChange={(value) => {
                                    setEmploymentType(value);
                                }}
                            >
                                <SelectTrigger
                                    id="employment_type"
                                    className={cn(
                                        'mt-1 w-full',
                                        isFieldMissing('employment_type') &&
                                            MISSING_FIELD_CLASS,
                                    )}
                                >
                                    <SelectValue placeholder="Select employment type" />
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
                                name="employment_type"
                                value={employmentType}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.employment_type}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="employer_business_name">
                                Employer or business name
                            </Label>

                            <Input
                                id="employer_business_name"
                                className={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('employer_business_name') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                defaultValue={
                                    memberApplicationProfile?.employer_business_name ??
                                    ''
                                }
                                name="employer_business_name"
                                required={!isPensioner}
                                placeholder="Employer or business name"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.employer_business_name}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="employer_business_address3">
                                Province
                            </Label>

                            <LocationCombobox
                                id="employer_business_address3"
                                name="employer_business_address3"
                                search={employerBusinessProvinceSearch}
                                placeholder="Select province"
                                required
                                inputClassName="mt-1 block w-full"
                                loadingMessage="Searching province suggestions..."
                                errorMessage="Province suggestions are temporarily unavailable."
                                promptMessage="Type at least 2 characters to search provinces."
                                onSelect={() => {
                                    employerBusinessCitySearch.setSelectedValue(
                                        '',
                                    );
                                    employerBusinessBarangaySearch.setSelectedValue(
                                        '',
                                    );
                                    setEmployerBusinessAddressZipValue('');
                                }}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.employer_business_address3}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="employer_business_address2">
                                City/Municipality
                            </Label>

                            <LocationCombobox
                                id="employer_business_address2"
                                name="employer_business_address2"
                                search={employerBusinessCitySearch}
                                placeholder="Select city or municipality"
                                required
                                disabled={
                                    !employerBusinessProvinceSearch.selectedValue
                                }
                                inputClassName="mt-1 block w-full"
                                loadingMessage="Searching city suggestions..."
                                errorMessage="City suggestions are temporarily unavailable."
                                promptMessage="Select a province first."
                                onSelect={(suggestion) => {
                                    if (suggestion.province) {
                                        employerBusinessProvinceSearch.setSelectedValue(
                                            suggestion.province,
                                        );
                                    }

                                    employerBusinessBarangaySearch.setSelectedValue(
                                        '',
                                    );
                                    void handleEmployerCitySelect(
                                        suggestion.code,
                                    );
                                }}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.employer_business_address2}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="employer_business_address_zip">
                                ZIP code
                            </Label>

                            <Input
                                id="employer_business_address_zip"
                                className="mt-1 block w-full"
                                value={employerBusinessAddressZipValue}
                                onChange={(event) =>
                                    setEmployerBusinessAddressZipValue(
                                        event.target.value,
                                    )
                                }
                                name="employer_business_address_zip"
                                inputMode="numeric"
                                autoComplete="postal-code"
                                placeholder="Auto-filled from city"
                            />

                            <InputError
                                className="mt-2"
                                message={
                                    formErrors.employer_business_address_zip
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="employer_business_address_barangay">
                                Barangay
                            </Label>

                            <LocationCombobox
                                id="employer_business_address_barangay"
                                name="employer_business_address_barangay"
                                search={employerBusinessBarangaySearch}
                                placeholder="Select barangay"
                                disabled={
                                    !employerBusinessCitySearch.selectedValue
                                }
                                inputClassName={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing(
                                        'employer_business_address_barangay',
                                    ) && MISSING_FIELD_CLASS,
                                )}
                                loadingMessage="Loading barangays..."
                                errorMessage="Barangay suggestions are temporarily unavailable."
                                promptMessage="Select a city or municipality first."
                            />

                            <InputError
                                className="mt-2"
                                message={
                                    formErrors.employer_business_address_barangay
                                }
                            />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="employer_business_address1">
                                Employer/Business address (street)
                            </Label>

                            <Input
                                id="employer_business_address1"
                                className="mt-1 block w-full"
                                defaultValue={employerBusinessAddress1}
                                name="employer_business_address1"
                                required
                                placeholder="Employer or business address"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.employer_business_address1}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="telephone_no">
                                Telephone number
                            </Label>

                            <Input
                                id="telephone_no"
                                type="tel"
                                className="mt-1 block w-full"
                                defaultValue={
                                    memberApplicationProfile?.telephone_no ?? ''
                                }
                                name="telephone_no"
                                placeholder="Telephone number"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.telephone_no}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="current_position">
                                Current position
                            </Label>

                            <Input
                                id="current_position"
                                className={cn(
                                    'mt-1 block w-full',
                                    isCurrentPositionFromWmaster &&
                                        WMASTER_VALUE_CLASS,
                                    isFieldMissing('current_position') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                defaultValue={resolvedCurrentPosition}
                                name="current_position"
                                required={!isPensioner}
                                placeholder="Current position"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.current_position}
                            />
                        </div>

                        <div className="grid gap-4 md:col-span-2 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="nature_of_business">
                                    Nature of business
                                </Label>

                                <Select
                                    value={
                                        natureOfBusinessSelection || undefined
                                    }
                                    onValueChange={(value) => {
                                        setNatureOfBusinessSelection(value);

                                        if (
                                            value !==
                                            NATURE_OF_BUSINESS_OTHER_VALUE
                                        ) {
                                            setNatureOfBusinessOther('');
                                        }
                                    }}
                                >
                                    <SelectTrigger
                                        id="nature_of_business"
                                        className="mt-1 w-full"
                                    >
                                        <SelectValue placeholder="Select an industry" />
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

                                <input
                                    type="hidden"
                                    name="nature_of_business"
                                    value={resolvedNatureOfBusiness}
                                />

                                <InputError
                                    className="mt-2"
                                    message={formErrors.nature_of_business}
                                />
                            </div>

                            {natureOfBusinessSelection ===
                                NATURE_OF_BUSINESS_OTHER_VALUE && (
                                <div className="grid gap-2">
                                    <Label htmlFor="nature_of_business_other">
                                        Specify industry
                                    </Label>

                                    <Input
                                        id="nature_of_business_other"
                                        className="mt-1 block w-full"
                                        value={natureOfBusinessOther}
                                        name="nature_of_business_other"
                                        placeholder="Describe your industry"
                                        onChange={(event) => {
                                            setNatureOfBusinessOther(
                                                event.target.value,
                                            );
                                        }}
                                    />
                                </div>
                            )}

                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="years_in_work_business">
                                    Years in work or business
                                </Label>

                                <Input
                                    id="years_in_work_business"
                                    className="mt-1 block w-full"
                                    defaultValue={
                                        memberApplicationProfile?.years_in_work_business ??
                                        ''
                                    }
                                    name="years_in_work_business"
                                    placeholder="e.g. 5 years"
                                />

                                <InputError
                                    className="mt-2"
                                    message={formErrors.years_in_work_business}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="gross_monthly_income">
                                Gross monthly income
                            </Label>

                            <CurrencyInput
                                id="gross_monthly_income"
                                className={cn(
                                    isFieldMissing('gross_monthly_income') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                value={grossMonthlyIncome}
                                onValueChange={setGrossMonthlyIncome}
                                required
                            />

                            <input
                                type="hidden"
                                name="gross_monthly_income"
                                value={grossMonthlyIncome}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.gross_monthly_income}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="payday">Payday</Label>

                            <Select
                                value={paydaySelection || undefined}
                                onValueChange={(value) => {
                                    setPaydaySelection(value);
                                }}
                            >
                                <SelectTrigger
                                    id="payday"
                                    className={cn(
                                        'mt-1 w-full',
                                        isFieldMissing('payday') &&
                                            MISSING_FIELD_CLASS,
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

                            <input
                                type="hidden"
                                name="payday"
                                value={paydaySelection}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.payday}
                            />
                        </div>
                    </div>
                </div>
            </SurfaceCard>
        </TabsContent>
    );
}
