<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\Wlntype;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanRequestService
{
    /**
     * @return array{
     *     loanTypes: list<array{typecode: string, label: string}>,
     *     applicant: array<string, mixed>,
     *     applicantReadOnly: array<string, bool>,
     *     member: array{name: string, acctno: string|null}
     * }
     */
    public function getFormData(AppUser $user): array
    {
        $user->loadMissing('memberApplicationProfile');

        if (Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');
        }

        $applicant = $this->buildApplicantSnapshot($user);
        $applicantReadOnly = $this->buildApplicantReadOnlyMap($user);
        $memberName = $this->resolveMemberName($user);

        return [
            'loanTypes' => $this->getLoanTypes()->values()->all(),
            'applicant' => $applicant,
            'applicantReadOnly' => $applicantReadOnly,
            'member' => [
                'name' => $memberName,
                'acctno' => $user->acctno,
            ],
        ];
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
    public function create(AppUser $user, array $payload): LoanRequest
    {
        return DB::transaction(function () use ($user, $payload): LoanRequest {
            $typecode = (string) $payload['typecode'];
            $loanTypeLabel = $this->resolveLoanTypeLabel($typecode);

            $loanRequest = LoanRequest::create([
                'user_id' => $user->user_id,
                'acctno' => (string) ($user->acctno ?? ''),
                'typecode' => $typecode,
                'loan_type_label_snapshot' => $loanTypeLabel,
                'requested_amount' => $this->normalizeDecimal($payload['requested_amount']),
                'requested_term' => (int) $payload['requested_term'],
                'loan_purpose' => (string) $payload['loan_purpose'],
                'availment_status' => (string) $payload['availment_status'],
                'status' => LoanRequestStatus::Submitted,
                'submitted_at' => now(),
            ]);

            $this->createPersonSnapshot(
                $loanRequest,
                LoanRequestPersonRole::Applicant,
                $payload['applicant'],
            );
            $this->createPersonSnapshot(
                $loanRequest,
                LoanRequestPersonRole::CoMakerOne,
                $payload['co_maker_1'],
            );
            $this->createPersonSnapshot(
                $loanRequest,
                LoanRequestPersonRole::CoMakerTwo,
                $payload['co_maker_2'],
            );

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
     * @return array<string, mixed>
     */
    private function buildApplicantSnapshot(AppUser $user): array
    {
        $wmaster = $user->wmaster;
        $profile = $user->memberApplicationProfile;

        $structuredName = $this->resolveStructuredName(
            $wmaster?->fname,
            $wmaster?->mname,
            $wmaster?->lname,
            $wmaster?->bname,
        );

        return [
            'first_name' => $structuredName['first_name'],
            'middle_name' => $structuredName['middle_name'],
            'last_name' => $structuredName['last_name'],
            'nickname' => $profile?->nickname,
            'birthdate' => $wmaster?->birthday?->toDateString(),
            'birthplace' => $profile?->birthplace,
            'address' => $wmaster?->address,
            'length_of_stay' => $profile?->length_of_stay,
            'housing_status' => $wmaster?->restype !== null
                ? (string) $wmaster->restype
                : null,
            'cell_no' => $user->phoneno,
            'civil_status' => $wmaster?->civilstat,
            'educational_attainment' => $profile?->educational_attainment,
            'number_of_children' => $wmaster?->dependent !== null
                ? (string) $wmaster->dependent
                : null,
            'spouse_name' => $wmaster?->spouse,
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

        $hasStructuredName = $this->hasStructuredName(
            $wmaster?->fname,
            $wmaster?->mname,
            $wmaster?->lname,
            $wmaster?->bname,
        );

        return [
            'first_name' => $hasStructuredName,
            'middle_name' => $hasStructuredName && $this->hasValue($wmaster?->mname),
            'last_name' => $hasStructuredName,
            'birthdate' => $this->hasValue($wmaster?->birthday),
            'address' => $this->hasValue($wmaster?->address),
            'housing_status' => $this->hasValue($wmaster?->restype),
            'civil_status' => $this->hasValue($wmaster?->civilstat),
            'number_of_children' => $this->hasValue($wmaster?->dependent),
            'spouse_name' => $this->hasValue($wmaster?->spouse),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createPersonSnapshot(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
        array $data,
    ): LoanRequestPerson {
        return $loanRequest->people()->create([
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
     * @return array{first_name: string, middle_name: string, last_name: string}
     */
    private function resolveStructuredName(
        ?string $firstName,
        ?string $middleName,
        ?string $lastName,
        ?string $legacyName,
    ): array {
        $first = trim((string) $firstName);
        $middle = trim((string) $middleName);
        $last = trim((string) $lastName);

        if ($first !== '' || $middle !== '' || $last !== '') {
            return [
                'first_name' => $first,
                'middle_name' => $middle,
                'last_name' => $last,
            ];
        }

        return $this->parseLegacyName($legacyName);
    }

    /**
     * @return array{first_name: string, middle_name: string, last_name: string}
     */
    private function parseLegacyName(?string $legacyName): array
    {
        $value = trim((string) $legacyName);

        if ($value === '') {
            return [
                'first_name' => '',
                'middle_name' => '',
                'last_name' => '',
            ];
        }

        if (str_contains($value, ',')) {
            [$lastPart, $rest] = array_pad(explode(',', $value, 2), 2, '');
            $last = trim($lastPart);
            $rest = trim($rest);
        } else {
            $parts = preg_split('/\s+/', $value) ?: [];
            $last = (string) array_pop($parts);
            $rest = trim(implode(' ', $parts));
        }

        $restParts = $rest !== '' ? preg_split('/\s+/', $rest) ?: [] : [];
        $first = $restParts !== [] ? (string) array_shift($restParts) : '';
        $middle = $restParts !== [] ? trim(implode(' ', $restParts)) : '';

        return [
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
        ];
    }

    private function resolveMemberName(AppUser $user): string
    {
        $name = $user->wmaster?->bname;

        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        return $user->username;
    }

    private function hasStructuredName(
        ?string $firstName,
        ?string $middleName,
        ?string $lastName,
        ?string $legacyName,
    ): bool {
        return $this->hasValue($firstName)
            || $this->hasValue($middleName)
            || $this->hasValue($lastName)
            || $this->hasValue($legacyName);
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
