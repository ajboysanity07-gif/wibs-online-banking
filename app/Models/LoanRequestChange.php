<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestChange extends Model
{
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
        'reason',
        'before_json',
        'after_json',
        'changed_fields_json',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'changed_by', 'user_id');
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
        ];
    }
}
