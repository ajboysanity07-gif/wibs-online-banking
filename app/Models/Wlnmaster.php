<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wlnmaster extends Model
{
    protected $table = 'wlnmaster';

    protected $primaryKey = 'lnnumber';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'acctno',
        'lnnumber',
        'lntype',
        'lnstatus',
        'principal',
        'balance',
        'date_rel',
        'date_mat',
        'lastmove',
        'initial',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'balance' => 'decimal:2',
            'initial' => 'decimal:2',
            'date_rel' => 'date',
            'date_mat' => 'date',
            'lastmove' => 'datetime',
        ];
    }
}
