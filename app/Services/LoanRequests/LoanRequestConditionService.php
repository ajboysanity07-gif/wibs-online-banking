<?php

namespace App\Services\LoanRequests;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestCondition;
use App\Support\SchemaCapabilities;
use Illuminate\Support\Facades\DB;

class LoanRequestConditionService
{
    public function __construct(private SchemaCapabilities $schemaCapabilities) {}

    /**
     * `available` is false until the conditions table is deployed, so the UI
     * can show the card as pending and never gate on missing data.
     *
     * @return array{available: bool, items: list<array<string, mixed>>}
     */
    public function serialize(LoanRequest $loanRequest): array
    {
        if (! $this->schemaCapabilities->hasTable('loan_request_conditions')) {
            return ['available' => false, 'items' => []];
        }

        $rows = $loanRequest->conditions()
            ->with('verifiedBy.adminProfile')
            ->get()
            ->keyBy('condition_key');

        $items = [];

        foreach (LoanRequestCondition::LABELS as $key => $label) {
            $row = $rows->get($key);

            $items[] = [
                'key' => $key,
                'label' => $label,
                'verified' => $row !== null,
                'verified_by' => $row?->verifiedBy?->resolvedDisplayName(),
                'verified_at' => $row?->verified_at?->toDateTimeString(),
            ];
        }

        return ['available' => true, 'items' => $items];
    }

    /**
     * Idempotent: re-sending the current state changes nothing and writes no
     * audit entry.
     */
    public function set(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $key,
        bool $verified,
    ): void {
        DB::transaction(function () use ($loanRequest, $actor, $key, $verified): void {
            $existing = LoanRequestCondition::query()
                ->where('loan_request_id', $loanRequest->id)
                ->where('condition_key', $key)
                ->lockForUpdate()
                ->first();

            if (($existing !== null) === $verified) {
                return;
            }

            if ($verified) {
                LoanRequestCondition::query()->create([
                    'loan_request_id' => $loanRequest->id,
                    'condition_key' => $key,
                    'verified_by' => $actor->user_id,
                    'verified_at' => now(),
                ]);
            } else {
                $existing?->delete();
            }

            $label = LoanRequestCondition::LABELS[$key];
            $status = $loanRequest->status?->value ?? (string) $loanRequest->status;

            LoanRequestChange::query()->create([
                'loan_request_id' => $loanRequest->id,
                'changed_by' => $actor->user_id,
                'action' => $verified
                    ? LoanRequestChange::ACTION_CONDITION_VERIFIED
                    : LoanRequestChange::ACTION_CONDITION_UNVERIFIED,
                'from_status' => $status,
                'to_status' => $status,
                'reason' => ($verified ? 'Verified: ' : 'Unverified: ').$label,
                'before_json' => ['verified' => ! $verified],
                'after_json' => ['verified' => $verified],
                'changed_fields_json' => ['condition_'.$key],
                'metadata_json' => ['condition' => $label],
            ]);
        });
    }
}
