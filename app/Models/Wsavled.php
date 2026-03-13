<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wsavled extends Model
{
    protected $table = 'wsavled';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'acctno',
        'svnumber',
        'svtype',
        'date_in',
        'deposit',
        'withdrawal',
        'balance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_in' => 'datetime',
            'deposit' => 'decimal:2',
            'withdrawal' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }
}
