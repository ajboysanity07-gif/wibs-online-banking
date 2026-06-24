<?php

namespace App\Exports;

use App\Models\AppUser;
use App\Models\DocumentAccessLog;
use App\Models\LoanRequestChange;
use App\Models\UserRoleChange;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuditLogExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private AppUser $actor) {}

    public function collection(): Collection
    {
        $roleChanges = UserRoleChange::query()
            ->with(['targetUser', 'actor'])
            ->latest()
            ->get()
            ->map(static fn (UserRoleChange $c): array => [
                'type' => 'role_change',
                'action' => $c->action,
                'actor' => $c->actor?->name ?? '',
                'target' => $c->targetUser?->name ?? '',
                'detail' => $c->role_name ?? '',
                'reason' => $c->reason ?? '',
                'occurred_at' => $c->created_at?->toDateTimeString() ?? '',
            ]);

        $loanChanges = LoanRequestChange::query()
            ->with(['loanRequest', 'changedBy'])
            ->latest()
            ->get()
            ->map(static fn (LoanRequestChange $c): array => [
                'type' => 'loan_change',
                'action' => $c->action,
                'actor' => $c->changedBy?->name ?? '',
                'target' => $c->loanRequest?->reference ?? '',
                'detail' => sprintf('%s → %s', $c->from_status ?? '', $c->to_status ?? ''),
                'reason' => $c->reason ?? '',
                'occurred_at' => $c->created_at?->toDateTimeString() ?? '',
            ]);

        $docLogs = DocumentAccessLog::query()
            ->with(['user', 'loanRequest'])
            ->orderBy('accessed_at', 'desc')
            ->get()
            ->map(static fn (DocumentAccessLog $d): array => [
                'type' => 'document_access',
                'action' => $d->action,
                'actor' => $d->user?->name ?? '',
                'target' => $d->loanRequest?->reference ?? '',
                'detail' => $d->document_key,
                'reason' => '',
                'occurred_at' => $d->accessed_at?->toDateTimeString() ?? '',
            ]);

        return $roleChanges
            ->merge($loanChanges)
            ->merge($docLogs)
            ->sortByDesc('occurred_at')
            ->values();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Type', 'Action', 'Actor', 'Target', 'Detail', 'Reason', 'Occurred At'];
    }

    /** @param array<string, string> $row */
    public function map($row): array
    {
        return [
            $row['type'],
            $row['action'],
            $row['actor'],
            $row['target'],
            $row['detail'],
            $row['reason'],
            $row['occurred_at'],
        ];
    }
}
