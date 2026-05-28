<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AdminSignature extends Model
{
    /** @use HasFactory<\Database\Factories\AdminSignatureFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'signature_path',
        'is_active',
        'created_ip',
        'created_user_agent',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['signature_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }

    public function getSignatureUrlAttribute(): ?string
    {
        $path = trim((string) $this->signature_path);

        if ($path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
