import InputError from '@/components/input-error';
import { BirthdateInput } from '@/components/loan-request/birthdate-input';
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
    CIVIL_STATUS_OPTIONS,
    handleMobileNumberInput,
    HOUSING_STATUS_OPTIONS,
    hasWmasterValue,
    MISSING_FIELD_CLASS,
    OriginalValueHint,
    WMASTER_VALUE_CLASS,
    type MemberApplicationProfileData,
    type MemberRecord,
} from '../profile-shared';

type LocationSearchState = ReturnType<typeof useLocationSearch>;

type Props = {
    formErrors: Record<string, string>;
    memberRecord: MemberRecord | null;
    memberApplicationProfile: MemberApplicationProfileData | null;
    isFieldMissing: (field: string) => boolean;
    hasStructuredName: boolean;
    memberFirstName: string;
    memberLastName: string;
    memberMiddleName: string;
    memberDisplayName: string;
    memberAge: number | null;
    memberCivilStatus: string;
    isCivilStatusLocked: boolean;
    isHousingStatusLocked: boolean;
    isSpouseNameLocked: boolean;
    isSingle: boolean;
    numberOfChildrenValue: string | number;
    birthplaceProvinceSearch: LocationSearchState;
    birthplaceCitySearch: LocationSearchState;
    birthplaceBarangaySearch: LocationSearchState;
    homeProvinceSearch: LocationSearchState;
    homeCitySearch: LocationSearchState;
    homeBarangaySearch: LocationSearchState;
    homeAddress1: string;
    homeAddress2RawHint: string | null;
    homeAddress3RawHint: string | null;
    homeAddressBarangayRawHint: string | null;
    homeAddressZipValue: string;
    setHomeAddressZipValue: (value: string) => void;
    handleHomeCitySelect: (code: string) => void;
    civilStatusValue: string;
    setCivilStatusValue: (value: string) => void;
    housingStatusValue: string;
    setHousingStatusValue: (value: string) => void;
    spouseBirthdateValue: string;
    setSpouseBirthdateValue: (value: string) => void;
    spouseAge: number | null;
    educationalAttainment: string;
    setEducationalAttainment: (value: string) => void;
    educationalAttainmentOptions: string[];
};

export function PersonalTab({
    formErrors,
    memberRecord,
    memberApplicationProfile,
    isFieldMissing,
    hasStructuredName,
    memberFirstName,
    memberLastName,
    memberMiddleName,
    memberDisplayName,
    memberAge,
    memberCivilStatus,
    isCivilStatusLocked,
    isHousingStatusLocked,
    isSpouseNameLocked,
    isSingle,
    numberOfChildrenValue,
    birthplaceProvinceSearch,
    birthplaceCitySearch,
    birthplaceBarangaySearch,
    homeProvinceSearch,
    homeCitySearch,
    homeBarangaySearch,
    homeAddress1,
    homeAddress2RawHint,
    homeAddress3RawHint,
    homeAddressBarangayRawHint,
    homeAddressZipValue,
    setHomeAddressZipValue,
    handleHomeCitySelect,
    civilStatusValue,
    setCivilStatusValue,
    housingStatusValue,
    setHousingStatusValue,
    spouseBirthdateValue,
    setSpouseBirthdateValue,
    spouseAge,
    educationalAttainment,
    setEducationalAttainment,
    educationalAttainmentOptions,
}: Props) {
    return (
        <TabsContent value="personal" forceMount className="mt-0">
            <SurfaceCard variant="muted" padding="md" className="space-y-6">
                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">Personal</h3>
                        <p className="text-sm text-muted-foreground">
                            Review your verified member details and keep your
                            application profile updated.
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Fields with a subtle outline come from your verified
                            member record and are read-only.
                        </p>
                    </div>

                    {!memberRecord && (
                        <p className="text-sm text-muted-foreground">
                            No member record was found for this account.
                        </p>
                    )}

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="member_full_name">
                                Member full name
                            </Label>

                            <Input
                                id="member_full_name"
                                className={cn(
                                    'mt-1 block w-full',
                                    hasWmasterValue(memberDisplayName) &&
                                        WMASTER_VALUE_CLASS,
                                )}
                                defaultValue={memberDisplayName}
                                placeholder="Not available"
                                disabled
                            />
                        </div>
                        {hasStructuredName && (
                            <>
                                {memberFirstName !== '' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="member_first_name">
                                            First name
                                        </Label>

                                        <Input
                                            id="member_first_name"
                                            className={cn(
                                                'mt-1 block w-full',
                                                hasWmasterValue(
                                                    memberFirstName,
                                                ) && WMASTER_VALUE_CLASS,
                                            )}
                                            defaultValue={memberFirstName}
                                            disabled
                                        />
                                    </div>
                                )}

                                {memberLastName !== '' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="member_last_name">
                                            Last name
                                        </Label>

                                        <Input
                                            id="member_last_name"
                                            className={cn(
                                                'mt-1 block w-full',
                                                hasWmasterValue(
                                                    memberLastName,
                                                ) && WMASTER_VALUE_CLASS,
                                            )}
                                            defaultValue={memberLastName}
                                            disabled
                                        />
                                    </div>
                                )}

                                {memberMiddleName !== '' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="member_middle_name">
                                            Middle name
                                        </Label>

                                        <Input
                                            id="member_middle_name"
                                            className={cn(
                                                'mt-1 block w-full',
                                                hasWmasterValue(
                                                    memberMiddleName,
                                                ) && WMASTER_VALUE_CLASS,
                                            )}
                                            defaultValue={memberMiddleName}
                                            disabled
                                        />
                                    </div>
                                )}
                            </>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="nickname">Nickname</Label>

                            <Input
                                id="nickname"
                                className="mt-1 block w-full"
                                defaultValue={
                                    memberApplicationProfile?.nickname ?? ''
                                }
                                name="nickname"
                                autoComplete="nickname"
                                placeholder="Preferred name"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.nickname}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="member_birthday">Birthdate</Label>

                            <Input
                                id="member_birthday"
                                type="date"
                                className={cn(
                                    'mt-1 block w-full',
                                    hasWmasterValue(memberRecord?.birthday) &&
                                        WMASTER_VALUE_CLASS,
                                )}
                                defaultValue={memberRecord?.birthday ?? ''}
                                disabled
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="birthplace_province">
                                Birthplace province
                            </Label>
                            <LocationCombobox
                                id="birthplace_province"
                                name="birthplace_province"
                                search={birthplaceProvinceSearch}
                                placeholder="Select province"
                                required
                                inputClassName={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('birthplace_province') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                loadingMessage="Searching province suggestions..."
                                errorMessage="Province suggestions are temporarily unavailable."
                                promptMessage="Type at least 2 characters to search provinces."
                                onSelect={() => {
                                    birthplaceCitySearch.setSelectedValue('');
                                    birthplaceBarangaySearch.setSelectedValue(
                                        '',
                                    );
                                }}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.birthplace_province}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="birthplace_city">
                                Birthplace city/municipality
                            </Label>
                            <LocationCombobox
                                id="birthplace_city"
                                name="birthplace_city"
                                search={birthplaceCitySearch}
                                placeholder="Select city or municipality"
                                required
                                disabled={
                                    !birthplaceProvinceSearch.selectedValue
                                }
                                inputClassName={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('birthplace_city') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                loadingMessage="Searching city suggestions..."
                                errorMessage="City suggestions are temporarily unavailable."
                                promptMessage="Select a province first."
                                onSelect={(suggestion) => {
                                    if (suggestion.province) {
                                        birthplaceProvinceSearch.setSelectedValue(
                                            suggestion.province,
                                        );
                                    }

                                    birthplaceBarangaySearch.setSelectedValue(
                                        '',
                                    );
                                }}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.birthplace_city}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="birthplace_barangay">
                                Birthplace barangay{' '}
                                <span className="text-xs text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <LocationCombobox
                                id="birthplace_barangay"
                                name="birthplace_barangay"
                                search={birthplaceBarangaySearch}
                                placeholder="Select barangay"
                                disabled={!birthplaceCitySearch.selectedValue}
                                inputClassName={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('birthplace_barangay') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                loadingMessage="Loading barangays..."
                                errorMessage="Barangay suggestions are temporarily unavailable."
                                promptMessage="Select a city or municipality first."
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.birthplace_barangay}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="member_age">Age</Label>

                            <Input
                                id="member_age"
                                type="number"
                                className={cn(
                                    'mt-1 block w-full',
                                    hasWmasterValue(memberAge) &&
                                        WMASTER_VALUE_CLASS,
                                )}
                                defaultValue={memberAge ?? ''}
                                placeholder="Not available"
                                disabled
                            />
                        </div>

                        <div className="grid gap-4 md:col-span-3 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="home_address3">Province</Label>
                                <OriginalValueHint
                                    value={homeAddress3RawHint}
                                />

                                <LocationCombobox
                                    id="home_address3"
                                    name="home_address3"
                                    search={homeProvinceSearch}
                                    placeholder="Select province"
                                    required
                                    inputClassName={cn(
                                        'mt-1 block w-full',
                                        isFieldMissing('home_address3') &&
                                            MISSING_FIELD_CLASS,
                                    )}
                                    loadingMessage="Searching province suggestions..."
                                    errorMessage="Province suggestions are temporarily unavailable."
                                    promptMessage="Type at least 2 characters to search provinces."
                                    onSelect={() => {
                                        homeCitySearch.setSelectedValue('');
                                        homeBarangaySearch.setSelectedValue('');
                                        setHomeAddressZipValue('');
                                    }}
                                />

                                <InputError
                                    className="mt-2"
                                    message={formErrors.home_address3}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="home_address2">
                                    City/Municipality
                                </Label>
                                <OriginalValueHint
                                    value={homeAddress2RawHint}
                                />

                                <LocationCombobox
                                    id="home_address2"
                                    name="home_address2"
                                    search={homeCitySearch}
                                    placeholder="Select city or municipality"
                                    required
                                    disabled={!homeProvinceSearch.selectedValue}
                                    inputClassName={cn(
                                        'mt-1 block w-full',
                                        isFieldMissing('home_address2') &&
                                            MISSING_FIELD_CLASS,
                                    )}
                                    loadingMessage="Searching city suggestions..."
                                    errorMessage="City suggestions are temporarily unavailable."
                                    promptMessage="Select a province first."
                                    onSelect={(suggestion) => {
                                        if (suggestion.province) {
                                            homeProvinceSearch.setSelectedValue(
                                                suggestion.province,
                                            );
                                        }

                                        homeBarangaySearch.setSelectedValue('');
                                        void handleHomeCitySelect(
                                            suggestion.code,
                                        );
                                    }}
                                />

                                <InputError
                                    className="mt-2"
                                    message={formErrors.home_address2}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="home_address_zip">
                                    ZIP code
                                </Label>

                                <Input
                                    id="home_address_zip"
                                    className="mt-1 block w-full"
                                    value={homeAddressZipValue}
                                    onChange={(event) =>
                                        setHomeAddressZipValue(
                                            event.target.value,
                                        )
                                    }
                                    name="home_address_zip"
                                    inputMode="numeric"
                                    autoComplete="postal-code"
                                    placeholder="Auto-filled from city"
                                />

                                <InputError
                                    className="mt-2"
                                    message={formErrors.home_address_zip}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="home_address_barangay">
                                    Barangay
                                </Label>
                                <OriginalValueHint
                                    value={homeAddressBarangayRawHint}
                                />

                                <LocationCombobox
                                    id="home_address_barangay"
                                    name="home_address_barangay"
                                    search={homeBarangaySearch}
                                    placeholder="Select barangay"
                                    disabled={!homeCitySearch.selectedValue}
                                    inputClassName={cn(
                                        'mt-1 block w-full',
                                        isFieldMissing(
                                            'home_address_barangay',
                                        ) && MISSING_FIELD_CLASS,
                                    )}
                                    loadingMessage="Loading barangays..."
                                    errorMessage="Barangay suggestions are temporarily unavailable."
                                    promptMessage="Select a city or municipality first."
                                />

                                <InputError
                                    className="mt-2"
                                    message={formErrors.home_address_barangay}
                                />
                            </div>

                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="home_address1">
                                    Address (street)
                                </Label>

                                <Input
                                    id="home_address1"
                                    className={cn(
                                        'mt-1 block w-full',
                                        isFieldMissing('home_address1') &&
                                            MISSING_FIELD_CLASS,
                                    )}
                                    defaultValue={homeAddress1}
                                    name="home_address1"
                                    required
                                    placeholder="Home address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={formErrors.home_address1}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="length_of_stay">
                                Length of stay
                            </Label>

                            <Input
                                id="length_of_stay"
                                className={cn(
                                    'mt-1 block w-full',
                                    isFieldMissing('length_of_stay') &&
                                        MISSING_FIELD_CLASS,
                                )}
                                defaultValue={
                                    memberApplicationProfile?.length_of_stay ??
                                    ''
                                }
                                name="length_of_stay"
                                required
                                placeholder="e.g. 2 years"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.length_of_stay}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label
                                htmlFor={
                                    isHousingStatusLocked
                                        ? 'member_housing_status'
                                        : 'housing_status'
                                }
                            >
                                Housing status
                            </Label>

                            {isHousingStatusLocked ? (
                                <Input
                                    id="member_housing_status"
                                    className={cn(
                                        'mt-1 block w-full',
                                        WMASTER_VALUE_CLASS,
                                    )}
                                    defaultValue={
                                        memberRecord?.housing_status ?? ''
                                    }
                                    placeholder="Not available"
                                    disabled
                                />
                            ) : (
                                <>
                                    <Select
                                        value={housingStatusValue || undefined}
                                        onValueChange={setHousingStatusValue}
                                    >
                                        <SelectTrigger
                                            id="housing_status"
                                            className={cn(
                                                'mt-1 w-full',
                                                isFieldMissing(
                                                    'housing_status',
                                                ) && MISSING_FIELD_CLASS,
                                            )}
                                        >
                                            <SelectValue placeholder="Select housing status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {HOUSING_STATUS_OPTIONS.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <input
                                        type="hidden"
                                        name="housing_status"
                                        value={housingStatusValue}
                                    />
                                    <InputError
                                        className="mt-2"
                                        message={formErrors.housing_status}
                                    />
                                </>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label
                                htmlFor={
                                    isCivilStatusLocked
                                        ? 'member_civil_status'
                                        : 'civil_status'
                                }
                            >
                                Civil status
                            </Label>

                            {isCivilStatusLocked ? (
                                <Select
                                    value={memberCivilStatus || undefined}
                                    disabled
                                >
                                    <SelectTrigger
                                        id="member_civil_status"
                                        className={cn(
                                            'mt-1 w-full',
                                            WMASTER_VALUE_CLASS,
                                        )}
                                    >
                                        <SelectValue placeholder="Not available" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {CIVIL_STATUS_OPTIONS.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={option}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <>
                                    <Select
                                        value={civilStatusValue || undefined}
                                        onValueChange={setCivilStatusValue}
                                    >
                                        <SelectTrigger
                                            id="civil_status"
                                            className={cn(
                                                'mt-1 w-full',
                                                isFieldMissing(
                                                    'civil_status',
                                                ) && MISSING_FIELD_CLASS,
                                            )}
                                        >
                                            <SelectValue placeholder="Select civil status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {CIVIL_STATUS_OPTIONS.map(
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
                                        name="civil_status"
                                        value={civilStatusValue}
                                    />
                                    <InputError
                                        className="mt-2"
                                        message={formErrors.civil_status}
                                    />
                                </>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="educational_attainment">
                                Educational attainment
                            </Label>

                            <Select
                                value={educationalAttainment || undefined}
                                onValueChange={(value) => {
                                    setEducationalAttainment(value);
                                }}
                            >
                                <SelectTrigger
                                    id="educational_attainment"
                                    className={cn(
                                        'mt-1 w-full',
                                        isFieldMissing(
                                            'educational_attainment',
                                        ) && MISSING_FIELD_CLASS,
                                    )}
                                >
                                    <SelectValue placeholder="Select educational attainment" />
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

                            <input
                                type="hidden"
                                name="educational_attainment"
                                value={educationalAttainment}
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.educational_attainment}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="number_of_children">
                                No. of children
                            </Label>

                            <Input
                                id="number_of_children"
                                type="number"
                                className={cn(
                                    'mt-1 block w-full',
                                    hasWmasterValue(
                                        memberRecord?.number_of_children,
                                    ) && WMASTER_VALUE_CLASS,
                                )}
                                defaultValue={numberOfChildrenValue}
                                name="number_of_children"
                                min={0}
                                inputMode="numeric"
                            />

                            <InputError
                                className="mt-2"
                                message={formErrors.number_of_children}
                            />
                        </div>

                        {!isSingle && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="member_record_spouse_name">
                                        Spouse name
                                    </Label>
                                    {isSpouseNameLocked ? (
                                        <Input
                                            id="member_record_spouse_name"
                                            className={cn(
                                                'mt-1 block w-full',
                                                WMASTER_VALUE_CLASS,
                                            )}
                                            defaultValue={
                                                memberRecord?.spouse_name ?? ''
                                            }
                                            placeholder="Not available"
                                            disabled
                                        />
                                    ) : (
                                        <>
                                            <Input
                                                id="member_record_spouse_name"
                                                className={cn(
                                                    'mt-1 block w-full',
                                                    isFieldMissing(
                                                        'spouse_name',
                                                    ) && MISSING_FIELD_CLASS,
                                                )}
                                                defaultValue={
                                                    memberApplicationProfile?.spouse_name ??
                                                    ''
                                                }
                                                name="spouse_name"
                                                placeholder="Spouse name"
                                            />
                                            <InputError
                                                className="mt-2"
                                                message={formErrors.spouse_name}
                                            />
                                        </>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="spouse_birthdate">
                                        Spouse birthdate
                                    </Label>

                                    <BirthdateInput
                                        id="spouse_birthdate"
                                        name="spouse_birthdate"
                                        className={cn(
                                            isFieldMissing(
                                                'spouse_birthdate',
                                            ) && MISSING_FIELD_CLASS,
                                        )}
                                        value={spouseBirthdateValue}
                                        onValueChange={setSpouseBirthdateValue}
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={formErrors.spouse_birthdate}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="spouse_age_display">
                                        Spouse age
                                    </Label>

                                    <Input
                                        id="spouse_age_display"
                                        type="number"
                                        className="mt-1 block w-full"
                                        value={spouseAge ?? ''}
                                        placeholder="Computed from birthdate"
                                        disabled
                                        readOnly
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="spouse_cell_no">
                                        Spouse cell no.
                                    </Label>

                                    <Input
                                        id="spouse_cell_no"
                                        type="tel"
                                        className="mt-1 block w-full"
                                        defaultValue={
                                            memberApplicationProfile?.spouse_cell_no ??
                                            ''
                                        }
                                        name="spouse_cell_no"
                                        inputMode="numeric"
                                        maxLength={11}
                                        placeholder="09XXXXXXXXX"
                                        onChange={handleMobileNumberInput}
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={formErrors.spouse_cell_no}
                                    />
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </SurfaceCard>
        </TabsContent>
    );
}
