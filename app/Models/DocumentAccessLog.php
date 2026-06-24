<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAccessLog extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentAccessLogFactory> */
    use HasFactory;

    public const ACTION_VIEW = 'view';

    public const ACTION_DOWNLOAD = 'download';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'loan_request_id',
        'document_key',
        'action',
        'accessed_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public static function record(
        int $userId,
        ?int $loanRequestId,
        string $documentKey,
        string $action,
    ): void {
        static::create([
            'user_id' => $userId,
            'loan_request_id' => $loanRequestId,
            'document_key' => $documentKey,
            'action' => $action,
            'accessed_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }
}
