<?php

namespace App\Models;

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanRequest extends Model
{
    /** @use HasFactory<\Database\Factories\LoanRequestFactory> */
    use HasFactory;

    private const REFERENCE_PREFIX = 'LNREQ';

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
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'approved_amount',
        'approved_term',
        'decision_notes',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'reviewed_by', 'user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'cancelled_by', 'user_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(LoanRequestChange::class);
    }

    public function correctionReports(): HasMany
    {
        return $this->hasMany(LoanRequestCorrectionReport::class);
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
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => LoanRequestStatus::class,
        ];
    }
}
