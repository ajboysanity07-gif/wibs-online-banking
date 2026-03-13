<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wsvmaster extends Model
{
    protected $table = 'wsvmaster';

    protected $primaryKey = 'svnumber';

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
        'mortuary',
        'balance',
        'wbalance',
        'lastmove',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mortuary' => 'decimal:2',
            'balance' => 'decimal:2',
            'wbalance' => 'decimal:2',
            'lastmove' => 'datetime',
        ];
    }
}
