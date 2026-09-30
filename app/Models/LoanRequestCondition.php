<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestCondition extends Model
{
    /**
     * Conditions a processor signs off before recommending approval.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'identity_membership' => 'Identity and membership verified',
        'employment' => 'Employment confirmed',
        'income_documents' => 'Income documents match',
        'employer_category' => 'Employer category confirmed with member',
        'co_maker_requirements' => 'Co-maker requirements reviewed',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'condition_key',
        'verified_by',
        'verified_at',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'verified_by', 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }
}
