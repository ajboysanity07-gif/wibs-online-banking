<?php

namespace App\Services\LoanRequests;

use App\LoanCivilStatus;
use App\LoanPaydayOption;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\LoanSex;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\Wmaster;
use App\Notifications\LoanRequestAdminCorrectedCreatedNotification;
use App\Notifications\LoanRequestSubmittedNotification;
use App\Services\Locations\PsgcService;
use App\Services\Notifications\NotificationRecipientService;
use App\Support\LocationComposer;
use App\Support\SchemaCapabilities;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LoanRequestService
{
    /**
     * Statuses whose loan documents are final, so the applicant income
     * snapshot must not be overwritten by profile changes.
     *
     * @var list<string>
     */
    public const INCOME_SYNC_EXCLUDED_STATUSES = [
        LoanRequestStatus::Rejected->value,
        LoanRequestStatus::Declined->value,
        LoanRequestStatus::MemberDeclinedTerms->value,
        LoanRequestStatus::Cancelled->value,
        LoanRequestStatus::Released->value,
    ];

    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
        private NotificationRecipientService $notificationRecipients,
        private LoanRequestPayloadSerializer $serializer,
        private LoanRequestDataService $dataService,
        private DependentsProfileSyncService $dependentsSync,
        private SavedCoMakersService $savedCoMakers,
        private LoanDeclarationAutoFillService $declarationAutoFillService,
        private LoanRequestLookupService $lookupService,
        private PsgcService $psgcService,
    ) {}

    /**
     * @return array{
     *     loanTypes: list<array{typecode: string, label: string}>,
     *     applicant: array<string, mixed>,
     *     coMakerOne: array<string, mixed>|null,
     *     coMakerTwo: array<string, mixed>|null,
     *     applicantReadOnly: array<string, bool>,
     *     savedCoMakers: list<array{id: int, label: string, last_used_at: string|null}>,
     *     member: array{name: string, acctno: string|null},
     *     dataSections: array<string, array<string, mixed>>,
     *     dataSectionDefinitions: array<string, mixed>,
     *     insurancePrefilledFromProfile: bool,
     *     initialStep: int,
     *     autoFilledDeclarations: array<string, bool|string|float|null>,
     *     draft: array{
     *         id: int,
     *         status: string,
     *         typecode: string|null,
     *         loan_type_label_snapshot: string|null,
     *         requested_amount: string|float|int|null,
     *         requested_term: int|string|null,
     *         loan_purpose: string|null,
     *         availment_status: string|null,
     *         submitted_at: string|null,
     *         updated_at: string|null
     *     }|null
     * }
     */
    public function getFormData(AppUser $user): array
    {
        $user->loadMissing('memberApplicationProfile');

        if ($this->schemaCapabilities->hasTable('wmaster')) {
            $user->loadMissing('wmaster');
        }

        $draft = $this->getActiveEditableRequest($user);
        $applicantReadOnly = $this->buildApplicantReadOnlyMap($user);
        $memberName = $this->resolveMemberName($user);

        $autoFilledDeclarations = $this->declarationAutoFillService->getDeclarationData($user);

        if ($draft !== null) {
            $draft->loadMissing('people');
        }

        $applicant = $draft !== null
            ? $this->serializePerson($draft, LoanRequestPersonRole::Applicant)
            : [];

        if ($applicant === []) {
            $applicant = $this->buildApplicantSnapshot($user);
        }

        $coMakerOne = $draft !== null
            ? $this->serializePerson($draft, LoanRequestPersonRole::CoMakerOne)
            : null;

        $coMakerTwo = $draft !== null
            ? $this->serializePerson($draft, LoanRequestPersonRole::CoMakerTwo)
            : null;

        $applicant = $this->normalizePersonSelectValues($applicant);
        $applicant = $this->applyApplicantProfileDefaults($applicant, $user);
        $coMakerOne = $coMakerOne !== null
            ? $this->normalizePersonSelectValues($coMakerOne)
            : null;
        $coMakerTwo = $coMakerTwo !== null
            ? $this->normalizePersonSelectValues($coMakerTwo)
            : null;

        $initialStep = 0;

        if ($draft !== null) {
            $flatValues = $this->dataService->loadFlatValues($draft);
            $stepValue = $flatValues['wizard_current_step'] ?? null;

            if (is_numeric($stepValue)) {
                $initialStep = max(0, min(23, (int) $stepValue));
            }
        }

        $dataSections = $draft !== null
            ? $this->dataService->serializeSections($draft)
            : $this->dataService->emptySections();

        [$dataSections['insurance'], $insurancePrefilledFromProfile] = $this->applyInsuranceProfileDefaults(
            $dataSections['insurance'],
            $user->memberApplicationProfile,
        );

        [$dataSections['banking'], $bankingPrefilledFromProfile] = $this->applyBankingProfileDefaults(
            $dataSections['banking'],
            $user->memberApplicationProfile,
        );

        [$dataSections['dependents'], $dependentsPrefilledFromProfile] = $this->applyDependentsProfileDefaults(
            $dataSections['dependents'],
            $user->memberApplicationProfile,
        );

        return [
            'loanTypes' => $this->getLoanTypes()->values()->all(),
            'applicant' => $applicant,
            'coMakerOne' => $coMakerOne,
            'coMakerTwo' => $coMakerTwo,
            'applicantReadOnly' => $applicantReadOnly,
            'savedCoMakers' => $user->memberApplicationProfile !== null
                ? $this->savedCoMakers->listFor($user->memberApplicationProfile)->values()->all()
                : [],
            'member' => [
                'name' => $memberName,
                'acctno' => $user->acctno,
            ],
            'dataSections' => $dataSections,
            'dataSectionDefinitions' => $this->dataService->sectionDefinitions(),
            'initialStep' => $initialStep,
            'autoFilledDeclarations' => $autoFilledDeclarations,
            'draft' => $draft !== null ? $this->serializeLoanRequest($draft) : null,
            'bankingPrefilledFromProfile' => $bankingPrefilledFromProfile,
            'insurancePrefilledFromProfile' => $insurancePrefilledFromProfile,
            'dependentsPrefilledFromProfile' => $dependentsPrefilledFromProfile,
        ];
    }

    /**
     * Fill missing "Insurance and beneficiaries" wizard values from the
     * member's saved application profile, same precedence rules as
     * applyBankingProfileDefaults(): only null fields are overwritten so a
     * member's own in-progress draft edits are never clobbered.
     *
     * @param  array<string, mixed>  $insuranceValues
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function applyInsuranceProfileDefaults(
        array $insuranceValues,
        ?MemberApplicationProfile $profile,
    ): array {
        if ($profile === null) {
            return [$insuranceValues, false];
        }

        $dateFields = [
            'beneficiary_primary_birthdate',
            'beneficiary_secondary_birthdate',
        ];
        $prefilled = false;

        foreach (MemberApplicationProfile::beneficiaryFields() as $field) {
            if (($insuranceValues[$field] ?? null) !== null) {
                continue;
            }

            $rawValue = $profile->getAttribute($field);

            $profileValue = in_array($field, $dateFields, true)
                ? $rawValue?->toDateString()
                : $this->normalizeOptionalString($rawValue);

            if ($profileValue === null) {
                continue;
            }

            $insuranceValues[$field] = $profileValue;
            $prefilled = true;
        }

        return [$insuranceValues, $prefilled];
    }

    /**
     * Fill missing "Bank & payout" wizard values from the member's saved
     * application profile (same precedence as the "About you" applicant
     * snapshot). Only null fields are overwritten so a member's own
     * in-progress draft edits are never clobbered.
     *
     * @param  array<string, mixed>  $bankingValues
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function applyBankingProfileDefaults(
        array $bankingValues,
        ?MemberApplicationProfile $profile,
    ): array {
        if ($profile === null) {
            return [$bankingValues, false];
        }

        $prefilled = false;

        foreach (MemberApplicationProfile::payoutBankFields() as $field) {
            if (($bankingValues[$field] ?? null) !== null) {
                continue;
            }

            $profileValue = $profile->getAttribute($field);

            if ($profileValue === null) {
                continue;
            }

            if (is_int($profileValue) || is_numeric($profileValue)) {
                $bankingValues[$field] = (int) $profileValue;
                $prefilled = true;

                continue;
            }

            $profileValue = $this->normalizeOptionalString($profileValue);

            if ($profileValue === null) {
                continue;
            }

            $bankingValues[$field] = $profileValue;
            $prefilled = true;
        }

        return [$bankingValues, $prefilled];
    }

    /**
     * Fill missing "Dependents" wizard values from the member's saved
     * dependent profile (member_dependent_profiles/member_dependents),
     * flattening each row back into the wizard's fixed-slot field keys.
     * Same precedence as applyBankingProfileDefaults(): only null fields
     * are overwritten so a member's own in-progress draft edits are never
     * clobbered.
     *
     * @param  array<string, mixed>  $dependentsValues
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function applyDependentsProfileDefaults(
        array $dependentsValues,
        ?MemberApplicationProfile $profile,
    ): array {
        if ($profile === null) {
            return [$dependentsValues, false];
        }

        $profile->loadMissing('dependentProfile.dependents');
        $dependentProfile = $profile->dependentProfile;
        $dependents = $dependentProfile?->dependents;
        $hasDependentRows = $dependents !== null && $dependents->isNotEmpty();
        $hasSpouseCycleData = $dependentProfile?->spouse_cycle_status !== null;

        if ($dependentProfile === null || (! $hasDependentRows && ! $hasSpouseCycleData)) {
            return [$dependentsValues, false];
        }

        $prefilled = false;

        $applyValue = function (string $field, mixed $rawValue) use (&$dependentsValues, &$prefilled): void {
            if (! array_key_exists($field, $dependentsValues) || $dependentsValues[$field] !== null) {
                return;
            }

            $value = $this->normalizeOptionalString($rawValue);

            if ($value === null) {
                return;
            }

            $dependentsValues[$field] = $value;
            $prefilled = true;
        };

        foreach ($dependents ?? [] as $dependent) {
            $prefix = "dependent_{$dependent->category}_{$dependent->slot}_";

            foreach ([
                'name' => $dependent->name,
                'birthdate' => $dependent->birthdate?->toDateString(),
                'cycle_status' => $dependent->cycle_status,
                'cycle_number' => $dependent->cycle_number,
            ] as $attribute => $rawValue) {
                $applyValue($prefix.$attribute, $rawValue);
            }
        }

        $applyValue('dependent_spouse_cycle_status', $dependentProfile->spouse_cycle_status);
        $applyValue('dependent_spouse_cycle_number', $dependentProfile->spouse_cycle_number);

        return [$dependentsValues, $prefilled];
    }

    /**
     * Write validated submission data back onto the member's application
     * profile, so future loan requests (and the settings profile page) start
     * pre-filled with it. Submit-only by design: drafts are unvalidated and
     * autosave on abandoned edits, so only a validated final submission
     * should overwrite the canonical profile.
     *
     * @param  array<string, mixed>  $payload
     */
    private function syncMemberApplicationProfileFromSubmission(
        AppUser $user,
        array $payload,
    ): void {
        $applicant = is_array($payload['applicant'] ?? null) ? $payload['applicant'] : [];

        $profileData = [
            ...Arr::only($applicant, MemberApplicationProfile::fields()),
            ...$this->applicantHomeAddressProfileFields($applicant),
            ...Arr::only(
                is_array($payload['banking'] ?? null) ? $payload['banking'] : [],
                MemberApplicationProfile::payoutBankFields(),
            ),
            ...Arr::only(
                is_array($payload['insurance'] ?? null) ? $payload['insurance'] : [],
                MemberApplicationProfile::beneficiaryFields(),
            ),
        ];

        // A blank (or intentionally hidden, e.g. Self-Employed owners) date
        // field must never write an empty string into a date column.
        if (($profileData['employer_date_employed'] ?? null) === '') {
            $profileData['employer_date_employed'] = null;
        }

        // home_address_barangay / employer_business_address_barangay are
        // never actually collected by the wizard (no field renders them --
        // the applicant payload just carries them as an always-blank ''
        // placeholder). Without this guard, every submission overwrites a
        // real barangay value the member set in Profile Settings with null,
        // which then fails member-profile-complete on the very next page
        // load. Never let a blank value here clobber an existing one.
        foreach (['home_address_barangay', 'employer_business_address_barangay'] as $barangayField) {
            if (($profileData[$barangayField] ?? null) === null || $profileData[$barangayField] === '') {
                unset($profileData[$barangayField]);
            }
        }

        if ($profileData === []) {
            return;
        }

        $profile = $user->memberApplicationProfile()->firstOrNew();
        $profile->fill($profileData);
        $profile->save();

        $user->setRelation('memberApplicationProfile', $profile);

        $this->dependentsSync->sync(
            $profile,
            is_array($payload['dependents'] ?? null) ? $payload['dependents'] : [],
        );

        $this->saveCoMakerForReuse($profile, $payload, 'co_maker_1');
        $this->saveCoMakerForReuse($profile, $payload, 'co_maker_2');
    }

    /**
     * The applicant payload keys its address fields as
     * address/address1-3/address_barangay/address_zip (see
     * LoanRequestPayloadSerializer), while MemberApplicationProfile stores
     * the same data under a "home_" prefix. Arr::only(..., fields()) in
     * syncMemberApplicationProfileFromSubmission() above never matches those
     * unprefixed keys, so an address correction made on a loan request
     * submission was silently never reaching the member's Profile Settings.
     * Remap explicitly so it does.
     *
     * @param  array<string, mixed>  $applicant
     * @return array<string, mixed>
     */
    private function applicantHomeAddressProfileFields(array $applicant): array
    {
        $map = [
            'address' => 'home_address',
            'address1' => 'home_address1',
            'address2' => 'home_address2',
            'address3' => 'home_address3',
            'address_barangay' => 'home_address_barangay',
            'address_zip' => 'home_address_zip',
        ];

        $fields = [];

        foreach ($map as $applicantKey => $profileKey) {
            if (array_key_exists($applicantKey, $applicant)) {
                $fields[$profileKey] = $applicant[$applicantKey];
            }
        }

        return $fields;
    }

    /**
     * Persist a co-maker's details onto the member's reusable contact list --
     * only when the borrower explicitly checked "save for reuse" on that
     * slot (opt-in, see SavedCoMakersService). Editing a previously loaded
     * saved contact (saved_co_maker_id present) updates it in place instead
     * of forking a duplicate entry.
     *
     * @param  array<string, mixed>  $payload
     */
    private function saveCoMakerForReuse(
        MemberApplicationProfile $profile,
        array $payload,
        string $key,
    ): void {
        $data = $this->extractPersonPayload($payload, $key);
        $shouldSave = filter_var($data['save_for_reuse'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($data === [] || ! $shouldSave) {
            return;
        }

        $existingId = $data['saved_co_maker_id'] ?? null;

        $this->savedCoMakers->saveOrUpdate(
            $profile,
            $data,
            is_numeric($existingId) ? (int) $existingId : null,
            $this->normalizeOptionalString($data['saved_co_maker_label'] ?? null),
        );
    }

    /**
     * @param  array{
     *     typecode?: string,
     *     requested_amount?: string|float|int|null,
     *     requested_term?: int|string|null,
     *     loan_purpose?: string|null,
     *     availment_status?: string|null,
     *     applicant?: array<string, mixed>,
     *     co_maker_1?: array<string, mixed>,
     *     co_maker_2?: array<string, mixed>
     * }  $payload
     */
    public function saveDraft(AppUser $user, array $payload): LoanRequest
    {
        return DB::transaction(function () use ($user, $payload): LoanRequest {
            $loanRequest = $this->getActiveEditableRequest($user);

            if ($loanRequest === null) {
                $loanRequest = $this->initializeLoanRequest($user);
            }

            $this->fillLoanRequest(
                $loanRequest,
                $payload,
                LoanRequestStatus::Draft,
                false,
            );
            $loanRequest->save();

            $payload = $this->applySystemDeclarations($user, $payload);

            $this->upsertPeopleSnapshots($loanRequest, $payload);
            $this->dataService->syncMemberSections($loanRequest, $payload);

            if (array_key_exists('wizard_step', $payload) && $payload['wizard_step'] !== null) {
                $stepValue = max(0, min(23, (int) $payload['wizard_step']));

                LoanRequestDataEntry::query()->updateOrCreate(
                    [
                        'loan_request_id' => $loanRequest->id,
                        'field_key' => 'wizard_current_step',
                    ],
                    [
                        'section_key' => 'system',
                        'owner_type' => 'system',
                        'is_sensitive' => false,
                        'confirmed_by_member' => false,
                        'confirmed_by_member_at' => null,
                        'value_json' => ['value' => $stepValue],
                        'metadata_json' => ['label' => 'Wizard current step', 'type' => 'integer'],
                    ],
                );
            }

            return $loanRequest->loadMissing('people');
        });
    }

    /**
     * @param  array{
     *     typecode: string,
     *     requested_amount: string|float|int,
     *     requested_term: int|string,
     *     loan_purpose: string,
     *     availment_status: string,
     *     applicant: array<string, mixed>,
     *     co_maker_1: array<string, mixed>,
     *     co_maker_2: array<string, mixed>
     * }  $payload
     */
    public function submit(AppUser $user, array $payload): LoanRequest
    {
        $user->loadMissing('memberApplicationProfile');

        if (! ($user->memberApplicationProfile?->hasLoanPrerequisiteFields() ?? false)) {
            throw ValidationException::withMessages([
                'loan_prerequisites' => 'Please complete your Bank & Payout and Source of Fund / Government ID details before submitting.',
            ]);
        }

        $shouldNotifyAdmins = false;

        $loanRequest = DB::transaction(function () use ($user, $payload, &$shouldNotifyAdmins): LoanRequest {
            $loanRequest = $this->getActiveEditableRequest($user);

            if ($loanRequest === null) {
                $loanRequest = $this->initializeLoanRequest($user);
            }

            $wasSubmitted = in_array($this->statusValue($loanRequest), [
                LoanRequestStatus::Submitted->value,
                LoanRequestStatus::PendingReview->value,
                LoanRequestStatus::UnderReview->value,
            ], true);

            $isRevisionResubmission = $this->statusValue($loanRequest)
                === LoanRequestStatus::NeedsRevision->value;

            $this->fillLoanRequest(
                $loanRequest,
                $payload,
                $isRevisionResubmission && $loanRequest->assigned_officer_id !== null
                    ? LoanRequestStatus::UnderReview
                    : LoanRequestStatus::PendingReview,
                true,
            );
            $loanRequest->workflow_version = LoanRequestWorkflowVersion::DocumentWorkflowV2;
            $loanRequest->save();

            $payload = $this->applySystemDeclarations($user, $payload);

            $this->upsertPeopleSnapshots($loanRequest, $payload);
            $this->dataService->syncMemberSections($loanRequest, $payload);
            $missingMemberFields = $this->dataService->missingRequiredMemberFields(
                $loanRequest,
            );

            if ($missingMemberFields !== []) {
                throw ValidationException::withMessages([
                    'document_data' => sprintf(
                        'Please complete these sections before submitting: %s.',
                        implode(
                            ', ',
                            array_map(
                                fn (string $fieldKey): string => $this->dataService->fieldLabel(
                                    $fieldKey,
                                ),
                                $missingMemberFields,
                            ),
                        ),
                    ),
                ]);
            }

            $this->syncMemberApplicationProfileFromSubmission($user, $payload);

            $loanRequest = $loanRequest->refresh();
            $loanRequest->loadMissing('people');

            $shouldNotifyAdmins = ! $wasSubmitted;

            return $loanRequest;
        });

        if ($shouldNotifyAdmins) {
            $this->notifyAdminsOfSubmission($loanRequest->id);
        }

        return $loanRequest;
    }

    public function createAdminCorrectedCopyFromCancelledRequest(
        LoanRequest $source,
        AppUser $actor,
        string $correctionReason,
    ): LoanRequest {
        $corrected = DB::transaction(function () use ($source, $actor, $correctionReason): LoanRequest {
            $sourceRequest = LoanRequest::query()
                ->whereKey($source->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $sourceRequest->status instanceof LoanRequestStatus
                ? $sourceRequest->status->value
                : (string) $sourceRequest->status;

            if ($status !== LoanRequestStatus::Cancelled->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only cancelled requests can be corrected.',
                ]);
            }

            if ($sourceRequest->user_id === null) {
                throw ValidationException::withMessages([
                    'loan_request' => 'Cancelled request is missing member ownership.',
                ]);
            }

            $existingCorrectedRequest = LoanRequest::query()
                ->where('corrected_from_id', $sourceRequest->id)
                ->lockForUpdate()
                ->first();

            if ($existingCorrectedRequest !== null) {
                throw ValidationException::withMessages([
                    'loan_request' => 'A corrected request already exists for this cancelled request.',
                ]);
            }

            $before = $this->serializer->serializeDetail($sourceRequest);

            $corrected = new LoanRequest;
            $corrected->user_id = $sourceRequest->user_id;
            $corrected->corrected_from_id = $sourceRequest->id;
            $corrected->acctno = (string) ($sourceRequest->acctno ?? '');
            $corrected->typecode = (string) $sourceRequest->typecode;
            $corrected->loan_type_label_snapshot = (string) $sourceRequest->loan_type_label_snapshot;
            $corrected->requested_amount = $sourceRequest->requested_amount;
            $corrected->requested_term = (int) $sourceRequest->requested_term;
            $corrected->loan_purpose = (string) $sourceRequest->loan_purpose;
            $corrected->other_loan_type_name = $sourceRequest->other_loan_type_name;
            $corrected->availment_status = (string) $sourceRequest->availment_status;
            $corrected->status = LoanRequestStatus::UnderReview;
            $corrected->workflow_version = $sourceRequest->workflow_version
                ?? LoanRequestWorkflowVersion::LegacyV1;
            $corrected->submitted_at = $sourceRequest->submitted_at ?? $sourceRequest->created_at;
            $corrected->reviewed_by = null;
            $corrected->reviewed_at = null;
            $corrected->approval_ip_address = null;
            $corrected->approval_user_agent = null;
            $corrected->approved_amount = null;
            $corrected->approved_term = null;
            $corrected->decision_notes = null;
            $corrected->cancelled_by = null;
            $corrected->cancelled_at = null;
            $corrected->cancellation_reason = null;
            $corrected->save();

            $sourceRequest->loadMissing('people');

            foreach ($sourceRequest->people as $person) {
                $role = $person->role instanceof LoanRequestPersonRole
                    ? $person->role->value
                    : (string) $person->role;

                if (! in_array($role, [
                    LoanRequestPersonRole::Applicant->value,
                    LoanRequestPersonRole::CoMakerOne->value,
                    LoanRequestPersonRole::CoMakerTwo->value,
                ], true)) {
                    continue;
                }

                $clone = $person->replicate();
                $clone->loan_request_id = $corrected->id;
                $clone->save();
            }

            $sourceRequest->loadMissing('dataEntries');

            foreach ($sourceRequest->dataEntries as $entry) {
                $clone = $entry->replicate();
                $clone->loan_request_id = $corrected->id;
                $clone->save();
            }

            $corrected->loadMissing('people', 'dataEntries', 'correctedFrom');

            $after = $this->serializer->serializeDetail($corrected);

            $this->recordAdminCorrectedRequestAudit(
                $corrected,
                $actor,
                $correctionReason,
                $before,
                $after,
            );

            return $corrected;
        });

        $this->notifyMemberOfAdminCorrectedCopy(
            $source,
            $corrected,
            $actor,
            $correctionReason,
        );

        return $corrected;
    }

    public function createCorrectedDraftFromCancelledRequest(
        AppUser $user,
        LoanRequest $source,
    ): LoanRequest {
        return DB::transaction(function () use ($user, $source): LoanRequest {
            $sourceRequest = LoanRequest::query()
                ->whereKey($source->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $sourceRequest->user_id !== (int) $user->user_id) {
                throw ValidationException::withMessages([
                    'loan_request' => 'You can only create a corrected request from your own cancelled request.',
                ]);
            }

            $status = $sourceRequest->status instanceof LoanRequestStatus
                ? $sourceRequest->status->value
                : (string) $sourceRequest->status;

            if ($status !== LoanRequestStatus::Cancelled->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only cancelled requests can be corrected.',
                ]);
            }

            $existingDraft = LoanRequest::query()
                ->where('user_id', $user->user_id)
                ->where('status', LoanRequestStatus::Draft->value)
                ->lockForUpdate()
                ->first();

            if ($existingDraft !== null) {
                throw ValidationException::withMessages([
                    'draft' => 'You already have an active draft. Please continue or clear your existing draft before creating a corrected request.',
                ]);
            }

            $before = $this->serializer->serializeDetail($sourceRequest);

            $draft = new LoanRequest;
            $draft->user_id = $user->user_id;
            $draft->corrected_from_id = $sourceRequest->id;
            $draft->acctno = (string) ($user->acctno ?? '');
            $draft->typecode = (string) $sourceRequest->typecode;
            $draft->loan_type_label_snapshot = (string) $sourceRequest->loan_type_label_snapshot;
            $draft->requested_amount = $sourceRequest->requested_amount;
            $draft->requested_term = (int) $sourceRequest->requested_term;
            $draft->loan_purpose = (string) $sourceRequest->loan_purpose;
            $draft->other_loan_type_name = $sourceRequest->other_loan_type_name;
            $draft->availment_status = (string) $sourceRequest->availment_status;
            $draft->status = LoanRequestStatus::Draft;
            $draft->workflow_version = $sourceRequest->workflow_version
                ?? LoanRequestWorkflowVersion::LegacyV1;
            $draft->submitted_at = null;
            $draft->save();

            $sourceRequest->loadMissing('people');

            foreach ($sourceRequest->people as $person) {
                $role = $person->role instanceof LoanRequestPersonRole
                    ? $person->role->value
                    : (string) $person->role;

                if (! in_array($role, [
                    LoanRequestPersonRole::Applicant->value,
                    LoanRequestPersonRole::CoMakerOne->value,
                    LoanRequestPersonRole::CoMakerTwo->value,
                ], true)) {
                    continue;
                }

                $clone = $person->replicate();
                $clone->loan_request_id = $draft->id;
                $clone->save();
            }

            $draft->loadMissing('people');

            $after = $this->serializer->serializeDetail($draft);

            $this->recordCorrectedDraftAudit(
                $draft,
                $user,
                $before,
                $after,
            );

            return $draft;
        });
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordCorrectedDraftAudit(
        LoanRequest $draft,
        AppUser $actor,
        array $before,
        array $after,
    ): void {
        if (! $this->schemaCapabilities->hasTable('loan_request_changes')) {
            return;
        }

        LoanRequestChange::query()->create([
            'loan_request_id' => $draft->id,
            'changed_by' => $actor->user_id,
            'action' => 'create_corrected_request',
            'reason' => 'Created corrected draft from cancelled request.',
            'before_json' => $before,
            'after_json' => $after,
            'changed_fields_json' => [
                'corrected_from_id',
                'status',
                'copied_loan_details',
                'copied_people_snapshots',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAdminCorrectedRequestAudit(
        LoanRequest $correctedRequest,
        AppUser $actor,
        string $correctionReason,
        array $before,
        array $after,
    ): void {
        if (! $this->schemaCapabilities->hasTable('loan_request_changes')) {
            return;
        }

        LoanRequestChange::query()->create([
            'loan_request_id' => $correctedRequest->id,
            'changed_by' => $actor->user_id,
            'action' => 'admin_create_corrected_request',
            'reason' => $correctionReason,
            'before_json' => $before,
            'after_json' => $after,
            'changed_fields_json' => [
                'corrected_from_id',
                'copied_loan_details',
                'copied_people_snapshots',
                'admin_correction_reason',
            ],
        ]);
    }

    private function notifyMemberOfAdminCorrectedCopy(
        LoanRequest $sourceRequest,
        LoanRequest $correctedRequest,
        AppUser $actor,
        string $correctionReason,
    ): void {
        $sourceRequest->loadMissing('user');
        $correctedRequest->loadMissing('user');

        $member = $sourceRequest->user;

        if ($member === null || ! $member->hasMemberAccess()) {
            return;
        }

        $member->notify(new LoanRequestAdminCorrectedCreatedNotification(
            $sourceRequest,
            $correctedRequest,
            $correctionReason,
            $actor,
        ));
    }

    private function notifyAdminsOfSubmission(int $loanRequestId): void
    {
        $loanRequest = LoanRequest::query()
            ->with('user')
            ->find($loanRequestId);

        if ($loanRequest === null) {
            return;
        }

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if (! in_array($status, [
            LoanRequestStatus::Submitted->value,
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
        ], true)) {
            return;
        }

        $admins = $this->notificationRecipients->adminsAndSuperadmins();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send(
            $admins,
            new LoanRequestSubmittedNotification($loanRequest),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{typecode: string, label: string}>
     */
    public function getLoanTypes(): Collection
    {
        return $this->lookupService->getLoanTypes();
    }

    /**
     * @return list<array{
     *     id: int,
     *     status: string,
     *     typecode: string|null,
     *     loan_type_label_snapshot: string|null,
     *     requested_amount: string|float|int|null,
     *     requested_term: int|string|null,
     *     submitted_at: string|null,
     *     updated_at: string|null,
     *     assigned_officer: array{user_id: int, name: string, display_code: string|null}|null
     * }>
     */
    public function getMemberRequestSummaries(AppUser $user, int $limit = 10): array
    {
        return $this->lookupService->getMemberRequestSummaries($user, $limit);
    }

    /**
     * Overrides the two declarations answers that are always system-derived
     * (existing loans / pending cases) with fresh values, so the member's
     * wizard cannot persist a different answer than the account records show.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applySystemDeclarations(AppUser $user, array $payload): array
    {
        if (! is_array($payload['declarations'] ?? null)) {
            return $payload;
        }

        $systemDeclarations = $this->declarationAutoFillService->getDeclarationData($user);

        $payload['declarations']['declaration_existing_loans'] = $systemDeclarations['declaration_existing_loans'];
        $payload['declarations']['declaration_pending_cases'] = $systemDeclarations['declaration_pending_cases'];

        return $payload;
    }

    private function getActiveEditableRequest(AppUser $user): ?LoanRequest
    {
        return LoanRequest::query()
            ->where('user_id', $user->user_id)
            ->whereIn('status', [
                LoanRequestStatus::Draft->value,
                LoanRequestStatus::PendingCoMakerSignatures->value,
                LoanRequestStatus::NeedsRevision->value,
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function initializeLoanRequest(AppUser $user): LoanRequest
    {
        $loanRequest = new LoanRequest;
        $loanRequest->user_id = $user->user_id;
        $loanRequest->acctno = (string) ($user->acctno ?? '');
        $loanRequest->workflow_version = LoanRequestWorkflowVersion::DocumentWorkflowV2;

        return $loanRequest;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fillSubmittedDetails(
        LoanRequest $loanRequest,
        array $payload,
    ): void {
        $this->fillLoanRequestDetails($loanRequest, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fillLoanRequest(
        LoanRequest $loanRequest,
        array $payload,
        LoanRequestStatus $status,
        bool $markSubmitted,
    ): void {
        $this->fillLoanRequestDetails($loanRequest, $payload);

        $loanRequest->status = $status;

        if (in_array($status, [
            LoanRequestStatus::Draft,
            LoanRequestStatus::PendingCoMakerSignatures,
        ], true)) {
            $loanRequest->submitted_at = null;
        } elseif ($markSubmitted) {
            $loanRequest->submitted_at = now();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fillLoanRequestDetails(
        LoanRequest $loanRequest,
        array $payload,
    ): void {
        $payload = array_merge([
            'typecode' => $loanRequest->typecode ?? '',
            'requested_amount' => $loanRequest->requested_amount ?? '0',
            'requested_term' => $loanRequest->requested_term ?? 0,
            'loan_purpose' => $loanRequest->loan_purpose ?? '',
            'other_loan_type_name' => $loanRequest->other_loan_type_name,
            'availment_status' => $loanRequest->availment_status ?? '',
            'requested_payment_frequency' => $loanRequest->requested_payment_frequency,
            'kind_of_loan' => $loanRequest->kind_of_loan,
        ], $payload);

        $typecode = (string) ($payload['typecode'] ?? '');

        $loanRequest->typecode = $typecode;
        $loanRequest->loan_type_label_snapshot = $this->lookupService->resolveLoanTypeLabel($typecode);
        $loanRequest->requested_amount = $this->normalizeDecimal($payload['requested_amount'] ?? null) ?? '0';
        $loanRequest->requested_term = (int) ($payload['requested_term'] ?? 0);
        $loanRequest->loan_purpose = (string) ($payload['loan_purpose'] ?? '');
        $loanRequest->other_loan_type_name = $payload['other_loan_type_name'] !== null
            ? (string) $payload['other_loan_type_name']
            : null;
        $loanRequest->availment_status = (string) ($payload['availment_status'] ?? '');
        $loanRequest->requested_payment_frequency = $payload['requested_payment_frequency'] !== null
            ? (string) $payload['requested_payment_frequency']
            : null;
        $loanRequest->kind_of_loan = $payload['kind_of_loan'] !== null
            ? (string) $payload['kind_of_loan']
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertPeopleSnapshots(LoanRequest $loanRequest, array $payload): void
    {
        $this->upsertPersonSnapshot(
            $loanRequest,
            LoanRequestPersonRole::Applicant,
            $this->extractPersonPayload($payload, 'applicant'),
        );
        $this->upsertPersonSnapshot(
            $loanRequest,
            LoanRequestPersonRole::CoMakerOne,
            $this->extractPersonPayload($payload, 'co_maker_1'),
        );
        $this->upsertPersonSnapshot(
            $loanRequest,
            LoanRequestPersonRole::CoMakerTwo,
            $this->extractPersonPayload($payload, 'co_maker_2'),
        );
    }

    /**
     * Propagate a member's current gross monthly income from their
     * application profile onto the applicant snapshot of their active loan
     * requests, so income-dependent figures (GNTHP) stay in sync before a
     * staff member ever edits processing details.
     *
     * Terminal/released requests are excluded: their documents are final.
     * Draft requests are updated but never audited (drafts must not create
     * audit entries).
     *
     * @return int Number of applicant snapshots updated.
     */
    public function syncApplicantIncomeFromProfile(
        AppUser $member,
        ?float $grossMonthlyIncome,
    ): int {
        if ($grossMonthlyIncome === null) {
            return 0;
        }

        $normalized = $this->normalizeDecimal($grossMonthlyIncome);

        if ($normalized === null) {
            return 0;
        }

        $acctno = trim((string) $member->acctno);

        $activeRequests = LoanRequest::query()
            ->where(fn ($query) => $query
                ->where('user_id', $member->user_id)
                ->when($acctno !== '', fn ($query) => $query->orWhere('acctno', $acctno)))
            ->whereNotIn('status', self::INCOME_SYNC_EXCLUDED_STATUSES)
            ->with('people')
            ->get();

        $updated = 0;

        foreach ($activeRequests as $loanRequest) {
            $applicant = $loanRequest->people
                ->firstWhere('role', LoanRequestPersonRole::Applicant);

            if ($applicant === null) {
                continue;
            }

            $current = $applicant->getAttribute('gross_monthly_income');

            if (
                $current !== null
                && abs((float) $current - (float) $normalized) < 0.005
            ) {
                continue;
            }

            $before = $current;
            $applicant->setAttribute('gross_monthly_income', $normalized);
            $applicant->save();

            $isDraft = $loanRequest->status instanceof LoanRequestStatus
                ? $loanRequest->status->value === LoanRequestStatus::Draft->value
                : (string) $loanRequest->status === LoanRequestStatus::Draft->value;

            if (! $isDraft) {
                LoanRequestChange::query()->create([
                    'loan_request_id' => $loanRequest->id,
                    'changed_by' => $member->user_id,
                    'action' => LoanRequestChange::ACTION_MEMBER_PROFILE_INCOME_SYNCED,
                    'reason' => 'Gross monthly income updated in the member profile.',
                    'before_json' => ['applicant' => ['gross_monthly_income' => $before]],
                    'after_json' => ['applicant' => ['gross_monthly_income' => $normalized]],
                    'changed_fields_json' => ['applicant.gross_monthly_income'],
                ]);
            }

            $updated++;
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractPersonPayload(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertPersonSnapshot(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
        array $data,
    ): LoanRequestPerson {
        $birthplaceValues = $this->resolveBirthplaceValues($data);
        $addressValues = $this->resolveAddressValues($data, 'address');
        $employerAddressValues = $this->resolveAddressValues(
            $data,
            'employer_business_address',
            'employer_business_',
        );

        $person = $loanRequest->people()->firstOrNew([
            'role' => $role,
        ]);

        $attributes = [
            'role' => $role,
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'middle_name' => $this->normalizeOptionalString($data['middle_name'] ?? null),
            'nickname' => $this->normalizeOptionalString($data['nickname'] ?? null),
            'birthdate' => $this->normalizeOptionalString($data['birthdate'] ?? null),
            'birthplace' => $birthplaceValues['legacy'],
            'birthplace_city' => $birthplaceValues['city'],
            'birthplace_province' => $birthplaceValues['province'],
            'address' => $addressValues['legacy'],
            'address1' => $addressValues['address1'],
            'address_barangay' => $addressValues['barangay'],
            'address2' => $addressValues['address2'],
            'address3' => $addressValues['address3'],
            'address_zip' => $this->normalizeOptionalString($data['address_zip'] ?? null),
            'length_of_stay' => $this->normalizeOptionalString($data['length_of_stay'] ?? null),
            'housing_status' => $this->normalizeOptionalString($data['housing_status'] ?? null),
            'cell_no' => $this->normalizeOptionalString($data['cell_no'] ?? null),
            'civil_status' => $this->normalizeOptionalString($data['civil_status'] ?? null),
            'sex' => $this->normalizeOptionalString($data['sex'] ?? null),
            'educational_attainment' => $this->normalizeOptionalString(
                $data['educational_attainment'] ?? null,
            ),
            'number_of_children' => $this->normalizeOptionalInt(
                $data['number_of_children'] ?? null,
            ),
            'spouse_name' => $this->normalizeOptionalString($data['spouse_name'] ?? null),
            'spouse_birthdate' => $this->normalizeOptionalString($data['spouse_birthdate'] ?? null),
            'spouse_cell_no' => $this->normalizeOptionalString(
                $data['spouse_cell_no'] ?? null,
            ),
            'employment_type' => $this->normalizeOptionalString($data['employment_type'] ?? null),
            'employer_business_name' => $this->normalizeOptionalString(
                $data['employer_business_name'] ?? null,
            ),
            'employer_business_address' => $employerAddressValues['legacy'],
            'employer_business_address1' => $employerAddressValues['address1'],
            'employer_business_address_barangay' => $employerAddressValues['barangay'],
            'employer_business_address2' => $employerAddressValues['address2'],
            'employer_business_address3' => $employerAddressValues['address3'],
            'employer_business_address_zip' => $this->normalizeOptionalString(
                $data['employer_business_address_zip'] ?? null,
            ),
            'telephone_no' => $this->normalizeOptionalString($data['telephone_no'] ?? null),
            'current_position' => $this->normalizeOptionalString(
                $data['current_position'] ?? null,
            ),
            'nature_of_business' => $this->normalizeOptionalString(
                $data['nature_of_business'] ?? null,
            ),
            'institutional_employer_category' => $this->normalizeOptionalString(
                $data['institutional_employer_category'] ?? null,
            ),
            'years_in_work_business' => $this->normalizeOptionalString(
                $data['years_in_work_business'] ?? null,
            ),
            'employer_date_employed' => $this->normalizeOptionalString(
                $data['employer_date_employed'] ?? null,
            ),
            'gross_monthly_income' => $this->normalizeDecimal(
                $data['gross_monthly_income'] ?? null,
            ),
            'payday' => $this->normalizeOptionalString($data['payday'] ?? null),
        ];

        $person->fill($attributes);
        $person->loanRequest()->associate($loanRequest);
        $person->save();

        return $person;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePerson(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
    ): array {
        $person = $loanRequest->people
            ->first(fn ($item) => $item->role === $role);

        if ($person === null) {
            return [];
        }

        return $this->hydrateStructuredPersonFields($person->toArray());
    }

    /**
     * @return array{
     *     id: int,
     *     status: string,
     *     typecode: string|null,
     *     loan_type_label_snapshot: string|null,
     *     requested_amount: string|float|int|null,
     *     requested_term: int|string|null,
     *     loan_purpose: string|null,
     *     other_loan_type_name: string|null,
     *     availment_status: string|null,
     *     requested_payment_frequency: string|null,
     *     kind_of_loan: string|null,
     *     submitted_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function serializeLoanRequest(LoanRequest $loanRequest): array
    {
        $status = LoanRequestStatus::memberVisibleValue($loanRequest->status)
            ?? (string) $loanRequest->status;
        $isDraft = $status === LoanRequestStatus::Draft->value;

        $typecode = $this->normalizeDraftString($loanRequest->typecode, $isDraft);
        $loanPurpose = $this->normalizeDraftString($loanRequest->loan_purpose, $isDraft);
        $otherLoanTypeName = $this->normalizeDraftString($loanRequest->other_loan_type_name, $isDraft);
        $availmentStatus = $this->normalizeDraftString($loanRequest->availment_status, $isDraft);
        $requestedPaymentFrequency = $this->normalizeDraftString(
            $loanRequest->requested_payment_frequency,
            $isDraft,
        );
        $kindOfLoan = $this->normalizeDraftString(
            $loanRequest->kind_of_loan,
            $isDraft,
        );
        $loanTypeLabel = $this->normalizeDraftString(
            $loanRequest->loan_type_label_snapshot,
            $isDraft,
        );

        $requestedTerm = $loanRequest->requested_term;
        if ($isDraft && ($requestedTerm === 0 || $requestedTerm === null)) {
            $requestedTerm = null;
        }

        $requestedAmount = $this->normalizeDraftDecimal(
            $loanRequest->requested_amount,
            $isDraft,
        );

        return [
            'id' => $loanRequest->id,
            'reference' => $loanRequest->reference,
            'status' => $status,
            'typecode' => $typecode,
            'loan_type_label_snapshot' => $loanTypeLabel,
            'requested_amount' => $requestedAmount,
            'requested_term' => $requestedTerm,
            'loan_purpose' => $loanPurpose,
            'other_loan_type_name' => $otherLoanTypeName,
            'availment_status' => $availmentStatus,
            'requested_payment_frequency' => $requestedPaymentFrequency,
            'kind_of_loan' => $kindOfLoan,
            'submitted_at' => $loanRequest->submitted_at?->toDateTimeString(),
            'updated_at' => $loanRequest->updated_at?->toDateTimeString(),
        ];
    }

    private function normalizeDraftString(?string $value, bool $isDraft): ?string
    {
        if (! $isDraft) {
            return $value;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeDraftDecimal(mixed $value, bool $isDraft): ?string
    {
        if (! $isDraft) {
            return $value === null ? null : (string) $value;
        }

        if ($value === null) {
            return null;
        }

        if (is_numeric($value) && (float) $value === 0.0) {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function personSnapshotHasChanges(
        LoanRequestPerson $person,
        array $attributes,
    ): bool {
        foreach ($attributes as $key => $value) {
            if ($this->normalizeComparableValue($person->getAttribute($key)) !== $this->normalizeComparableValue($value)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeComparableValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_string($value) && str_contains($value, '.') && is_numeric($value)) {
            return rtrim(rtrim($value, '0'), '.');
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function normalizePersonSelectValues(array $person): array
    {
        if (! array_key_exists('housing_status', $person)) {
            $person['housing_status'] = null;
        }

        $person['housing_status'] = $this->normalizeHousingStatusValue(
            $person['housing_status'] ?? null,
        );

        if (! array_key_exists('civil_status', $person)) {
            $person['civil_status'] = null;
        }

        $person['civil_status'] = $this->normalizeCivilStatusValue(
            $person['civil_status'] ?? null,
        );

        if (! array_key_exists('sex', $person)) {
            $person['sex'] = null;
        }

        $person['sex'] = $this->normalizeSexValue(
            $person['sex'] ?? null,
        );

        if (! array_key_exists('payday', $person)) {
            $person['payday'] = null;
        }

        $person['payday'] = $this->normalizePaydayValue(
            $person['payday'] ?? null,
        );

        return $person;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function hydrateStructuredPersonFields(array $person): array
    {
        $birthdate = $this->normalizeDateForInput($person['birthdate'] ?? null);
        $birthplaceValues = $this->resolveBirthplaceValues($person);
        $addressValues = $this->resolveAddressValues($person, 'address');
        $employerAddressValues = $this->resolveAddressValues(
            $person,
            'employer_business_address',
            'employer_business_',
        );

        return array_merge($person, [
            'birthdate' => $birthdate,
            'spouse_birthdate' => $this->normalizeDateForInput($person['spouse_birthdate'] ?? null),
            'birthplace' => $birthplaceValues['legacy'],
            'birthplace_city' => $birthplaceValues['city'],
            'birthplace_province' => $birthplaceValues['province'],
            'address' => $addressValues['legacy'],
            'address1' => $addressValues['address1'],
            'address_barangay' => $addressValues['barangay'],
            'address2' => $addressValues['address2'],
            'address3' => $addressValues['address3'],
            'address_zip' => $this->normalizeOptionalString($person['address_zip'] ?? null),
            'employer_business_address' => $employerAddressValues['legacy'],
            'employer_business_address1' => $employerAddressValues['address1'],
            'employer_business_address_barangay' => $employerAddressValues['barangay'],
            'employer_business_address2' => $employerAddressValues['address2'],
            'employer_business_address3' => $employerAddressValues['address3'],
            'employer_business_address_zip' => $this->normalizeOptionalString(
                $person['employer_business_address_zip'] ?? null,
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{city: string|null, province: string|null, legacy: string|null}
     */
    private function resolveBirthplaceValues(array $data): array
    {
        $city = $this->normalizeOptionalString($data['birthplace_city'] ?? null);
        $province = $this->normalizeOptionalString(
            $data['birthplace_province'] ?? null,
        );
        $legacy = $this->normalizeOptionalString($data['birthplace'] ?? null);

        if ($city === null && $province === null && $legacy !== null) {
            $parsed = LocationComposer::parseLegacyBirthplace($legacy);
            $city = $parsed['city'];
            $province = $parsed['province'];
        }

        $composed = LocationComposer::composeBirthplace($city, $province);
        $legacyValue = $composed !== '' ? $composed : $legacy;

        return [
            'city' => $city,
            'province' => $province,
            'legacy' => $legacyValue,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{address1: string|null, barangay: string|null, address2: string|null, address3: string|null, legacy: string|null}
     */
    private function resolveAddressValues(
        array $data,
        string $legacyKey,
        string $prefix = '',
    ): array {
        $address1 = $this->normalizeOptionalString(
            $data[$prefix.'address1'] ?? null,
        );
        $barangay = $this->normalizeOptionalString(
            $data[$prefix.'address_barangay'] ?? null,
        );
        $address2 = $this->normalizeOptionalString(
            $data[$prefix.'address2'] ?? null,
        );
        $address3 = $this->normalizeOptionalString(
            $data[$prefix.'address3'] ?? null,
        );
        $legacy = $this->normalizeOptionalString($data[$legacyKey] ?? null);

        if ($address1 === null && $address2 === null && $address3 === null && $legacy !== null) {
            $parsed = LocationComposer::parseLegacyAddress($legacy);
            $address1 = $parsed['address1'];
            $address2 = $parsed['address2'];
            $address3 = $parsed['address3'];
        }

        $composed = LocationComposer::compose($address1, $address2, $address3, $barangay);
        $legacyValue = $composed !== '' ? $composed : $legacy;

        return [
            'address1' => $address1,
            'barangay' => $barangay,
            'address2' => $address2,
            'address3' => $address3,
            'legacy' => $legacyValue,
        ];
    }

    private function normalizeHousingStatusValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $upper = strtoupper($trimmed);

        if (in_array($upper, ['OWNED', 'OWN', 'OWNER'], true)) {
            return 'OWNED';
        }

        if (in_array($upper, ['RENT', 'RENTAL', 'RENTED', 'RENTING'], true)) {
            return 'RENT';
        }

        return null;
    }

    private function normalizeCivilStatusValue(mixed $value): ?string
    {
        return LoanCivilStatus::normalize($value);
    }

    private function normalizeSexValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $resolved = match (strtoupper($trimmed)) {
            'MALE', 'M' => 'Male',
            'FEMALE', 'F' => 'Female',
            default => null,
        };

        return $resolved !== null && in_array($resolved, LoanSex::values(), true)
            ? $resolved
            : null;
    }

    private function normalizePaydayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (in_array($trimmed, LoanPaydayOption::values(), true)) {
            return $trimmed;
        }

        $upper = strtoupper($trimmed);
        $compact = preg_replace('/[^0-9A-Z]/', '', $upper) ?? '';

        return match (true) {
            $upper === 'WEEKLY' => 'Weekly',
            $upper === 'MONTHLY' => 'Monthly',
            $compact === 'BIWEEKLY' => 'Weekly',
            $compact === '15' => 'Quincenal',
            $compact === '30' => 'Quincenal',
            str_contains($upper, '15') && str_contains($upper, '30') => 'Quincenal',
            $compact === 'SEMIMONTHLY' => 'Quincenal',
            str_contains($upper, 'LUMP') => 'Due date',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Resolve address1/address2(city)/address3(province)/barangay as one
     * paired group instead of falling back per-field. This keeps barangay
     * from being taken from a different (wmaster vs. profile) source than
     * city/province, which previously produced mismatched pairs the
     * barangay dropdown couldn't resolve/preselect.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    private function resolvePairedApplicantAddress(
        ?Wmaster $wmaster,
        ?MemberApplicationProfile $profile,
    ): array {
        $wmasterAddress1 = $this->normalizeOptionalString($wmaster?->address1);
        $wmasterBarangay = $this->normalizeOptionalString($wmaster?->address2);
        $wmasterAddress2 = $this->normalizeOptionalString($wmaster?->address3);
        $wmasterAddress3 = $this->normalizeOptionalString($wmaster?->address4);

        if ($wmasterAddress1 === null && $wmasterAddress2 === null && $wmasterAddress3 === null) {
            $legacyAddress = $this->normalizeOptionalString($wmaster?->address);

            if ($legacyAddress !== null) {
                $parsedAddress = LocationComposer::parseLegacyAddress($legacyAddress);
                $wmasterAddress1 = $parsedAddress['address1'];
                $wmasterAddress2 = $parsedAddress['address2'];
                $wmasterAddress3 = $parsedAddress['address3'];
            }
        }

        if ($wmasterAddress1 !== null || $wmasterAddress2 !== null || $wmasterAddress3 !== null) {
            return [$wmasterAddress1, $wmasterAddress2, $wmasterAddress3, $wmasterBarangay];
        }

        return [
            $this->normalizeOptionalString($profile?->home_address1),
            $this->normalizeOptionalString($profile?->home_address2),
            $this->normalizeOptionalString($profile?->home_address3),
            $this->normalizeOptionalString($profile?->home_address_barangay),
        ];
    }

    private function buildApplicantSnapshot(AppUser $user): array
    {
        $wmaster = $user->wmaster;
        $profile = $user->memberApplicationProfile;
        $hasDependentColumn = $this->schemaCapabilities->hasColumn(
            'wmaster',
            'dependent',
        );

        $nameParts = $wmaster?->resolvedNameParts() ?? [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
        ];
        $firstName = $this->normalizeOptionalString($nameParts['first_name']);
        $middleName = $this->normalizeOptionalString($nameParts['middle_name']);
        $lastName = $this->normalizeOptionalString($nameParts['last_name']);
        $wmasterBirthplace = $this->normalizeOptionalString($wmaster?->birthplace);
        $birthplaceCity = null;
        $birthplaceProvince = null;

        if ($wmasterBirthplace !== null) {
            $parsedBirthplace = LocationComposer::parseLegacyBirthplace(
                $wmasterBirthplace,
            );
            $birthplaceCity = $parsedBirthplace['city'];
            $birthplaceProvince = $parsedBirthplace['province'];
        } else {
            $birthplaceCity = $this->normalizeOptionalString(
                $profile?->birthplace_city,
            );
            $birthplaceProvince = $this->normalizeOptionalString(
                $profile?->birthplace_province,
            );

            if ($birthplaceCity === null && $birthplaceProvince === null) {
                $legacyBirthplace = $this->normalizeOptionalString(
                    $profile?->birthplace,
                );

                if ($legacyBirthplace !== null) {
                    $parsedBirthplace = LocationComposer::parseLegacyBirthplace(
                        $legacyBirthplace,
                    );
                    $birthplaceCity = $parsedBirthplace['city'];
                    $birthplaceProvince = $parsedBirthplace['province'];
                }
            }
        }

        $birthplace = $wmasterBirthplace;

        if ($birthplace === null) {
            $birthplace = LocationComposer::composeBirthplace(
                $birthplaceCity,
                $birthplaceProvince,
            );
            $birthplace = $birthplace !== ''
                ? $birthplace
                : $this->normalizeOptionalString($profile?->birthplace);
        }

        [$address1, $address2, $address3, $addressBarangay] = $this->resolvePairedApplicantAddress(
            $wmaster,
            $profile,
        );

        if ($address2 !== null) {
            $address2 = $this->psgcService->resolveLocalityName($address2);
        }

        if ($address3 !== null) {
            $address3 = $this->psgcService->resolveProvinceName($address3);
        }

        if ($addressBarangay !== null && $address2 !== null) {
            $addressBarangay = $this->psgcService->resolveBarangayName(
                $addressBarangay,
                $address2,
                $address3,
            );
        }

        $address = LocationComposer::compose($address1, $address2, $address3, $addressBarangay);
        $address = $address !== ''
            ? $address
            : $this->normalizeOptionalString($wmaster?->displayAddress());

        $employerAddress1 = $this->normalizeOptionalString(
            $profile?->employer_business_address1,
        );
        $employerAddress2 = $this->normalizeOptionalString(
            $profile?->employer_business_address2,
        );
        $employerAddress3 = $this->normalizeOptionalString(
            $profile?->employer_business_address3,
        );

        if (
            $employerAddress1 === null
            && $employerAddress2 === null
            && $employerAddress3 === null
        ) {
            $legacyEmployerAddress = $this->normalizeOptionalString(
                $profile?->employer_business_address,
            );

            if ($legacyEmployerAddress !== null) {
                $parsedEmployerAddress = LocationComposer::parseLegacyAddress(
                    $legacyEmployerAddress,
                );
                $employerAddress1 = $parsedEmployerAddress['address1'];
                $employerAddress2 = $parsedEmployerAddress['address2'];
                $employerAddress3 = $parsedEmployerAddress['address3'];
            }
        }

        $employerBusinessAddress = LocationComposer::compose(
            $employerAddress1,
            $employerAddress2,
            $employerAddress3,
        );
        $employerBusinessAddress = $employerBusinessAddress !== ''
            ? $employerBusinessAddress
            : $this->normalizeOptionalString($profile?->employer_business_address);
        $spouseName = $this->normalizeOptionalString($wmaster?->spouse);
        $currentPosition = $this->normalizeOptionalString(
            $profile?->current_position,
        );
        $numberOfChildren = null;

        if ($currentPosition === null) {
            $currentPosition = $this->normalizeOptionalString(
                $wmaster?->occupation,
            );
        }

        if ($hasDependentColumn && $wmaster?->dependent !== null) {
            $numberOfChildren = (string) $wmaster->dependent;
        } elseif ($profile?->number_of_children !== null) {
            $numberOfChildren = (string) $profile->number_of_children;
        }

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'nickname' => $profile?->nickname,
            'birthdate' => $wmaster?->birthday?->toDateString(),
            'birthplace' => $birthplace,
            'birthplace_city' => $birthplaceCity,
            'birthplace_province' => $birthplaceProvince,
            'address' => $address,
            'address1' => $address1,
            'address_barangay' => $addressBarangay,
            'address2' => $address2,
            'address3' => $address3,
            'address_zip' => $this->normalizeOptionalString(
                $wmaster?->zone_number ?? $profile?->home_address_zip,
            ),
            'length_of_stay' => $profile?->length_of_stay,
            'housing_status' => $this->normalizeHousingStatusValue(
                $wmaster?->restype ?? $profile?->housing_status,
            ),
            'cell_no' => $user->phoneno,
            'civil_status' => $this->normalizeCivilStatusValue($wmaster?->civilstat)
                ?? $this->normalizeCivilStatusValue($profile?->civil_status),
            'educational_attainment' => $profile?->educational_attainment,
            'number_of_children' => $numberOfChildren,
            'spouse_name' => $spouseName ?? $profile?->spouse_name,
            'spouse_birthdate' => $profile?->spouse_birthdate?->toDateString(),
            'spouse_cell_no' => $profile?->spouse_cell_no,
            'employment_type' => $profile?->employment_type,
            'employer_business_name' => $profile?->employer_business_name,
            'employer_business_address' => $employerBusinessAddress,
            'employer_business_address1' => $employerAddress1,
            'employer_business_address2' => $employerAddress2,
            'employer_business_address3' => $employerAddress3,
            'employer_business_address_zip' => $this->normalizeOptionalString(
                $profile?->employer_business_address_zip,
            ),
            'telephone_no' => $profile?->telephone_no,
            'current_position' => $currentPosition,
            'nature_of_business' => $profile?->nature_of_business,
            'institutional_employer_category' => $profile?->institutional_employer_category?->value,
            'years_in_work_business' => $profile?->years_in_work_business,
            'employer_date_employed' => $profile?->employer_date_employed?->toDateString(),
            'gross_monthly_income' => $profile?->gross_monthly_income !== null
                ? (string) $profile->gross_monthly_income
                : null,
            'payday' => $profile?->payday,
        ];
    }

    /**
     * Fill missing applicant fields (civil status and the fields sharing its
     * "About you" sync history) from the member's saved application profile,
     * same precedence as applyBankingProfileDefaults()/applyDependentsProfileDefaults():
     * only null fields are overwritten so a member's own in-progress draft
     * edits are never clobbered.
     *
     * Without this, an applicant's LoanRequestPerson row created before the
     * member filled in Profile Settings stays permanently stale — serializePerson()
     * reads the row verbatim with no fallback to the live profile.
     *
     * @param  array<string, mixed>  $applicant
     * @return array<string, mixed>
     */
    private function applyApplicantProfileDefaults(array $applicant, AppUser $user): array
    {
        if ($applicant === []) {
            return $applicant;
        }

        $snapshot = $this->buildApplicantSnapshot($user);

        $fields = [
            'civil_status',
            'housing_status',
            'length_of_stay',
            'spouse_name',
            'spouse_birthdate',
            'spouse_cell_no',
            'number_of_children',
        ];

        foreach ($fields as $field) {
            if (($applicant[$field] ?? null) !== null) {
                continue;
            }

            $snapshotValue = $snapshot[$field] ?? null;

            if ($snapshotValue === null) {
                continue;
            }

            $applicant[$field] = $snapshotValue;
        }

        return $applicant;
    }

    /**
     * @return array<string, bool>
     */
    private function buildApplicantReadOnlyMap(AppUser $user): array
    {
        $wmaster = $user->wmaster;
        $hasDependentColumn = $this->schemaCapabilities->hasColumn(
            'wmaster',
            'dependent',
        );
        $nameParts = $wmaster?->resolvedNameParts() ?? [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
        ];
        $hasName = $nameParts['first_name'] !== ''
            || $nameParts['middle_name'] !== ''
            || $nameParts['last_name'] !== '';
        $birthplaceValue = $this->normalizeOptionalString($wmaster?->birthplace);
        $birthplaceCity = null;
        $birthplaceProvince = null;

        if ($birthplaceValue !== null) {
            $parsedBirthplace = LocationComposer::parseLegacyBirthplace(
                $birthplaceValue,
            );
            $birthplaceCity = $this->normalizeOptionalString($parsedBirthplace['city']);
            $birthplaceProvince = $this->normalizeOptionalString(
                $parsedBirthplace['province'],
            );
        }

        $address1 = $this->normalizeOptionalString($wmaster?->address1);
        $addressBarangay = $this->normalizeOptionalString($wmaster?->address2);
        $address2 = $this->normalizeOptionalString($wmaster?->address3);
        $address3 = $this->normalizeOptionalString($wmaster?->address4);
        $addressZip = $this->normalizeOptionalString($wmaster?->zone_number);
        $legacyAddress = $this->normalizeOptionalString($wmaster?->address);

        if (
            $address1 === null
            && $address2 === null
            && $address3 === null
            && $legacyAddress !== null
        ) {
            $parsedAddress = LocationComposer::parseLegacyAddress(
                $legacyAddress,
            );
            $address1 = $this->normalizeOptionalString($parsedAddress['address1']);
            $address2 = $this->normalizeOptionalString($parsedAddress['address2']);
            $address3 = $this->normalizeOptionalString($parsedAddress['address3']);
        }

        $hasAddress = $address1 !== null
            || $address2 !== null
            || $address3 !== null
            || $legacyAddress !== null;

        return [
            'first_name' => $hasName,
            'middle_name' => $nameParts['middle_name'] !== '',
            'last_name' => $hasName,
            'birthdate' => $this->hasValue($wmaster?->birthday),
            'birthplace' => $birthplaceValue !== null,
            'birthplace_city' => $birthplaceCity !== null,
            'birthplace_province' => $birthplaceProvince !== null,
            'address' => $hasAddress,
            'address1' => $address1 !== null,
            'address_barangay' => $addressBarangay !== null,
            'address2' => $address2 !== null,
            'address3' => $address3 !== null,
            'address_zip' => $addressZip !== null,
            'housing_status' => $this->hasValue($wmaster?->restype),
            'civil_status' => $this->normalizeCivilStatusValue($wmaster?->civilstat) !== null,
            'number_of_children' => $hasDependentColumn
                && $this->hasValue($wmaster?->dependent),
            'spouse_name' => $this->hasValue($wmaster?->spouse),
        ];
    }

    private function resolveMemberName(AppUser $user): string
    {
        $name = $user->wmaster?->displayName();

        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        return $user->username;
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : null;
    }

    private function normalizeDateForInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $candidate = substr($trimmed, 0, 10);

        return preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $candidate) === 1
            ? $candidate
            : null;
    }

    private function normalizeOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (string) $value : null;
    }

    private function statusValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
    }
}
