<?php

namespace App\Services\LoanRequests;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataChange;
use App\Models\LoanRequestDataEntry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LoanRequestDataService
{
    private const OWNER_MEMBER = 'member';

    private const OWNER_STAFF = 'staff';

    /**
     * @var array<string, array{label:string, owner:string, sensitive:bool, required_on_submit:bool, section:string, type:string}>
     */
    private const FIELD_DEFINITIONS = [
        'beneficiary_primary_name' => [
            'label' => 'Primary beneficiary name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'insurance',
            'type' => 'string',
        ],
        'beneficiary_primary_relationship' => [
            'label' => 'Primary beneficiary relationship',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'insurance',
            'type' => 'string',
        ],
        'beneficiary_primary_birthdate' => [
            'label' => 'Primary beneficiary birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'insurance',
            'type' => 'date',
        ],
        'beneficiary_secondary_name' => [
            'label' => 'Secondary beneficiary name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'insurance',
            'type' => 'string',
        ],
        'beneficiary_secondary_relationship' => [
            'label' => 'Secondary beneficiary relationship',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'insurance',
            'type' => 'string',
        ],
        'beneficiary_secondary_birthdate' => [
            'label' => 'Secondary beneficiary birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'insurance',
            'type' => 'date',
        ],
        'health_smoker' => [
            'label' => 'Tobacco-use declaration',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'health',
            'type' => 'boolean',
        ],
        'health_hypertension' => [
            'label' => 'Hypertension declaration',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'health',
            'type' => 'boolean',
        ],
        'health_diabetes' => [
            'label' => 'Diabetes declaration',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'health',
            'type' => 'boolean',
        ],
        'health_recent_hospitalization' => [
            'label' => 'Recent hospitalization declaration',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'health',
            'type' => 'boolean',
        ],
        'health_declaration_notes' => [
            'label' => 'Health declaration notes',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health',
            'type' => 'string',
        ],
        'authorized_recipient_name' => [
            'label' => 'Authorized recipient name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'authorization',
            'type' => 'string',
        ],
        'authorized_recipient_relationship' => [
            'label' => 'Authorized recipient relationship',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'authorization',
            'type' => 'string',
        ],
        'release_method' => [
            'label' => 'Release method',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'authorization',
            'type' => 'string',
        ],
        'payout_bank_name' => [
            'label' => 'Payout bank name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'banking',
            'type' => 'string',
        ],
        'payout_account_name' => [
            'label' => 'Payout account name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'banking',
            'type' => 'string',
        ],
        'payout_account_number' => [
            'label' => 'Payout account number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'banking',
            'type' => 'string',
        ],
        'payout_account_type' => [
            'label' => 'Payout account type',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'banking',
            'type' => 'string',
        ],
        'payout_atm_number' => [
            'label' => 'Payout ATM number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'banking',
            'type' => 'string',
        ],
        'payout_bank_branch' => [
            'label' => 'Payout bank branch',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'banking',
            'type' => 'string',
        ],
        'payout_atm_holder_name' => [
            'label' => 'ATM card holder name (if not the borrower)',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'banking',
            'type' => 'string',
        ],
        'barangay_name' => [
            'label' => 'Barangay name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => false,
            'required_on_submit' => true,
            'section' => 'barangay',
            'type' => 'string',
        ],
        'barangay_clearance_reference' => [
            'label' => 'Barangay clearance reference',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => false,
            'required_on_submit' => true,
            'section' => 'barangay',
            'type' => 'string',
        ],
        'barangay_locality' => [
            'label' => 'Barangay locality',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => false,
            'required_on_submit' => true,
            'section' => 'barangay',
            'type' => 'string',
        ],
        'barangay_official_designation' => [
            'label' => 'Barangay official designation',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'barangay',
            'type' => 'string',
        ],
        'barangay_agency_name' => [
            'label' => 'Agency name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'barangay',
            'type' => 'string',
        ],
        'barangay_agency_address' => [
            'label' => 'Agency address',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'barangay',
            'type' => 'string',
        ],
        'declaration_existing_loans' => [
            'label' => 'Existing-loans declaration',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'declarations',
            'type' => 'boolean',
        ],
        'declaration_pending_cases' => [
            'label' => 'Pending-cases declaration',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'declarations',
            'type' => 'boolean',
        ],
        'declaration_truth_confirmation' => [
            'label' => 'Truthfulness declaration',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'declarations',
            'type' => 'boolean',
        ],
        'declaration_data_privacy_consent' => [
            'label' => 'Data-privacy consent',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'declarations',
            'type' => 'boolean',
        ],
        'service_charge_rate' => [
            'label' => 'Service charge rate',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'insurance_rate' => [
            'label' => 'Insurance rate',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'insurance_required' => [
            'label' => 'Insurance required',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'boolean',
        ],
        'insurance_term' => [
            'label' => 'Insurance term',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'integer',
        ],
        'loan_security_rate' => [
            'label' => 'Loan security rate',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'documentary_stamp_rate' => [
            'label' => 'Documentary stamp rate',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'notarial_fee' => [
            'label' => 'Notarial fee',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'guaranteed_net_take_home_pay' => [
            'label' => 'Guaranteed net take-home pay',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'penalty_rate_per_month' => [
            'label' => 'Penalty rate per month',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'authorization_required' => [
            'label' => 'Authorization document required',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'boolean',
        ],
        'barangay_required' => [
            'label' => 'Barangay undertaking required',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'boolean',
        ],
        'security_required' => [
            'label' => 'Security agreement required',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'boolean',
        ],
        'loan_security_details' => [
            'label' => 'Security or collateral details',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'notarial_venue' => [
            'label' => 'Notarial venue',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'witness_one_name' => [
            'label' => 'Witness one name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'witness_two_name' => [
            'label' => 'Witness two name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'barangay_official_name' => [
            'label' => 'Barangay official name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'barangay_official_title' => [
            'label' => 'Barangay official title',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const SECTION_LABELS = [
        'insurance' => 'Insurance and beneficiaries',
        'health' => 'Health declarations',
        'authorization' => 'Authorization and release',
        'banking' => 'Bank and payout information',
        'barangay' => 'Barangay information',
        'declarations' => 'Personal declarations and consent',
        'processing' => 'Processing details',
    ];

    /**
     * @return array<string, array{label:string, fields:array<string, array{label:string, sensitive:bool, owner:string, type:string}>}>
     */
    public function sectionDefinitions(): array
    {
        $sections = [];

        foreach (self::SECTION_LABELS as $sectionKey => $label) {
            $sections[$sectionKey] = [
                'label' => $label,
                'fields' => [],
            ];
        }

        foreach (self::FIELD_DEFINITIONS as $fieldKey => $definition) {
            $sections[$definition['section']]['fields'][$fieldKey] = [
                'label' => $definition['label'],
                'sensitive' => $definition['sensitive'],
                'owner' => $definition['owner'],
                'type' => $definition['type'],
            ];
        }

        return $sections;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function emptySections(): array
    {
        $sections = [];

        foreach ($this->sectionDefinitions() as $sectionKey => $definition) {
            $sectionValues = [];

            foreach (array_keys($definition['fields']) as $fieldKey) {
                $sectionValues[$fieldKey] = null;
            }

            $sections[$sectionKey] = $sectionValues;
        }

        return $sections;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function serializeSections(LoanRequest $loanRequest): array
    {
        $flatValues = $this->loadFlatValues($loanRequest);
        $sections = $this->emptySections();

        foreach ($this->sectionDefinitions() as $sectionKey => $definition) {
            foreach (array_keys($definition['fields']) as $fieldKey) {
                if (array_key_exists($fieldKey, $flatValues)) {
                    $sections[$sectionKey][$fieldKey] = $flatValues[$fieldKey];
                }
            }
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    public function loadFlatValues(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('dataEntries');

        $values = [];

        foreach ($loanRequest->dataEntries as $entry) {
            $values[$entry->field_key] = $this->entryValue($entry);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncMemberSections(
        LoanRequest $loanRequest,
        array $payload,
    ): void {
        foreach ($this->sectionDefinitions() as $sectionKey => $definition) {
            if ($this->sectionOwner($sectionKey) !== self::OWNER_MEMBER) {
                continue;
            }

            $sectionPayload = $payload[$sectionKey] ?? null;

            if (! is_array($sectionPayload)) {
                continue;
            }

            foreach (array_keys($definition['fields']) as $fieldKey) {
                if (! array_key_exists($fieldKey, $sectionPayload)) {
                    continue;
                }

                $this->persistField(
                    $loanRequest,
                    $fieldKey,
                    $sectionPayload[$fieldKey],
                    confirmedByMember: true,
                    confirmedAt: now(),
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    public function missingRequiredMemberFields(LoanRequest $loanRequest): array
    {
        $flatValues = $this->loadFlatValues($loanRequest);
        $missing = [];

        foreach (self::FIELD_DEFINITIONS as $fieldKey => $definition) {
            if (
                $definition['owner'] !== self::OWNER_MEMBER
                || ! $definition['required_on_submit']
            ) {
                continue;
            }

            $value = $flatValues[$fieldKey] ?? null;

            if ($definition['type'] === 'boolean') {
                if (! is_bool($value)) {
                    $missing[] = $fieldKey;
                }

                continue;
            }

            if ($value === null || trim((string) $value) === '') {
                $missing[] = $fieldKey;
            }
        }

        if (
            ($flatValues['beneficiary_secondary_name'] ?? null) !== null
            && trim((string) ($flatValues['beneficiary_secondary_name'] ?? '')) !== ''
        ) {
            foreach ([
                'beneficiary_secondary_relationship',
                'beneficiary_secondary_birthdate',
            ] as $fieldKey) {
                $value = $flatValues[$fieldKey] ?? null;

                if ($value === null || trim((string) $value) === '') {
                    $missing[] = $fieldKey;
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @return list<string>
     */
    public function unconfirmedSensitiveFields(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('dataEntries');

        return $loanRequest->dataEntries
            ->filter(
                static fn (LoanRequestDataEntry $entry): bool => $entry->is_sensitive
                    && ! $entry->confirmed_by_member,
            )
            ->pluck('field_key')
            ->filter(static fn (mixed $fieldKey): bool => is_string($fieldKey))
            ->values()
            ->all();
    }

    public function hasUnconfirmedSensitiveFields(LoanRequest $loanRequest): bool
    {
        return $this->unconfirmedSensitiveFields($loanRequest) !== [];
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return list<string>
     */
    public function applyStaffUpdates(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $updates,
        string $reason,
        string $informationSource,
    ): array {
        $normalizedReason = trim($reason);
        $normalizedSource = trim($informationSource);

        if ($normalizedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for processing updates.',
            ]);
        }

        if ($normalizedSource === '') {
            throw ValidationException::withMessages([
                'information_source' => 'An information source is required for processing updates.',
            ]);
        }

        $changedFields = [];

        foreach ($updates as $fieldKey => $value) {
            if (! is_string($fieldKey) || ! isset(self::FIELD_DEFINITIONS[$fieldKey])) {
                continue;
            }

            $definition = self::FIELD_DEFINITIONS[$fieldKey];
            $entry = $this->resolveFieldEntry($loanRequest, $fieldKey);
            $before = $entry !== null ? $this->entryValue($entry) : null;
            $after = $this->normalizeFieldValue($fieldKey, $value);

            if ($before === $after) {
                continue;
            }

            $confirmedByMember = $definition['sensitive'] ? false : ($entry?->confirmed_by_member ?? false);
            $confirmedAt = $definition['sensitive']
                ? null
                : $entry?->confirmed_by_member_at;

            $entry = $this->persistField(
                $loanRequest,
                $fieldKey,
                $after,
                ownerType: $definition['owner'],
                confirmedByMember: $confirmedByMember,
                confirmedAt: $confirmedAt,
            );

            LoanRequestDataChange::query()->create([
                'loan_request_id' => $loanRequest->id,
                'actor_user_id' => $actor->user_id,
                'field_key' => $fieldKey,
                'before_value_json' => ['value' => $before],
                'after_value_json' => ['value' => $after],
                'reason' => $normalizedReason,
                'information_source' => $normalizedSource,
                'metadata_json' => [
                    'section' => $definition['section'],
                    'owner_type' => $entry->owner_type,
                    'confirmed_by_member' => $entry->confirmed_by_member,
                ],
            ]);

            $changedFields[] = $fieldKey;
        }

        return array_values(array_unique($changedFields));
    }

    /**
     * @param  list<string>  $fieldKeys
     * @return array<int, array{field:string, label:string}>
     */
    public function fieldDescriptors(array $fieldKeys): array
    {
        $descriptors = [];

        foreach ($fieldKeys as $fieldKey) {
            if (! isset(self::FIELD_DEFINITIONS[$fieldKey])) {
                continue;
            }

            $descriptors[] = [
                'field' => $fieldKey,
                'label' => self::FIELD_DEFINITIONS[$fieldKey]['label'],
            ];
        }

        return $descriptors;
    }

    public function fieldLabel(string $fieldKey): string
    {
        return self::FIELD_DEFINITIONS[$fieldKey]['label'] ?? $fieldKey;
    }

    public function isSensitiveField(string $fieldKey): bool
    {
        return (bool) (self::FIELD_DEFINITIONS[$fieldKey]['sensitive'] ?? false);
    }

    /**
     * @return list<string>
     */
    public function processingFieldKeys(): array
    {
        return array_keys(array_filter(
            self::FIELD_DEFINITIONS,
            static fn (array $definition): bool => $definition['section'] === 'processing',
        ));
    }

    private function sectionOwner(string $sectionKey): string
    {
        foreach (self::FIELD_DEFINITIONS as $definition) {
            if ($definition['section'] === $sectionKey) {
                return $definition['owner'];
            }
        }

        return self::OWNER_MEMBER;
    }

    private function resolveFieldEntry(
        LoanRequest $loanRequest,
        string $fieldKey,
    ): ?LoanRequestDataEntry {
        $loanRequest->loadMissing('dataEntries');

        return $loanRequest->dataEntries
            ->first(
                static fn (LoanRequestDataEntry $entry): bool => $entry->field_key === $fieldKey,
            );
    }

    private function entryValue(LoanRequestDataEntry $entry): mixed
    {
        $value = $entry->value_json;

        if (! is_array($value)) {
            return null;
        }

        return $value['value'] ?? null;
    }

    private function persistField(
        LoanRequest $loanRequest,
        string $fieldKey,
        mixed $value,
        ?string $ownerType = null,
        bool $confirmedByMember = false,
        Carbon|string|null $confirmedAt = null,
    ): LoanRequestDataEntry {
        $definition = self::FIELD_DEFINITIONS[$fieldKey] ?? null;

        if ($definition === null) {
            throw ValidationException::withMessages([
                'field' => 'The selected processing field is not supported.',
            ]);
        }

        /** @var LoanRequestDataEntry $entry */
        $entry = LoanRequestDataEntry::query()->firstOrNew([
            'loan_request_id' => $loanRequest->id,
            'field_key' => $fieldKey,
        ]);

        $entry->section_key = $definition['section'];
        $entry->owner_type = $ownerType ?? $definition['owner'];
        $entry->is_sensitive = $definition['sensitive'];
        $entry->confirmed_by_member = $confirmedByMember;
        $entry->confirmed_by_member_at = $confirmedByMember
            ? ($confirmedAt instanceof Carbon ? $confirmedAt : ($confirmedAt !== null ? Carbon::parse($confirmedAt) : now()))
            : null;
        $entry->value_json = ['value' => $this->normalizeFieldValue($fieldKey, $value)];
        $entry->metadata_json = [
            'label' => $definition['label'],
            'type' => $definition['type'],
        ];
        $entry->save();

        return $entry;
    }

    private function normalizeFieldValue(string $fieldKey, mixed $value): mixed
    {
        $definition = self::FIELD_DEFINITIONS[$fieldKey] ?? null;

        if ($definition === null) {
            return $value;
        }

        if ($definition['type'] === 'boolean') {
            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));

                if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                    return true;
                }

                if (in_array($normalized, ['0', 'false', 'no'], true)) {
                    return false;
                }
            }

            if (is_numeric($value)) {
                return (int) $value === 1;
            }

            return null;
        }

        if ($definition['type'] === 'integer') {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return (int) $value;
        }

        if ($definition['type'] === 'number') {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return trim((string) $value);
        }

        if ($definition['type'] === 'date') {
            if ($value === null) {
                return null;
            }

            $normalized = trim((string) $value);

            return $normalized !== '' ? $normalized : null;
        }

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
