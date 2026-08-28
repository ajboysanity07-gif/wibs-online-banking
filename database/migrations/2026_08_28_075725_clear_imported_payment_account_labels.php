<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_LABELS = [
        'Imported payout account',
        'Imported repayment account',
    ];

    public function up(): void
    {
        DB::table('member_payment_accounts')
            ->whereIn('label', self::LEGACY_LABELS)
            ->update(['label' => '']);
    }

    public function down(): void
    {
        //
    }
};
