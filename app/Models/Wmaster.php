<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wmaster extends Model
{
    use HasFactory;

    protected $table = 'wmaster';

    protected $primaryKey = 'acctno';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'acctno',
        'lname',
        'fname',
        'mname',
        'bname',
        'phone',
        'email_address',
        'address',
        'birthday',
        'datemem',
        'beneficiary1',
        'beneficiary2',
        'beneficiary3',
        'ben1_bday',
        'ben2_bday',
        'ben3_bday',
        'civilstat',
        'sex',
        'occupation',
        'memtype',
        'district',
        'zoning',
        'dateissued',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'UserPassword',
        'Userrights',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'datemem' => 'date',
            'ben1_bday' => 'date',
            'ben2_bday' => 'date',
            'ben3_bday' => 'date',
            'dateissued' => 'date',
        ];
    }

    public function normalizedLastName(): string
    {
        return $this->normalizeValue($this->lname);
    }

    public function normalizedFirstName(): string
    {
        return $this->normalizeValue($this->fname);
    }

    public function normalizedMiddleInitial(): string
    {
        $normalized = $this->normalizeValue($this->mname);

        return $normalized === '' ? '' : substr($normalized, 0, 1);
    }

    public function normalizedBname(): string
    {
        return $this->normalizeValue($this->bname);
    }

    private function normalizeValue(?string $value): string
    {
        $normalized = Str::upper(trim((string) $value));
        $normalized = preg_replace('/[.,\-]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
