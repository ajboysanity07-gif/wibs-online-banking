<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminProfile extends Model
{
    use HasFactory;

    protected $table = 'admin_profiles';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'fullname',
        'profile_pic_path',
        'reviewed_by',
        'reviewed_at',
    ];

    public function appUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'reviewed_by', 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
}
