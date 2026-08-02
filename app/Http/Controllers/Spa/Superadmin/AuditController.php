<?php

namespace App\Http\Controllers\Spa\Superadmin;

use App\Exports\AuditLogExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\AuditLogIndexRequest;
use App\Models\DocumentAccessLog;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\UserRoleChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditController extends Controller
{
    public function index(AuditLogIndexRequest $request): JsonResponse
    {
        $type = $request->validated('type', 'all');
        $userId = $request->validated('user_id');
        $loanReference = $request->validated('loan_reference');
        $dateFrom = $request->validated('date_from');
        $dateTo = $request->validated('date_to');
        $page = (int) $request->validated('page', 1);
        $perPage = 50;

        $loanRequestId = null;
        if ($loanReference !== null) {
            $prefix = LoanRequest::REFERENCE_PREFIX.'-';
            if (str_starts_with($loanReference, $prefix)) {
                $numericId = (int) ltrim(substr($loanReference, strlen($prefix)), '0');
                if ($numericId > 0) {
                    $loanRequestId = LoanRequest::query()->where('id', $numericId)->value('id');
                }
            }
        }

        $items = [];
        $total = 0;
        $mergeWindow = $page * $perPage;

        if (in_array($type, ['role_changes', 'all'], true)) {
            $roleQuery = UserRoleChange::query()
                ->with(['targetUser', 'actor'])
                ->when($userId, fn ($q) => $q->where('target_user_id', $userId))
                ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo.' 23:59:59'));

            if ($type === 'role_changes') {
                $paginator = $roleQuery->latest()->paginate($perPage, ['*'], 'page', $page);

                return response()->json([
                    'ok' => true,
                    'data' => [
                        'items' => $this->formatRoleChanges($paginator->getCollection()),
                        'meta' => $this->buildMeta($paginator, $type, $request),
                    ],
                ]);
            }

            $total += $roleQuery->count();
            $items = array_merge($items, $this->formatRoleChanges($roleQuery->latest()->limit($mergeWindow)->get())->toArray());
        }

        if (in_array($type, ['loan_changes', 'all'], true)) {
            $loanQuery = LoanRequestChange::query()
                ->with(['loanRequest', 'changedBy'])
                ->when($userId, fn ($q) => $q->where('changed_by', $userId))
                ->when($loanRequestId, fn ($q) => $q->where('loan_request_id', $loanRequestId))
                ->when($loanReference !== null && $loanRequestId === null, fn ($q) => $q->whereRaw('0=1'))
                ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo.' 23:59:59'));

            if ($type === 'loan_changes') {
                $paginator = $loanQuery->latest()->paginate($perPage, ['*'], 'page', $page);

                return response()->json([
                    'ok' => true,
                    'data' => [
                        'items' => $this->formatLoanChanges($paginator->getCollection()),
                        'meta' => $this->buildMeta($paginator, $type, $request),
                    ],
                ]);
            }

            $total += $loanQuery->count();
            $items = array_merge($items, $this->formatLoanChanges($loanQuery->latest()->limit($mergeWindow)->get())->toArray());
        }

        if (in_array($type, ['document_access', 'all'], true)) {
            $docQuery = DocumentAccessLog::query()
                ->with(['user', 'loanRequest'])
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when($loanRequestId, fn ($q) => $q->where('loan_request_id', $loanRequestId))
                ->when($loanReference !== null && $loanRequestId === null, fn ($q) => $q->whereRaw('0=1'))
                ->when($dateFrom, fn ($q) => $q->where('accessed_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->where('accessed_at', '<=', $dateTo.' 23:59:59'));

            if ($type === 'document_access') {
                $paginator = $docQuery->orderBy('accessed_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

                return response()->json([
                    'ok' => true,
                    'data' => [
                        'items' => $this->formatDocumentAccess($paginator->getCollection()),
                        'meta' => $this->buildMeta($paginator, $type, $request),
                    ],
                ]);
            }

            $total += $docQuery->count();
            $items = array_merge($items, $this->formatDocumentAccess($docQuery->orderBy('accessed_at', 'desc')->limit($mergeWindow)->get())->toArray());
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['occurred_at'], (string) $a['occurred_at']));

        $offset = ($page - 1) * $perPage;
        $pagedItems = array_slice($items, $offset, $perPage);
        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => array_values($pagedItems),
                'meta' => [
                    'type' => $type,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                ],
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->isSuperadmin(), 403);

        return Excel::download(
            new AuditLogExport($request->user()),
            'audit-log-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, UserRoleChange>  $items
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function formatRoleChanges($items)
    {
        return $items->map(static fn (UserRoleChange $c): array => [
            'type' => 'role_change',
            'id' => $c->id,
            'action' => $c->action,
            'actor' => $c->actor?->name,
            'target_user' => $c->targetUser?->name,
            'role_name' => $c->role_name,
            'reason' => $c->reason,
            'occurred_at' => $c->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LoanRequestChange>  $items
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function formatLoanChanges($items)
    {
        return $items->map(static fn (LoanRequestChange $c): array => [
            'type' => 'loan_change',
            'id' => $c->id,
            'action' => $c->action,
            'actor' => $c->changedBy?->name,
            'loan_reference' => $c->loanRequest?->reference,
            'from_status' => $c->from_status,
            'to_status' => $c->to_status,
            'reason' => $c->reason,
            'occurred_at' => $c->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DocumentAccessLog>  $items
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function formatDocumentAccess($items)
    {
        return $items->map(static fn (DocumentAccessLog $d): array => [
            'type' => 'document_access',
            'id' => $d->id,
            'action' => $d->action,
            'actor' => $d->user?->name,
            'loan_reference' => $d->loanRequest?->reference,
            'document_key' => $d->document_key,
            'occurred_at' => $d->accessed_at?->toIso8601String(),
        ]);
    }

    private function buildMeta(
        \Illuminate\Pagination\LengthAwarePaginator $paginator,
        string $type,
        Request $request,
    ): array {
        return [
            'type' => $type,
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
