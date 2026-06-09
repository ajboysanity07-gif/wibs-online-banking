<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestChange extends Model
{
    public const ACTION_START_REVIEW = 'start_review';

    public const ACTION_REQUEST_REVISION = 'request_revision';

    public const ACTION_REJECT = 'reject';

    public const ACTION_RECOMMEND_APPROVAL = 'recommend_approval';

    public const ACTION_APPROVE = 'approve';

    public const ACTION_DECLINE = 'decline';

    public const ACTION_CONVERT_TO_LOAN = 'convert_to_loan';

    public const ACTION_CANCEL_REQUEST = 'cancel_request';

    public const ACTION_CREATE_CORRECTED_REQUEST = 'create_corrected_request';

    public const ACTION_ADMIN_CREATE_CORRECTED_REQUEST = 'admin_create_corrected_request';

    public const ACTION_ADMIN_UPDATE_CORRECTED_REQUEST_DETAILS = 'admin_update_corrected_request_details';

    public const ACTION_CANCEL_APPROVED_REQUEST = 'cancel_approved_request';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'changed_by',
        'action',
        'from_status',
        'to_status',
        'reason',
        'before_json',
        'after_json',
        'changed_fields_json',
        'metadata_json',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'changed_by', 'user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->changedBy();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'changed_fields_json' => 'array',
            'metadata_json' => 'array',
        ];
    }
}
