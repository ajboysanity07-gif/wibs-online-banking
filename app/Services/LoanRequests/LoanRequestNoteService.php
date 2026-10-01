<?php

namespace App\Services\LoanRequests;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestNote;
use App\Support\SchemaCapabilities;
use Illuminate\Support\Facades\DB;

class LoanRequestNoteService
{
    public function __construct(private SchemaCapabilities $schemaCapabilities) {}

    /**
     * `available` is false until the notes table is deployed. Newest first.
     *
     * @return array{available: bool, items: list<array<string, mixed>>}
     */
    public function serialize(LoanRequest $loanRequest): array
    {
        if (! $this->schemaCapabilities->hasTable('loan_request_notes')) {
            return ['available' => false, 'items' => []];
        }

        $items = $loanRequest->notes()
            ->with('author.adminProfile')
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (LoanRequestNote $note): array => [
                'id' => $note->id,
                'author' => $note->author?->resolvedDisplayName(),
                'body' => $note->body,
                'created_at' => $note->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return ['available' => true, 'items' => $items];
    }

    /**
     * The audit entry records that a note was added, not its text, so the
     * trail never carries staff-only content.
     */
    public function add(LoanRequest $loanRequest, AppUser $actor, string $body): void
    {
        DB::transaction(function () use ($loanRequest, $actor, $body): void {
            $note = LoanRequestNote::query()->create([
                'loan_request_id' => $loanRequest->id,
                'user_id' => $actor->user_id,
                'body' => $body,
            ]);

            $status = $loanRequest->status?->value ?? (string) $loanRequest->status;

            LoanRequestChange::query()->create([
                'loan_request_id' => $loanRequest->id,
                'changed_by' => $actor->user_id,
                'action' => LoanRequestChange::ACTION_INTERNAL_NOTE_ADDED,
                'from_status' => $status,
                'to_status' => $status,
                'reason' => 'Internal note added',
                'before_json' => [],
                'after_json' => [],
                'changed_fields_json' => [],
                'metadata_json' => ['note_id' => $note->id],
            ]);
        });
    }
}
