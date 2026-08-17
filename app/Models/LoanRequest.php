<?php

namespace App\Models;

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanRequest extends Model
{
    /** @use HasFactory<\Database\Factories\LoanRequestFactory> */
    use HasFactory;

    public const REFERENCE_PREFIX = 'LNREQ';

    /**
     * Target turnaround time (business days) for loan decisions.
     */
    public const DECISION_TARGET_BUSINESS_DAYS = 3;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'corrected_from_id',
        'acctno',
        'typecode',
        'loan_type_label_snapshot',
        'requested_amount',
        'requested_term',
        'loan_purpose',
        'availment_status',
        'status',
        'workflow_version',
        'submitted_at',
        'assigned_officer_id',
        'reviewed_by',
        'reviewed_at',
        'review_decision',
        'review_remarks',
        'review_rejection_category',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'approval_ip_address',
        'approval_user_agent',
        'approved_amount',
        'approved_term',
        'approved_interest_rate',
        'recommended_amount',
        'recommended_term',
        'recommended_interest_rate',
        'recommended_payment_frequency',
        'recommended_payment_frequency_lumpsum_months',
        'requested_payment_frequency',
        'requested_payment_frequency_lumpsum_months',
        'decision_notes',
        'declined_by',
        'declined_at',
        'decline_category',
        'decline_reason',
        'member_action_type',
        'member_action_message',
        'member_action_fields_json',
        'member_action_requested_by',
        'member_action_requested_at',
        'member_action_resolved_at',
        'workflow_upgraded_by',
        'workflow_upgraded_at',
        'workflow_upgrade_reason',
        'reopened_by',
        'reopened_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'wibs_loan_reference',
        'wibs_release_date',
        'wibs_encoded_at',
        'wibs_released_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }

    public function correctedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrected_from_id', 'id');
    }

    public function correctedRequests(): HasMany
    {
        return $this->hasMany(self::class, 'corrected_from_id', 'id');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'assigned_officer_id', 'user_id');
    }

    public function assignedProcessor(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'assigned_officer_id', 'user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'reviewed_by', 'user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'rejected_by', 'user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'approved_by', 'user_id');
    }

    public function declinedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'declined_by', 'user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'cancelled_by', 'user_id');
    }

    public function memberActionRequestedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'member_action_requested_by', 'user_id');
    }

    public function workflowUpgradedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'workflow_upgraded_by', 'user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'reopened_by', 'user_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(LoanRequestChange::class);
    }

    public function dataEntries(): HasMany
    {
        return $this->hasMany(LoanRequestDataEntry::class);
    }

    public function dataChanges(): HasMany
    {
        return $this->hasMany(LoanRequestDataChange::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LoanRequestDocument::class);
    }

    public function notificationEvents(): HasMany
    {
        return $this->hasMany(LoanRequestNotificationEvent::class);
    }

    public function correctionReports(): HasMany
    {
        return $this->hasMany(LoanRequestCorrectionReport::class);
    }

    public function latestOpenCorrectionReport(): HasOne
    {
        return $this->hasOne(LoanRequestCorrectionReport::class)
            ->where('status', LoanRequestCorrectionReport::STATUS_OPEN)
            ->latest('id');
    }

    public function people(): HasMany
    {
        return $this->hasMany(LoanRequestPerson::class);
    }

    public function applicant(): HasOne
    {
        return $this->hasOne(LoanRequestPerson::class)
            ->where('role', LoanRequestPersonRole::Applicant->value);
    }

    public function coMakerOne(): HasOne
    {
        return $this->hasOne(LoanRequestPerson::class)
            ->where('role', LoanRequestPersonRole::CoMakerOne->value);
    }

    public function coMakerTwo(): HasOne
    {
        return $this->hasOne(LoanRequestPerson::class)
            ->where('role', LoanRequestPersonRole::CoMakerTwo->value);
    }

    public function isOwnedBy(AppUser $user): bool
    {
        if ($this->user_id !== null && $this->user_id === $user->user_id) {
            return true;
        }

        $requestAcctno = trim((string) ($this->acctno ?? ''));
        $userAcctno = trim((string) ($user->acctno ?? ''));

        if ($requestAcctno === '' || $userAcctno === '') {
            return false;
        }

        return $requestAcctno === $userAcctno;
    }

    public function getReferenceAttribute(): string
    {
        $id = (int) ($this->getKey() ?? 0);

        return sprintf('%s-%06d', self::REFERENCE_PREFIX, $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'corrected_from_id' => 'integer',
            'assigned_officer_id' => 'integer',
            'reviewed_by' => 'integer',
            'rejected_by' => 'integer',
            'approved_by' => 'integer',
            'declined_by' => 'integer',
            'member_action_requested_by' => 'integer',
            'workflow_upgraded_by' => 'integer',
            'reopened_by' => 'integer',
            'cancelled_by' => 'integer',
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'approved_interest_rate' => 'decimal:4',
            'recommended_amount' => 'decimal:2',
            'recommended_interest_rate' => 'decimal:4',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'approved_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'member_action_requested_at' => 'datetime',
            'member_action_resolved_at' => 'datetime',
            'workflow_upgraded_at' => 'datetime',
            'reopened_at' => 'datetime',
            'member_action_fields_json' => 'array',
            'workflow_version' => LoanRequestWorkflowVersion::class,
            'status' => LoanRequestStatus::class,
            'wibs_release_date' => 'date',
            'wibs_encoded_at' => 'datetime',
            'wibs_released_at' => 'datetime',
        ];
    }
}
