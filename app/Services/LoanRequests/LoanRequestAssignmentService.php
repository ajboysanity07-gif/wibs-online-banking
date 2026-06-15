<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Support\SchemaCapabilities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LoanRequestAssignmentService
{
    public const CAUSE_STAFF_SUSPENDED = 'staff_suspended';

    public const CAUSE_ROLE_REMOVED = 'loan_officer_role_removed';

    private const CLAIM_CONFLICT_MESSAGE = 'This application has already been assigned to another Loan Officer.';

    private const MISSING_ASSIGNMENT_MESSAGE = 'This application is not currently assigned to a Loan Officer.';

    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    public function claim(LoanRequest $loanRequest, AppUser $actor): LoanRequest
    {
        return DB::transaction(function () use ($loanRequest, $actor): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize('claim', $lockedLoanRequest);
            $this->ensureOperationalAssignmentStatus(
                $lockedLoanRequest,
                'Only pending review, under review, or needs revision requests can be claimed.',
            );

            return $this->claimLockedLoanRequest($lockedLoanRequest, $actor);
        });
    }

    public function assign(
        LoanRequest $loanRequest,
        AppUser $targetOfficer,
        AppUser $actor,
        string $reason,
    ): LoanRequest {
        return DB::transaction(function () use (
            $loanRequest,
            $targetOfficer,
            $actor,
            $reason,
        ): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize(
                'manageAssignment',
                $lockedLoanRequest,
            );
            $this->ensureOperationalAssignmentStatus(
                $lockedLoanRequest,
                'Only pending review, under review, or needs revision requests can be assigned.',
            );

            if ($lockedLoanRequest->assigned_officer_id !== null) {
                throw ValidationException::withMessages([
                    'assignment' => 'This application is already assigned. Use reassignment instead.',
                ]);
            }

            return $this->setOfficerAssignment(
                $lockedLoanRequest,
                $this->lockOfficer($targetOfficer->user_id),
                $actor,
                $this->normalizeReason($reason),
                LoanRequestChange::ACTION_ASSIGNMENT_ASSIGNED,
            );
        });
    }

    public function reassign(
        LoanRequest $loanRequest,
        AppUser $targetOfficer,
        AppUser $actor,
        string $reason,
    ): LoanRequest {
        return DB::transaction(function () use (
            $loanRequest,
            $targetOfficer,
            $actor,
            $reason,
        ): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize(
                'manageAssignment',
                $lockedLoanRequest,
            );
            $this->ensureOperationalAssignmentStatus(
                $lockedLoanRequest,
                'Only pending review, under review, or needs revision requests can be reassigned.',
            );

            if ($lockedLoanRequest->assigned_officer_id === null) {
                throw ValidationException::withMessages([
                    'assignment' => 'This application is not assigned yet. Use assignment instead.',
                ]);
            }

            if ($lockedLoanRequest->assigned_officer_id === $targetOfficer->user_id) {
                return $this->refreshLoanRequest($lockedLoanRequest);
            }

            return $this->setOfficerAssignment(
                $lockedLoanRequest,
                $this->lockOfficer($targetOfficer->user_id),
                $actor,
                $this->normalizeReason($reason),
                LoanRequestChange::ACTION_ASSIGNMENT_REASSIGNED,
            );
        });
    }

    public function returnToQueue(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $reason,
    ): LoanRequest {
        return DB::transaction(function () use (
            $loanRequest,
            $actor,
            $reason,
        ): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize(
                'returnToQueue',
                $lockedLoanRequest,
            );
            $this->ensureOperationalAssignmentStatus(
                $lockedLoanRequest,
                'Only pending review, under review, or needs revision requests can be returned to the queue.',
            );

            if ($lockedLoanRequest->assigned_officer_id === null) {
                throw ValidationException::withMessages([
                    'assignment' => self::MISSING_ASSIGNMENT_MESSAGE,
                ]);
            }

            $before = $this->assignmentSnapshot($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);
            $previousOfficer = $lockedLoanRequest->assignedOfficer;

            $lockedLoanRequest->forceFill([
                'assigned_officer_id' => null,
            ])->save();

            $updatedLoanRequest = $this->refreshLoanRequest($lockedLoanRequest);

            $this->recordAssignmentAudit(
                $updatedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_ASSIGNMENT_RETURNED_TO_QUEUE,
                $this->normalizeReason($reason),
                $fromStatus,
                $fromStatus,
                $before,
                $this->assignmentSnapshot($updatedLoanRequest),
                $previousOfficer,
                null,
                null,
            );

            return $updatedLoanRequest;
        });
    }

    /**
     * @return array<int, int>
     */
    public function unassignUnavailableOfficerRequests(
        AppUser $officer,
        AppUser $actor,
        string $reason,
        string $cause,
    ): array {
        return DB::transaction(function () use (
            $officer,
            $actor,
            $reason,
            $cause,
        ): array {
            $loanRequests = LoanRequest::query()
                ->with('assignedOfficer.adminProfile')
                ->where('assigned_officer_id', $officer->user_id)
                ->whereIn('status', $this->operationalAssignmentStatuses())
                ->lockForUpdate()
                ->get();

            foreach ($loanRequests as $loanRequest) {
                $before = $this->assignmentSnapshot($loanRequest);
                $fromStatus = $this->statusValue($loanRequest);
                $previousOfficer = $loanRequest->assignedOfficer;

                $loanRequest->forceFill([
                    'assigned_officer_id' => null,
                ])->save();

                $updatedLoanRequest = $this->refreshLoanRequest($loanRequest);

                $this->recordAssignmentAudit(
                    $updatedLoanRequest,
                    $actor,
                    LoanRequestChange::ACTION_ASSIGNMENT_UNASSIGNED_STAFF_UNAVAILABLE,
                    $this->normalizeReason($reason),
                    $fromStatus,
                    $fromStatus,
                    $before,
                    $this->assignmentSnapshot($updatedLoanRequest),
                    $previousOfficer,
                    null,
                    $cause,
                );
            }

            return $loanRequests
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        });
    }

    /**
     * @return array<int, array{
     *     user_id:int,
     *     name:string,
     *     display_code:string,
     *     username:string|null,
     *     active_assignment_count:int,
     *     has_workload_warning:bool,
     *     workload_warning_label:string|null
     * }>
     */
    public function eligibleOfficerOptions(?LoanRequest $loanRequest = null): array
    {
        /** @var Collection<int, AppUser> $officers */
        $officers = AppUser::query()
            ->with(['adminProfile', 'staffAccessControl'])
            ->whereHas('roles', function (Builder $query): void {
                $query->where('name', Role::LOAN_OFFICER);
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('staffAccessControl')
                    ->orWhereHas('staffAccessControl', function (
                        Builder $controlQuery,
                    ): void {
                        $controlQuery->where(
                            'status',
                            StaffAccessControl::STATUS_ACTIVE,
                        );
                    });
            })
            ->withCount([
                'assignedLoanRequests as active_assignment_count' => function (
                    Builder $query,
                ): void {
                    $query->whereIn(
                        'status',
                        $this->operationalAssignmentStatuses(),
                    );
                },
            ])
            ->orderBy('username')
            ->get()
            ->filter(function (AppUser $officer) use ($loanRequest): bool {
                if (! $officer->hasRole(Role::LOAN_OFFICER)) {
                    return false;
                }

                if (! $officer->hasActiveStaffAccess()) {
                    return false;
                }

                if (! $loanRequest instanceof LoanRequest) {
                    return true;
                }

                if (
                    $loanRequest->user_id !== null
                    && $loanRequest->user_id === $officer->user_id
                ) {
                    return false;
                }

                $requestAcctno = trim((string) ($loanRequest->acctno ?? ''));
                $officerAcctno = trim((string) ($officer->acctno ?? ''));

                if ($requestAcctno === '' || $officerAcctno === '') {
                    return true;
                }

                return $requestAcctno !== $officerAcctno;
            })
            ->values();

        return $officers
            ->map(fn (AppUser $officer): array => $this->serializeOfficerOption(
                $officer,
            ))
            ->all();
    }

    /**
     * @return array{
     *     assignment_state:string,
     *     can_claim:bool,
     *     can_assign:bool,
     *     can_reassign:bool,
     *     can_return_to_queue:bool
     * }
     */
    public function capabilitiesFor(
        LoanRequest $loanRequest,
        ?AppUser $actor,
    ): array {
        $status = $this->statusValue($loanRequest);
        $isOperationalAssignmentStatus = in_array(
            $status,
            $this->operationalAssignmentStatuses(),
            true,
        );

        if (! $actor instanceof AppUser) {
            return [
                'assignment_state' => $loanRequest->assigned_officer_id === null
                    ? 'unassigned'
                    : 'assigned_to_other',
                'can_claim' => false,
                'can_assign' => false,
                'can_reassign' => false,
                'can_return_to_queue' => false,
            ];
        }

        $canManageAssignment = Gate::forUser($actor)->allows(
            'manageAssignment',
            $loanRequest,
        );

        return [
            'assignment_state' => $this->assignmentStateFor(
                $loanRequest,
                $actor,
            ),
            'can_claim' => Gate::forUser($actor)->allows('claim', $loanRequest)
                && $loanRequest->assigned_officer_id === null,
            'can_assign' => $canManageAssignment
                && $isOperationalAssignmentStatus
                && $loanRequest->assigned_officer_id === null,
            'can_reassign' => $canManageAssignment
                && $isOperationalAssignmentStatus
                && $loanRequest->assigned_officer_id !== null,
            'can_return_to_queue' => Gate::forUser($actor)->allows(
                'returnToQueue',
                $loanRequest,
            ) && $isOperationalAssignmentStatus
                && $loanRequest->assigned_officer_id !== null,
        ];
    }

    /**
     * @return list<string>
     */
    public function operationalAssignmentStatuses(): array
    {
        return [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
            LoanRequestStatus::NeedsRevision->value,
        ];
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    public function assignmentFilterOptionsFor(AppUser $actor): array
    {
        $options = [
            ['value' => 'unassigned', 'label' => 'Unassigned'],
            ['value' => 'mine', 'label' => 'Mine'],
        ];

        if ($this->canManageAssignments($actor)) {
            array_unshift($options, ['value' => 'all', 'label' => 'All']);
        }

        return $options;
    }

    public function canManageAssignments(AppUser $actor): bool
    {
        return $actor->hasActiveStaffAccess()
            && (
                $actor->hasPermission(Permission::LOAN_MANAGE_ASSIGNMENT)
                || $actor->isLegacySuperadmin()
            );
    }

    public function applyOfficerQueueScope(
        Builder $query,
        AppUser $actor,
        ?string $assignmentFilter = null,
        ?int $officerId = null,
    ): void {
        $normalizedFilter = $this->normalizeAssignmentFilter(
            $assignmentFilter,
        );

        if (
            $normalizedFilter === 'all'
            && ! $this->canManageAssignments($actor)
        ) {
            throw ValidationException::withMessages([
                'assignment' => 'The selected assignment filter is not available for your role.',
            ]);
        }

        if (
            $officerId !== null
            && ! $this->canManageAssignments($actor)
        ) {
            throw ValidationException::withMessages([
                'officerId' => 'Filtering by loan officer is not available for your role.',
            ]);
        }

        if (
            $officerId !== null
            && ! collect($this->eligibleOfficerOptions())->contains(
                fn (array $officer): bool => $officer['user_id'] === $officerId,
            )
        ) {
            throw ValidationException::withMessages([
                'officerId' => 'The selected Loan Officer is not available for assignment oversight.',
            ]);
        }

        if ($this->canManageAssignments($actor)) {
            if ($normalizedFilter === 'unassigned') {
                $query->whereIn('status', $this->operationalAssignmentStatuses())
                    ->whereNull('assigned_officer_id');

                return;
            }

            if ($normalizedFilter === 'mine') {
                $query->where('assigned_officer_id', $actor->user_id);

                return;
            }

            if ($officerId !== null) {
                $query->where('assigned_officer_id', $officerId);
            }

            return;
        }

        $query->where(function (Builder $builder) use (
            $actor,
            $normalizedFilter,
        ): void {
            if ($normalizedFilter === 'mine') {
                $builder->where('assigned_officer_id', $actor->user_id);

                return;
            }

            if ($normalizedFilter === 'unassigned') {
                $builder
                    ->whereIn('status', $this->operationalAssignmentStatuses())
                    ->whereNull('assigned_officer_id');

                return;
            }

            $builder
                ->where(function (Builder $queueQuery): void {
                    $queueQuery
                        ->whereIn(
                            'status',
                            $this->operationalAssignmentStatuses(),
                        )
                        ->whereNull('assigned_officer_id');
                })
                ->orWhere('assigned_officer_id', $actor->user_id);
        });
    }

    public function assignForStartReview(
        LoanRequest $loanRequest,
        AppUser $actor,
    ): array {
        $loanRequest->loadMissing('assignedOfficer.adminProfile');

        $previousOfficer = $loanRequest->assignedOfficer;
        $before = $this->assignmentSnapshot($loanRequest);

        if ($loanRequest->assigned_officer_id === $actor->user_id) {
            return [
                'before' => $before,
                'after' => $before,
                'assignment_changed' => false,
                'previous_officer' => $this->serializeOfficerAudit(
                    $previousOfficer,
                ),
                'new_officer' => $this->serializeOfficerAudit($previousOfficer),
            ];
        }

        if ($loanRequest->assigned_officer_id !== null) {
            throw new ConflictHttpException(self::CLAIM_CONFLICT_MESSAGE);
        }

        $loanRequest->forceFill([
            'assigned_officer_id' => $actor->user_id,
        ])->save();

        $updatedLoanRequest = $this->refreshLoanRequest($loanRequest);

        return [
            'before' => $before,
            'after' => $this->assignmentSnapshot($updatedLoanRequest),
            'assignment_changed' => true,
            'previous_officer' => $this->serializeOfficerAudit(
                $previousOfficer,
            ),
            'new_officer' => $this->serializeOfficerAudit(
                $updatedLoanRequest->assignedOfficer,
            ),
        ];
    }

    private function claimLockedLoanRequest(
        LoanRequest $loanRequest,
        AppUser $actor,
    ): LoanRequest {
        $loanRequest->loadMissing('assignedOfficer.adminProfile');

        if ($loanRequest->assigned_officer_id === $actor->user_id) {
            return $this->refreshLoanRequest($loanRequest);
        }

        if ($loanRequest->assigned_officer_id !== null) {
            throw new ConflictHttpException(self::CLAIM_CONFLICT_MESSAGE);
        }

        $before = $this->assignmentSnapshot($loanRequest);
        $fromStatus = $this->statusValue($loanRequest);

        $loanRequest->forceFill([
            'assigned_officer_id' => $actor->user_id,
        ])->save();

        $updatedLoanRequest = $this->refreshLoanRequest($loanRequest);

        $this->recordAssignmentAudit(
            $updatedLoanRequest,
            $actor,
            LoanRequestChange::ACTION_ASSIGNMENT_CLAIMED,
            'Claimed from queue.',
            $fromStatus,
            $fromStatus,
            $before,
            $this->assignmentSnapshot($updatedLoanRequest),
            null,
            $updatedLoanRequest->assignedOfficer,
            null,
        );

        return $updatedLoanRequest;
    }

    private function setOfficerAssignment(
        LoanRequest $loanRequest,
        AppUser $targetOfficer,
        AppUser $actor,
        string $reason,
        string $action,
    ): LoanRequest {
        $loanRequest->loadMissing('assignedOfficer.adminProfile', 'user');
        $this->ensureOfficerEligibility($loanRequest, $targetOfficer);

        $before = $this->assignmentSnapshot($loanRequest);
        $fromStatus = $this->statusValue($loanRequest);
        $previousOfficer = $loanRequest->assignedOfficer;

        $loanRequest->forceFill([
            'assigned_officer_id' => $targetOfficer->user_id,
        ])->save();

        $updatedLoanRequest = $this->refreshLoanRequest($loanRequest);

        $this->recordAssignmentAudit(
            $updatedLoanRequest,
            $actor,
            $action,
            $reason,
            $fromStatus,
            $fromStatus,
            $before,
            $this->assignmentSnapshot($updatedLoanRequest),
            $previousOfficer,
            $updatedLoanRequest->assignedOfficer,
            null,
        );

        return $updatedLoanRequest;
    }

    private function ensureOfficerEligibility(
        LoanRequest $loanRequest,
        AppUser $targetOfficer,
    ): void {
        $targetOfficer->loadMissing(
            'adminProfile',
            'roles.permissions',
            'staffAccessControl',
        );

        if (! $targetOfficer->hasRole(Role::LOAN_OFFICER)) {
            throw ValidationException::withMessages([
                'officer_user_id' => 'The selected user is not an active Loan Officer.',
            ]);
        }

        if (! $targetOfficer->hasActiveStaffAccess()) {
            throw ValidationException::withMessages([
                'officer_user_id' => 'The selected Loan Officer does not currently have active staff access.',
            ]);
        }

        if (
            $loanRequest->user_id !== null
            && $loanRequest->user_id === $targetOfficer->user_id
        ) {
            throw ValidationException::withMessages([
                'officer_user_id' => 'You cannot assign an application to the applicant.',
            ]);
        }

        $requestAcctno = trim((string) ($loanRequest->acctno ?? ''));
        $targetAcctno = trim((string) ($targetOfficer->acctno ?? ''));

        if (
            $requestAcctno !== ''
            && $targetAcctno !== ''
            && $requestAcctno === $targetAcctno
        ) {
            throw ValidationException::withMessages([
                'officer_user_id' => 'You cannot assign an application to the applicant.',
            ]);
        }
    }

    private function lockLoanRequest(LoanRequest $loanRequest): LoanRequest
    {
        return LoanRequest::query()
            ->with(['assignedOfficer.adminProfile', 'user'])
            ->whereKey($loanRequest->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockOfficer(int $userId): AppUser
    {
        $this->lockOfficerSupportRows($userId);

        return AppUser::query()
            ->with(['adminProfile', 'roles.permissions', 'staffAccessControl'])
            ->whereKey($userId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockOfficerSupportRows(int $userId): void
    {
        if ($this->schemaCapabilities->hasTable('user_roles')) {
            DB::table('user_roles')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();
        }

        if ($this->schemaCapabilities->hasTable('staff_access_controls')) {
            DB::table('staff_access_controls')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();
        }
    }

    private function refreshLoanRequest(LoanRequest $loanRequest): LoanRequest
    {
        return $loanRequest->refresh()->loadMissing(
            'assignedOfficer.adminProfile',
            'reviewedBy.adminProfile',
            'rejectedBy.adminProfile',
            'approvedBy.adminProfile',
            'declinedBy.adminProfile',
            'cancelledBy',
            'user',
        );
    }

    private function ensureOperationalAssignmentStatus(
        LoanRequest $loanRequest,
        string $message,
    ): void {
        if (
            ! in_array(
                $this->statusValue($loanRequest),
                $this->operationalAssignmentStatuses(),
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }
    }

    private function normalizeReason(string $reason): string
    {
        $normalizedReason = trim($reason);

        if ($normalizedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for this assignment change.',
            ]);
        }

        return $normalizedReason;
    }

    private function statusValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentSnapshot(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('assignedOfficer.adminProfile');

        return [
            'id' => $loanRequest->id,
            'user_id' => $loanRequest->user_id,
            'acctno' => $loanRequest->acctno,
            'status' => $this->statusValue($loanRequest),
            'assigned_officer_id' => $loanRequest->assigned_officer_id,
            'assigned_officer' => $this->serializeOfficerAudit(
                $loanRequest->assignedOfficer,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAssignmentAudit(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $action,
        string $reason,
        string $fromStatus,
        string $toStatus,
        array $before,
        array $after,
        ?AppUser $previousOfficer,
        ?AppUser $newOfficer,
        ?string $cause,
    ): void {
        if (! $this->schemaCapabilities->hasTable('loan_request_changes')) {
            return;
        }

        $audit = [
            'loan_request_id' => $loanRequest->id,
            'changed_by' => $actor->user_id,
            'action' => $action,
            'reason' => $reason,
            'before_json' => $before,
            'after_json' => $after,
            'changed_fields_json' => ['assigned_officer_id'],
        ];

        if ($this->schemaCapabilities->hasColumn('loan_request_changes', 'from_status')) {
            $audit['from_status'] = $fromStatus;
        }

        if ($this->schemaCapabilities->hasColumn('loan_request_changes', 'to_status')) {
            $audit['to_status'] = $toStatus;
        }

        if ($this->schemaCapabilities->hasColumn('loan_request_changes', 'metadata_json')) {
            $audit['metadata_json'] = array_filter([
                'request_status' => $fromStatus,
                'previous_officer' => $this->serializeOfficerAudit(
                    $previousOfficer,
                ),
                'new_officer' => $this->serializeOfficerAudit($newOfficer),
                'cause' => $cause,
            ], static fn (mixed $value): bool => $value !== null);
        }

        LoanRequestChange::query()->create($audit);
    }

    /**
     * @return array{
     *     user_id:int,
     *     name:string,
     *     display_code:string,
     *     username:string|null
     * }|null
     */
    private function serializeOfficerAudit(?AppUser $officer): ?array
    {
        if (! $officer instanceof AppUser) {
            return null;
        }

        return [
            'user_id' => $officer->user_id,
            'name' => $this->resolveOfficerDisplayName($officer),
            'display_code' => $officer->display_code,
            'username' => $this->normalizeOptionalString($officer->username),
        ];
    }

    /**
     * @return array{
     *     user_id:int,
     *     name:string,
     *     display_code:string,
     *     username:string|null,
     *     active_assignment_count:int,
     *     has_workload_warning:bool,
     *     workload_warning_label:string|null
     * }
     */
    private function serializeOfficerOption(AppUser $officer): array
    {
        $activeAssignmentCount = (int) ($officer->active_assignment_count ?? 0);
        $hasWorkloadWarning = $activeAssignmentCount >= $this->workloadWarningThreshold();

        return [
            'user_id' => $officer->user_id,
            'name' => $this->resolveOfficerDisplayName($officer),
            'display_code' => $officer->display_code,
            'username' => $this->normalizeOptionalString($officer->username),
            'active_assignment_count' => $activeAssignmentCount,
            'has_workload_warning' => $hasWorkloadWarning,
            'workload_warning_label' => $hasWorkloadWarning
                ? 'High workload'
                : null,
        ];
    }

    private function resolveOfficerDisplayName(AppUser $officer): string
    {
        $adminName = trim((string) ($officer->adminProfile?->fullname ?? ''));

        if ($adminName !== '') {
            return $adminName;
        }

        if (Schema::hasTable('wmaster')) {
            $officer->loadMissing('wmaster');
            $memberName = $officer->wmaster?->displayName();

            if (is_string($memberName) && trim($memberName) !== '') {
                return trim($memberName);
            }
        }

        $username = trim((string) ($officer->username ?? ''));

        if ($username !== '') {
            return $username;
        }

        $email = trim((string) ($officer->email ?? ''));

        return $email !== '' ? $email : $officer->display_code;
    }

    private function workloadWarningThreshold(): int
    {
        $threshold = (int) config(
            'loan_workflow.officer_workload_warning_threshold',
            30,
        );

        return $threshold > 0 ? $threshold : 30;
    }

    private function assignmentStateFor(
        LoanRequest $loanRequest,
        AppUser $actor,
    ): string {
        if ($loanRequest->assigned_officer_id === null) {
            return 'unassigned';
        }

        return $loanRequest->assigned_officer_id === $actor->user_id
            ? 'assigned_to_me'
            : 'assigned_to_other';
    }

    private function normalizeAssignmentFilter(?string $assignmentFilter): ?string
    {
        if (! is_string($assignmentFilter)) {
            return null;
        }

        $normalizedFilter = trim($assignmentFilter);

        return $normalizedFilter !== '' ? $normalizedFilter : null;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }
}
