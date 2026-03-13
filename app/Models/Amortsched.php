<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amortsched extends Model
{
    protected $table = 'Amortsched';

    protected $primaryKey = 'controlno';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lnnumber',
        'Date_pay',
        'Amortization',
        'Interest',
        'Balance',
        'controlno',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Date_pay' => 'datetime',
            'Amortization' => 'decimal:2',
            'Interest' => 'decimal:2',
            'Balance' => 'decimal:2',
        ];
    }
}
