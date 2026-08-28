<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy free-text release-side bank/ATM columns that are retired once
     * their values are back-filled into member_payment_accounts rows.
     *
     * @var list<string>
     */
    private const LEGACY_PAYOUT_COLUMNS = [
        'payout_bank_name',
        'payout_account_name',
        'payout_account_number',
        'payout_account_type',
        'payout_atm_number',
        'payout_bank_branch',
        'payout_atm_holder_name',
    ];

    /**
     * Legacy free-text repayment-side bank/ATM columns, retired the same way.
     *
     * @var list<string>
     */
    private const LEGACY_PAYMENT_COLUMNS = [
        'payment_bank_name',
        'payment_account_name',
        'payment_account_number',
        'payment_account_type',
        'payment_atm_number',
        'payment_bank_branch',
        'payment_atm_holder_name',
    ];

    /**
     * @var list<string>
     */
    private const LEGACY_KEYS = [
        ...self::LEGACY_PAYOUT_COLUMNS,
        ...self::LEGACY_PAYMENT_COLUMNS,
    ];

    public function up(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->foreignId('release_saved_account_id')
                ->nullable()
                ->after('payout_atm_holder_name')
                ->constrained('member_payment_accounts')
                ->nullOnDelete();
            $table->foreignId('payment_saved_account_id')
                ->nullable()
                ->after('release_saved_account_id')
                ->constrained('member_payment_accounts')
                ->nullOnDelete();
        });

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->json('account_snapshot_json')
                ->nullable()
                ->after('kind_of_loan');
        });

        $this->backfillSavedAccounts();
        $this->backfillLoanAccountSnapshots();

        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([...self::LEGACY_PAYOUT_COLUMNS, ...self::LEGACY_PAYMENT_COLUMNS]);
        });

        DB::table('loan_request_data_entries')
            ->whereIn('field_key', self::LEGACY_KEYS)
            ->delete();
    }

    public function down(): void
    {
        $this->restoreLegacyProfileColumns();

        $this->restoreLegacyLoanDataEntries();

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropColumn('account_snapshot_json');
        });

        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('release_saved_account_id');
            $table->dropConstrainedForeignId('payment_saved_account_id');
        });
    }

    private function backfillSavedAccounts(): void
    {
        DB::table('member_application_profiles')
            ->select('id', 'release_method', 'payment_option', 'payout_bank_name', 'payout_account_name', 'payout_account_number', 'payout_account_type', 'payout_atm_number', 'payout_bank_branch', 'payout_atm_holder_name', 'payment_bank_name', 'payment_account_name', 'payment_account_number', 'payment_account_type', 'payment_atm_number', 'payment_bank_branch', 'payment_atm_holder_name')
            ->orderBy('id')
            ->chunkById(200, function ($profiles): void {
                foreach ($profiles as $profile) {
                    $this->backfillSavedAccountsForProfile($profile);
                }
            });
    }

    private function backfillSavedAccountsForProfile(object $profile): void
    {
        $releaseData = $this->accountDataFrom($profile, 'payout_');
        $paymentData = $this->accountDataFrom($profile, 'payment_');
        $updates = [];

        if ($releaseData !== []) {
            $releaseData['label'] = 'Imported payout account';

            if ($this->accountsMatch($releaseData, $paymentData)) {
                $releaseId = $this->insertAccount($profile->id, $releaseData);
                $updates['release_saved_account_id'] = $releaseId;
                $updates['payment_saved_account_id'] = $releaseId;
            } else {
                $updates['release_saved_account_id'] = $this->insertAccount($profile->id, $releaseData);

                if ($paymentData !== []) {
                    $paymentData['label'] = 'Imported repayment account';
                    $updates['payment_saved_account_id'] = $this->insertAccount($profile->id, $paymentData);
                }
            }
        } elseif ($paymentData !== []) {
            $paymentData['label'] = 'Imported repayment account';
            $updates['payment_saved_account_id'] = $this->insertAccount($profile->id, $paymentData);
        }

        if ($updates !== []) {
            DB::table('member_application_profiles')
                ->where('id', $profile->id)
                ->update($updates);
        }
    }

    /**
     * @return array<string, string>
     */
    private function accountDataFrom(object $profile, string $prefix): array
    {
        $mapping = [
            'bank_name' => "{$prefix}bank_name",
            'account_name' => "{$prefix}account_name",
            'account_number' => "{$prefix}account_number",
            'account_type' => "{$prefix}account_type",
            'atm_number' => "{$prefix}atm_number",
            'bank_branch' => "{$prefix}bank_branch",
            'atm_holder_name' => "{$prefix}atm_holder_name",
        ];
        $data = [];

        foreach ($mapping as $accountField => $column) {
            $value = trim((string) ($profile->{$column} ?? ''));

            if ($value !== '') {
                $data[$accountField] = $value;
            }
        }

        return $data;
    }

    /**
     * When a profile's typed payout and payment data are identical (same
     * bank, account, ATM card), serve both sides from a single saved account
     * instead of duplicating rows.
     *
     * @param  array<string, string>  $releaseData
     * @param  array<string, string>  $paymentData
     */
    private function accountsMatch(array $releaseData, array $paymentData): bool
    {
        return $paymentData !== [] && $releaseData === $paymentData;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function insertAccount(int $profileId, array $data): int
    {
        return DB::table('member_payment_accounts')->insertGetId([
            ...$data,
            'member_application_profile_id' => $profileId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function backfillLoanAccountSnapshots(): void
    {
        DB::table('loan_requests')
            ->select('id', 'user_id')
            ->orderBy('id')
            ->chunkById(200, function ($loanRequests): void {
                foreach ($loanRequests as $loanRequest) {
                    $this->backfillLoanAccountSnapshot($loanRequest);
                }
            });
    }

    private function backfillLoanAccountSnapshot(object $loanRequest): void
    {
        $entries = DB::table('loan_request_data_entries')
            ->where('loan_request_id', $loanRequest->id)
            ->whereIn('field_key', [
                ...self::LEGACY_KEYS,
                'release_saved_account_id',
                'payment_saved_account_id',
            ])
            ->pluck('value_json', 'field_key');

        $profileId = DB::table('member_application_profiles')
            ->where('user_id', $loanRequest->user_id)
            ->value('id');

        $release = $this->resolveReleaseSnapshot($entries, $profileId);
        $payment = $this->resolvePaymentSnapshot($entries, $profileId);
        $snapshot = [];

        if ($release !== null) {
            $snapshot['release'] = $release;
        }

        if ($payment !== null) {
            $snapshot['payment'] = $payment;
        }

        if ($snapshot === []) {
            return;
        }

        DB::table('loan_requests')
            ->where('id', $loanRequest->id)
            ->update(['account_snapshot_json' => json_encode($snapshot)]);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $entries
     * @return array<string, string|null>|null
     */
    private function resolveReleaseSnapshot($entries, ?int $profileId): ?array
    {
        if ($profileId !== null) {
            $account = $this->savedAccountFor($profileId, $this->entryValue($entries, 'release_saved_account_id'));

            if ($account !== null) {
                return $this->snapshotFromAccount($account);
            }
        }

        return $this->snapshotFromLegacy($entries, 'payout_');
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $entries
     * @return array<string, string|null>|null
     */
    private function resolvePaymentSnapshot($entries, ?int $profileId): ?array
    {
        if ($profileId !== null) {
            $account = $this->savedAccountFor($profileId, $this->entryValue($entries, 'payment_saved_account_id'));

            if ($account !== null) {
                return $this->snapshotFromAccount($account);
            }
        }

        return $this->snapshotFromLegacy($entries, 'payment_');
    }

    private function savedAccountFor(int $profileId, mixed $accountId): ?object
    {
        $accountId = trim((string) ($accountId ?? ''));

        if ($accountId === '') {
            return null;
        }

        return DB::table('member_payment_accounts')
            ->where('member_application_profile_id', $profileId)
            ->where('id', (int) $accountId)
            ->first();
    }

    private function snapshotFromAccount(object $account): array
    {
        return [
            'bank_name' => $this->nullableString($account->bank_name),
            'account_name' => $this->nullableString($account->account_name),
            'account_number' => $this->nullableString($account->account_number),
            'account_type' => $this->nullableString($account->account_type),
            'atm_number' => $this->nullableString($account->atm_number),
            'bank_branch' => $this->nullableString($account->bank_branch),
            'atm_holder_name' => $this->nullableString($account->atm_holder_name),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $entries
     * @return array<string, string|null>|null
     */
    private function snapshotFromLegacy($entries, string $prefix): ?array
    {
        $snapshot = [];

        foreach ([
            'bank_name' => "{$prefix}bank_name",
            'account_name' => "{$prefix}account_name",
            'account_number' => "{$prefix}account_number",
            'account_type' => "{$prefix}account_type",
            'atm_number' => "{$prefix}atm_number",
            'bank_branch' => "{$prefix}bank_branch",
            'atm_holder_name' => "{$prefix}atm_holder_name",
        ] as $accountField => $legacyKey) {
            $snapshot[$accountField] = $this->nullableString($this->entryValue($entries, $legacyKey));
        }

        if (array_filter($snapshot, fn ($value) => $value !== null) === []) {
            return null;
        }

        return $snapshot;
    }

    private function entryValue($entries, string $key): mixed
    {
        $raw = $entries[$key] ?? null;

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode(is_string($raw) ? $raw : json_encode($raw), true);

        return is_array($decoded) ? ($decoded['value'] ?? null) : $raw;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function restoreLegacyProfileColumns(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->string('payout_bank_name')->nullable()->after('payday');
            $table->string('payout_account_name')->nullable()->after('payout_bank_name');
            $table->string('payout_account_number')->nullable()->after('payout_account_name');
            $table->string('payout_account_type')->nullable()->after('payout_account_number');
            $table->string('payout_atm_number')->nullable()->after('release_method');
            $table->string('payout_bank_branch')->nullable()->after('payout_atm_number');
            $table->string('payout_atm_holder_name')->nullable()->after('payout_bank_branch');
            $table->string('payment_bank_name')->nullable()->after('payment_option');
            $table->string('payment_account_name')->nullable()->after('payment_bank_name');
            $table->string('payment_account_number')->nullable()->after('payment_account_name');
            $table->string('payment_account_type')->nullable()->after('payment_account_number');
            $table->string('payment_atm_number')->nullable()->after('payment_account_type');
            $table->string('payment_bank_branch')->nullable()->after('payment_atm_number');
            $table->string('payment_atm_holder_name')->nullable()->after('payment_bank_branch');
        });
    }

    private function restoreLegacyLoanDataEntries(): void
    {
        DB::table('loan_requests')
            ->whereNotNull('account_snapshot_json')
            ->select('id', 'account_snapshot_json')
            ->orderBy('id')
            ->chunkById(200, function ($loanRequests): void {
                foreach ($loanRequests as $loanRequest) {
                    $snapshot = json_decode((string) $loanRequest->account_snapshot_json, true);

                    if (! is_array($snapshot)) {
                        continue;
                    }

                    $entries = [];

                    foreach ([
                        'release' => 'payout_',
                        'payment' => 'payment_',
                    ] as $side => $prefix) {
                        foreach (($snapshot[$side] ?? []) as $field => $value) {
                            if ($value === null) {
                                continue;
                            }

                            $fieldMappings = [
                                'bank_name' => 'bank_name',
                                'account_name' => 'account_name',
                                'account_number' => 'account_number',
                                'account_type' => 'account_type',
                                'atm_number' => 'atm_number',
                                'bank_branch' => 'bank_branch',
                                'atm_holder_name' => 'atm_holder_name',
                            ];

                            $legacyKey = $fieldMappings[$field] ?? null;

                            if ($legacyKey === null) {
                                continue;
                            }

                            $entries[] = [
                                'loan_request_id' => $loanRequest->id,
                                'section_key' => 'banking',
                                'field_key' => "{$prefix}{$legacyKey}",
                                'owner_type' => 'member',
                                'value_json' => json_encode(['value' => $value]),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    if ($entries !== []) {
                        DB::table('loan_request_data_entries')->insert($entries);
                    }
                }
            });
    }
};
