<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\LoanRequestSignatureLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanRequestSignatureLinkService
{
    public const STATE_PROPOSED = 'proposed';

    public const STATE_LINK_ACTIVE = 'link_active';

    public const STATE_EXPIRED = 'expired';

    public const STATE_SIGNED = 'signed';

    private const DEFAULT_EXPIRATION_HOURS = 72;

    public function __construct(
        private LoanRequestSignatureStorage $signatureStorage,
    ) {}

    /**
     * @return array{
     *     link: LoanRequestSignatureLink,
     *     signing_url: string
     * }
     */
    public function generateForRole(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
    ): array {
        $person = $this->resolveCoMakerPerson($loanRequest, $role);

        if ($this->hasConfirmedSignature($person)) {
            throw ValidationException::withMessages([
                'signature_link' => sprintf(
                    '%s has already signed. Edit the proposed details to request a new signature.',
                    $this->roleLabel($role),
                ),
            ]);
        }

        return DB::transaction(function () use ($loanRequest, $person, $role): array {
            $this->revokePendingLinks($person);

            $token = Str::random(64);
            $link = LoanRequestSignatureLink::query()->create([
                'loan_request_id' => $loanRequest->id,
                'loan_request_person_id' => $person->id,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(self::DEFAULT_EXPIRATION_HOURS),
            ]);

            return [
                'link' => $link->refresh(),
                'signing_url' => route(
                    'loan-requests.sign.co-maker.show',
                    ['token' => $token],
                ),
            ];
        });
    }

    public function revokePendingLinks(LoanRequestPerson $person): void
    {
        LoanRequestSignatureLink::query()
            ->where('loan_request_person_id', $person->id)
            ->whereNull('signed_at')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array{
     *     status: string,
     *     signing: array<string, mixed>|null
     * }
     */
    public function resolvePublicPage(string $token): array
    {
        $link = $this->findByToken($token);

        if ($link === null) {
            return [
                'status' => 'invalid',
                'signing' => null,
            ];
        }

        if (! $this->hasValidRelations($link)) {
            return [
                'status' => 'invalid',
                'signing' => null,
            ];
        }

        if ($link->signed_at !== null) {
            return [
                'status' => 'signed',
                'signing' => $this->serializePublicSigningData($link),
            ];
        }

        if ($link->revoked_at !== null) {
            return [
                'status' => 'revoked',
                'signing' => $this->serializePublicSigningData($link),
            ];
        }

        if ($link->expires_at !== null && $link->expires_at->isPast()) {
            return [
                'status' => 'expired',
                'signing' => $this->serializePublicSigningData($link),
            ];
        }

        return [
            'status' => 'ready',
            'signing' => $this->serializePublicSigningData($link),
        ];
    }

    public function consume(
        string $token,
        string $signatureData,
        ?string $ipAddress,
        ?string $userAgent,
    ): LoanRequestSignatureLink {
        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use (
            $tokenHash,
            $signatureData,
            $ipAddress,
            $userAgent,
        ): LoanRequestSignatureLink {
            $link = LoanRequestSignatureLink::query()
                ->with(['loanRequest', 'loanRequestPerson'])
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($link === null || ! $this->hasValidRelations($link)) {
                throw ValidationException::withMessages([
                    'link' => 'This signing link is invalid.',
                ]);
            }

            if ($link->signed_at !== null) {
                throw ValidationException::withMessages([
                    'link' => 'This signing link was already used.',
                ]);
            }

            if ($link->revoked_at !== null) {
                throw ValidationException::withMessages([
                    'link' => 'This signing link is no longer active.',
                ]);
            }

            if ($link->expires_at !== null && $link->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'link' => 'This signing link has expired.',
                ]);
            }

            $storedSignaturePath = $this->signatureStorage->storeBase64Png(
                $signatureData,
            );

            if ($storedSignaturePath === null) {
                throw ValidationException::withMessages([
                    'signature_data' => 'Please provide a valid PNG signature.',
                ]);
            }

            $person = $link->loanRequestPerson;
            $this->signatureStorage->delete($person->signature_path);

            $person->forceFill([
                'signature_path' => $storedSignaturePath,
            ])->save();

            $link->forceFill([
                'signed_at' => now(),
                'ip_address' => $this->normalizeOptionalString($ipAddress),
                'user_agent' => $this->normalizeOptionalString($userAgent),
            ])->save();

            LoanRequestSignatureLink::query()
                ->where('loan_request_person_id', $person->id)
                ->whereKeyNot($link->id)
                ->whereNull('signed_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            return $link->refresh();
        });
    }

    /**
     * @return array{
     *     coMakerOneSignature: array<string, mixed>,
     *     coMakerTwoSignature: array<string, mixed>
     * }
     */
    public function getSignatureStates(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('people');

        /** @var LoanRequestPerson|null $coMakerOne */
        $coMakerOne = $loanRequest->people->first(
            fn (LoanRequestPerson $person): bool => $person->role === LoanRequestPersonRole::CoMakerOne,
        );
        /** @var LoanRequestPerson|null $coMakerTwo */
        $coMakerTwo = $loanRequest->people->first(
            fn (LoanRequestPerson $person): bool => $person->role === LoanRequestPersonRole::CoMakerTwo,
        );

        return [
            'coMakerOneSignature' => $this->summarizePerson(
                $coMakerOne,
                LoanRequestPersonRole::CoMakerOne,
            ),
            'coMakerTwoSignature' => $this->summarizePerson(
                $coMakerTwo,
                LoanRequestPersonRole::CoMakerTwo,
            ),
        ];
    }

    /**
     * @return array{
     *     role: string,
     *     state: string,
     *     is_confirmed: bool,
     *     has_signature: bool,
     *     has_active_link: bool,
     *     has_expired_link: bool,
     *     loan_request_person_id: int|null,
     *     signed_via: string|null,
     *     expires_at: string|null,
     *     signed_at: string|null,
     *     last_generated_at: string|null
     * }
     */
    public function summarizePerson(
        ?LoanRequestPerson $person,
        LoanRequestPersonRole $role,
    ): array {
        $default = [
            'role' => $role->value,
            'state' => self::STATE_PROPOSED,
            'is_confirmed' => false,
            'has_signature' => false,
            'has_active_link' => false,
            'has_expired_link' => false,
            'loan_request_person_id' => $person?->id,
            'signed_via' => null,
            'expires_at' => null,
            'signed_at' => null,
            'last_generated_at' => null,
        ];

        if ($person === null) {
            return $default;
        }

        $latestGenerated = $person->signatureLinks()->latest('id')->first();
        $latestSigned = $person->signatureLinks()
            ->whereNotNull('signed_at')
            ->latest('signed_at')
            ->first();
        $activeLink = $person->signatureLinks()
            ->whereNull('signed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
        $expiredLink = $person->signatureLinks()
            ->whereNull('signed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now())
            ->latest('expires_at')
            ->first();

        $hasSignaturePath = $this->normalizeOptionalString(
            $person->signature_path,
        ) !== null;
        $isConfirmed = $hasSignaturePath;

        if ($isConfirmed) {
            $signedVia = $latestSigned !== null ? 'remote' : 'in_person';
            $signedAt = $latestSigned?->signed_at?->toDateTimeString()
                ?? $person->updated_at?->toDateTimeString();

            return [
                ...$default,
                'state' => self::STATE_SIGNED,
                'is_confirmed' => true,
                'has_signature' => true,
                'signed_via' => $signedVia,
                'signed_at' => $signedAt,
                'last_generated_at' => $latestSigned?->created_at?->toDateTimeString(),
            ];
        }

        if ($activeLink !== null) {
            return [
                ...$default,
                'state' => self::STATE_LINK_ACTIVE,
                'has_active_link' => true,
                'expires_at' => $activeLink->expires_at?->toDateTimeString(),
                'last_generated_at' => $activeLink->created_at?->toDateTimeString(),
            ];
        }

        if ($expiredLink !== null) {
            return [
                ...$default,
                'state' => self::STATE_EXPIRED,
                'has_expired_link' => true,
                'expires_at' => $expiredLink->expires_at?->toDateTimeString(),
                'last_generated_at' => $expiredLink->created_at?->toDateTimeString(),
            ];
        }

        return [
            ...$default,
            'has_signature' => $hasSignaturePath,
            'last_generated_at' => $latestGenerated?->created_at?->toDateTimeString(),
        ];
    }

    private function findByToken(string $token): ?LoanRequestSignatureLink
    {
        return LoanRequestSignatureLink::query()
            ->with([
                'loanRequest.applicant',
                'loanRequestPerson',
            ])
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    private function hasValidRelations(LoanRequestSignatureLink $link): bool
    {
        if ($link->loanRequest === null || $link->loanRequestPerson === null) {
            return false;
        }

        $role = $link->role instanceof LoanRequestPersonRole
            ? $link->role
            : LoanRequestPersonRole::tryFrom((string) $link->role);

        if (! $role instanceof LoanRequestPersonRole) {
            return false;
        }

        return in_array($role, [
            LoanRequestPersonRole::CoMakerOne,
            LoanRequestPersonRole::CoMakerTwo,
        ], true);
    }

    private function hasConfirmedSignature(LoanRequestPerson $person): bool
    {
        $summary = $this->summarizePerson(
            $person,
            $person->role instanceof LoanRequestPersonRole
                ? $person->role
                : LoanRequestPersonRole::CoMakerOne,
        );

        return $summary['is_confirmed'] === true;
    }

    private function resolveCoMakerPerson(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
    ): LoanRequestPerson {
        if (! in_array($role, [
            LoanRequestPersonRole::CoMakerOne,
            LoanRequestPersonRole::CoMakerTwo,
        ], true)) {
            throw ValidationException::withMessages([
                'role' => 'Only co-maker signature links may be generated.',
            ]);
        }

        $loanRequest->loadMissing('people');

        /** @var LoanRequestPerson|null $person */
        $person = $loanRequest->people->first(
            fn (LoanRequestPerson $item): bool => $item->role === $role,
        );

        if ($person === null) {
            throw ValidationException::withMessages([
                'signature_link' => sprintf(
                    'Please save the proposed details for %s before generating a signature link.',
                    $this->roleLabel($role),
                ),
            ]);
        }

        return $person;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePublicSigningData(
        LoanRequestSignatureLink $link,
    ): array {
        $loanRequest = $link->loanRequest;
        $person = $link->loanRequestPerson;
        $applicant = $loanRequest?->applicant;

        return [
            'borrower_name' => $this->displayName($applicant),
            'loan_type' => $loanRequest?->loan_type_label_snapshot
                ?: $loanRequest?->typecode,
            'requested_amount' => $loanRequest?->requested_amount,
            'requested_term' => $loanRequest?->requested_term,
            'co_maker_name' => $this->displayName($person),
            'contact_number' => $person?->cell_no,
            'address' => $person?->composedAddress(),
            'employment_type' => $person?->employment_type,
            'employer_business_name' => $person?->employer_business_name,
            'employer_business_address' => $person?->composedEmployerBusinessAddress(),
            'current_position' => $person?->current_position,
            'nature_of_business' => $person?->nature_of_business,
            'role_label' => $this->roleLabel(
                $person?->role instanceof LoanRequestPersonRole
                    ? $person->role
                    : LoanRequestPersonRole::CoMakerOne,
            ),
        ];
    }

    private function displayName(?LoanRequestPerson $person): string
    {
        if ($person === null) {
            return '--';
        }

        $parts = array_filter([
            $this->normalizeOptionalString($person->first_name),
            $this->normalizeOptionalString($person->middle_name),
            $this->normalizeOptionalString($person->last_name),
        ]);

        return $parts !== [] ? implode(' ', $parts) : '--';
    }

    private function roleLabel(LoanRequestPersonRole $role): string
    {
        return match ($role) {
            LoanRequestPersonRole::CoMakerOne => 'Co-maker 1',
            LoanRequestPersonRole::CoMakerTwo => 'Co-maker 2',
            default => 'Co-maker',
        };
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
