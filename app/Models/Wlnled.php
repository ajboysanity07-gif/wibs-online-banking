<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wlnled extends Model
{
    protected $table = 'wlnled';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lnstatus',
        'grouploan',
        'acctno',
        'lnnumber',
        'bname',
        'typecode',
        'lntype',
        'date_in',
        'mreference',
        'cs_ck',
        'lncode',
        'principal',
        'payments',
        'balance',
        'debit',
        'credit',
        'unsettled',
        'transno',
        'controlno',
        'initial',
        'accruedint',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_in' => 'datetime',
            'principal' => 'decimal:2',
            'payments' => 'decimal:2',
            'balance' => 'decimal:2',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'initial' => 'decimal:2',
            'accruedint' => 'decimal:2',
        ];
    }
}
