<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\Wlntype;
use App\Support\DisplayText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanRequestService
{
    private const CIVIL_STATUS_OPTIONS = [
        'Single',
        'Married',
        'Separated',
        'Widowed',
    ];

    private const PAYDAY_OPTIONS = [
        'Weekly',
        '15th',
        '30th',
        '15th & 30th',
        'Bi-Weekly',
        'Monthly',
    ];

    /**
     * @return array{
     *     loanTypes: list<array{typecode: string, label: string}>,
     *     applicant: array<string, mixed>,
     *     coMakerOne: array<string, mixed>|null,
     *     coMakerTwo: array<string, mixed>|null,
     *     applicantReadOnly: array<string, bool>,
     *     member: array{name: string, acctno: string|null},
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

        if (Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');
        }

        $draft = $this->getActiveDraft($user);
        $applicantReadOnly = $this->buildApplicantReadOnlyMap($user);
        $memberName = $this->resolveMemberName($user);

        if ($draft !== null) {
            $draft->loadMissing('people');
        }

        $applicant = $draft !== null
            ? $this->serializePerson($draft, LoanRequestPersonRole::Applicant)
            : $this->buildApplicantSnapshot($user);

        $coMakerOne = $draft !== null
            ? $this->serializePerson($draft, LoanRequestPersonRole::CoMakerOne)
            : null;

        $coMakerTwo = $draft !== null
            ? $this->serializePerson($draft, LoanRequestPersonRole::CoMakerTwo)
            : null;

        $applicant = $this->normalizePersonSelectValues($applicant);
        $coMakerOne = $coMakerOne !== null
            ? $this->normalizePersonSelectValues($coMakerOne)
            : null;
        $coMakerTwo = $coMakerTwo !== null
            ? $this->normalizePersonSelectValues($coMakerTwo)
            : null;

        return [
            'loanTypes' => $this->getLoanTypes()->values()->all(),
            'applicant' => $applicant,
            'coMakerOne' => $coMakerOne,
            'coMakerTwo' => $coMakerTwo,
            'applicantReadOnly' => $applicantReadOnly,
            'member' => [
                'name' => $memberName,
                'acctno' => $user->acctno,
            ],
            'draft' => $draft !== null ? $this->serializeLoanRequest($draft) : null,
        ];
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
            $loanRequest = $this->getActiveDraft($user);

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

            $this->upsertPeopleSnapshots($loanRequest, $payload);

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
        return DB::transaction(function () use ($user, $payload): LoanRequest {
            $loanRequest = $this->getActiveDraft($user);

            if ($loanRequest === null) {
                $loanRequest = $this->initializeLoanRequest($user);
            }

            $this->fillLoanRequest(
                $loanRequest,
                $payload,
                LoanRequestStatus::UnderReview,
                true,
            );
            $loanRequest->save();

            $this->upsertPeopleSnapshots($loanRequest, $payload);

            return $loanRequest->loadMissing('people');
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{typecode: string, label: string}>
     */
    public function getLoanTypes(): Collection
    {
        if (! Schema::hasTable('wlntype') || ! Schema::hasColumn('wlntype', 'lntype')) {
            return collect();
        }

        $hasTypecode = Schema::hasColumn('wlntype', 'typecode');
        $columns = $hasTypecode ? ['typecode', 'lntype'] : ['lntype'];

        return Wlntype::query()
            ->select($columns)
            ->orderBy('lntype')
            ->get()
            ->map(function (Wlntype $type) use ($hasTypecode): array {
                $label = (string) $type->lntype;
                $typecode = $hasTypecode ? (string) $type->typecode : $label;

                return [
                    'typecode' => $typecode,
                    'label' => $label,
                ];
            });
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
     *     updated_at: string|null
     * }>
     */
    public function getMemberRequestSummaries(AppUser $user, int $limit = 10): array
    {
        if (! Schema::hasTable('loan_requests')) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        return LoanRequest::query()
            ->where('user_id', $user->user_id)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'typecode',
                'loan_type_label_snapshot',
                'requested_amount',
                'requested_term',
                'status',
                'submitted_at',
                'updated_at',
            ])
            ->map(fn (LoanRequest $request): array => $this->serializeRequestSummary($request))
            ->all();
    }

    private function resolveLoanTypeLabel(string $typecode): string
    {
        if (! Schema::hasTable('wlntype')) {
            return $typecode;
        }

        if (Schema::hasColumn('wlntype', 'typecode') && Schema::hasColumn('wlntype', 'lntype')) {
            $value = Wlntype::query()
                ->where('typecode', $typecode)
                ->value('lntype');

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        if (Schema::hasColumn('wlntype', 'lntype')) {
            $value = Wlntype::query()->where('lntype', $typecode)->value('lntype');

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $typecode;
    }

    /**
     * @return array{
     *     id: int,
     *     status: string,
     *     typecode: string|null,
     *     loan_type_label_snapshot: string|null,
     *     requested_amount: string|float|int|null,
     *     requested_term: int|string|null,
     *     submitted_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function serializeRequestSummary(LoanRequest $loanRequest): array
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        return [
            'id' => $loanRequest->id,
            'status' => $status,
            'typecode' => $loanRequest->typecode,
            'loan_type_label_snapshot' => $loanRequest->loan_type_label_snapshot,
            'requested_amount' => $loanRequest->requested_amount,
            'requested_term' => $loanRequest->requested_term,
            'submitted_at' => $loanRequest->submitted_at?->toDateTimeString(),
            'updated_at' => $loanRequest->updated_at?->toDateTimeString(),
        ];
    }

    private function getActiveDraft(AppUser $user): ?LoanRequest
    {
        return LoanRequest::query()
            ->where('user_id', $user->user_id)
            ->where('status', LoanRequestStatus::Draft->value)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function initializeLoanRequest(AppUser $user): LoanRequest
    {
        $loanRequest = new LoanRequest;
        $loanRequest->user_id = $user->user_id;
        $loanRequest->acctno = (string) ($user->acctno ?? '');

        return $loanRequest;
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
        $payload = array_merge([
            'typecode' => $loanRequest->typecode ?? '',
            'requested_amount' => $loanRequest->requested_amount ?? '0',
            'requested_term' => $loanRequest->requested_term ?? 0,
            'loan_purpose' => $loanRequest->loan_purpose ?? '',
            'availment_status' => $loanRequest->availment_status ?? '',
        ], $payload);

        $typecode = (string) ($payload['typecode'] ?? '');

        $loanRequest->typecode = $typecode;
        $loanRequest->loan_type_label_snapshot = $this->resolveLoanTypeLabel($typecode);
        $loanRequest->requested_amount = $this->normalizeDecimal($payload['requested_amount'] ?? null) ?? '0';
        $loanRequest->requested_term = (int) ($payload['requested_term'] ?? 0);
        $loanRequest->loan_purpose = (string) ($payload['loan_purpose'] ?? '');
        $loanRequest->availment_status = (string) ($payload['availment_status'] ?? '');
        $loanRequest->status = $status;

        if ($status === LoanRequestStatus::Draft) {
            $loanRequest->submitted_at = null;
        } elseif ($markSubmitted) {
            $loanRequest->submitted_at = now();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertPeopleSnapshots(LoanRequest $loanRequest, array $payload): void
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
        return $loanRequest->people()->updateOrCreate([
            'role' => $role,
        ], [
            'role' => $role,
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'middle_name' => $this->normalizeOptionalString($data['middle_name'] ?? null),
            'nickname' => $this->normalizeOptionalString($data['nickname'] ?? null),
            'birthdate' => $this->normalizeOptionalString($data['birthdate'] ?? null),
            'birthplace' => $this->normalizeOptionalString($data['birthplace'] ?? null),
            'address' => $this->normalizeOptionalString($data['address'] ?? null),
            'length_of_stay' => $this->normalizeOptionalString($data['length_of_stay'] ?? null),
            'housing_status' => $this->normalizeOptionalString($data['housing_status'] ?? null),
            'cell_no' => $this->normalizeOptionalString($data['cell_no'] ?? null),
            'civil_status' => $this->normalizeOptionalString($data['civil_status'] ?? null),
            'educational_attainment' => $this->normalizeOptionalString(
                $data['educational_attainment'] ?? null,
            ),
            'number_of_children' => $this->normalizeOptionalInt(
                $data['number_of_children'] ?? null,
            ),
            'spouse_name' => $this->normalizeOptionalString($data['spouse_name'] ?? null),
            'spouse_age' => $this->normalizeOptionalInt($data['spouse_age'] ?? null),
            'spouse_cell_no' => $this->normalizeOptionalString(
                $data['spouse_cell_no'] ?? null,
            ),
            'employment_type' => $this->normalizeOptionalString($data['employment_type'] ?? null),
            'employer_business_name' => $this->normalizeOptionalString(
                $data['employer_business_name'] ?? null,
            ),
            'employer_business_address' => $this->normalizeOptionalString(
                $data['employer_business_address'] ?? null,
            ),
            'telephone_no' => $this->normalizeOptionalString($data['telephone_no'] ?? null),
            'current_position' => $this->normalizeOptionalString(
                $data['current_position'] ?? null,
            ),
            'nature_of_business' => $this->normalizeOptionalString(
                $data['nature_of_business'] ?? null,
            ),
            'years_in_work_business' => $this->normalizeOptionalString(
                $data['years_in_work_business'] ?? null,
            ),
            'gross_monthly_income' => $this->normalizeDecimal(
                $data['gross_monthly_income'] ?? null,
            ),
            'payday' => $this->normalizeOptionalString($data['payday'] ?? null),
        ]);
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

        return $person->toArray();
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
     *     availment_status: string|null,
     *     submitted_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function serializeLoanRequest(LoanRequest $loanRequest): array
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
        $isDraft = $status === LoanRequestStatus::Draft->value;

        $typecode = $this->normalizeDraftString($loanRequest->typecode, $isDraft);
        $loanPurpose = $this->normalizeDraftString($loanRequest->loan_purpose, $isDraft);
        $availmentStatus = $this->normalizeDraftString($loanRequest->availment_status, $isDraft);
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
            'status' => $status,
            'typecode' => $typecode,
            'loan_type_label_snapshot' => $loanTypeLabel,
            'requested_amount' => $requestedAmount,
            'requested_term' => $requestedTerm,
            'loan_purpose' => $loanPurpose,
            'availment_status' => $availmentStatus,
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

        if (! array_key_exists('payday', $person)) {
            $person['payday'] = null;
        }

        $person['payday'] = $this->normalizePaydayValue(
            $person['payday'] ?? null,
        );

        return $person;
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

        if ($upper === 'RENTAL') {
            return 'RENT';
        }

        if ($upper === 'OWNED' || $upper === 'RENT') {
            return $upper;
        }

        return $upper;
    }

    private function normalizeCivilStatusValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $upper = strtoupper($trimmed);

        $resolved = match ($upper) {
            'SINGLE' => 'Single',
            'MARRIED' => 'Married',
            'SEPARATED' => 'Separated',
            'WIDOWED' => 'Widowed',
            'ANNULLED' => null,
            default => $trimmed,
        };

        if ($resolved === null) {
            return null;
        }

        return in_array($resolved, self::CIVIL_STATUS_OPTIONS, true)
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

        if (in_array($trimmed, self::PAYDAY_OPTIONS, true)) {
            return $trimmed;
        }

        $upper = strtoupper($trimmed);
        $compact = preg_replace('/[^0-9A-Z]/', '', $upper) ?? '';

        if ($upper === 'WEEKLY') {
            return 'Weekly';
        }

        if ($upper === 'MONTHLY') {
            return 'Monthly';
        }

        if ($compact === 'BIWEEKLY') {
            return 'Bi-Weekly';
        }

        if ($compact === '15') {
            return '15th';
        }

        if ($compact === '30') {
            return '30th';
        }

        if (str_contains($upper, '15') && str_contains($upper, '30')) {
            return '15th & 30th';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildApplicantSnapshot(AppUser $user): array
    {
        $wmaster = $user->wmaster;
        $profile = $user->memberApplicationProfile;
        $hasDependentColumn = Schema::hasColumn('wmaster', 'dependent');

        $nameParts = $wmaster?->resolvedNameParts() ?? [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
        ];
        $firstName = $this->normalizeOptionalString($nameParts['first_name']);
        $middleName = $this->normalizeOptionalString($nameParts['middle_name']);
        $lastName = $this->normalizeOptionalString($nameParts['last_name']);
        $birthplace = $this->normalizeOptionalString($wmaster?->birthplace);
        $address = $wmaster !== null
            ? $this->normalizeOptionalString($wmaster->displayAddress())
            : null;
        $spouseName = $this->normalizeOptionalString($wmaster?->spouse);
        $numberOfChildren = null;

        if ($hasDependentColumn && $wmaster?->dependent !== null) {
            $numberOfChildren = (string) $wmaster->dependent;
        } elseif ($profile?->number_of_children !== null) {
            $numberOfChildren = (string) $profile->number_of_children;
        }

        return [
            'first_name' => $firstName !== null ? DisplayText::normalize($firstName) : null,
            'middle_name' => $middleName !== null ? DisplayText::normalize($middleName) : null,
            'last_name' => $lastName !== null ? DisplayText::normalize($lastName) : null,
            'nickname' => $profile?->nickname,
            'birthdate' => $wmaster?->birthday?->toDateString(),
            'birthplace' => $birthplace !== null
                ? DisplayText::normalize($birthplace)
                : $profile?->birthplace,
            'address' => $address !== null ? DisplayText::normalize($address) : null,
            'length_of_stay' => $profile?->length_of_stay,
            'housing_status' => $wmaster?->restype !== null
                ? (string) $wmaster->restype
                : null,
            'cell_no' => $user->phoneno,
            'civil_status' => $wmaster?->civilstat,
            'educational_attainment' => $profile?->educational_attainment,
            'number_of_children' => $numberOfChildren,
            'spouse_name' => $spouseName !== null
                ? DisplayText::normalize($spouseName)
                : $profile?->spouse_name,
            'spouse_age' => $profile?->spouse_age,
            'spouse_cell_no' => $profile?->spouse_cell_no,
            'employment_type' => $profile?->employment_type,
            'employer_business_name' => $profile?->employer_business_name,
            'employer_business_address' => $profile?->employer_business_address,
            'telephone_no' => $profile?->telephone_no,
            'current_position' => $profile?->current_position,
            'nature_of_business' => $profile?->nature_of_business,
            'years_in_work_business' => $profile?->years_in_work_business,
            'gross_monthly_income' => $profile?->gross_monthly_income !== null
                ? (string) $profile->gross_monthly_income
                : null,
            'payday' => $profile?->payday,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function buildApplicantReadOnlyMap(AppUser $user): array
    {
        $wmaster = $user->wmaster;
        $hasDependentColumn = Schema::hasColumn('wmaster', 'dependent');
        $nameParts = $wmaster?->resolvedNameParts() ?? [
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
        ];
        $hasName = $nameParts['first_name'] !== ''
            || $nameParts['middle_name'] !== ''
            || $nameParts['last_name'] !== '';
        $hasAddress = $wmaster?->hasStructuredAddressParts() === true
            || $this->hasValue($wmaster?->address);

        return [
            'first_name' => $hasName,
            'middle_name' => $nameParts['middle_name'] !== '',
            'last_name' => $hasName,
            'birthdate' => $this->hasValue($wmaster?->birthday),
            'birthplace' => $this->hasValue($wmaster?->birthplace),
            'address' => $hasAddress,
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
}
