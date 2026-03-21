<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wlntype extends Model
{
    /** @use HasFactory<\Database\Factories\WlntypeFactory> */
    use HasFactory;

    protected $table = 'wlntype';

    protected $primaryKey = 'typecode';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'typecode',
        'lntype',
    ];
}
