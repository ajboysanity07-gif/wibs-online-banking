<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staff-only note on a loan request. Never shown to members.
 */
class LoanRequestNote extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'loan_request_id',
        'user_id',
        'body',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }
}
