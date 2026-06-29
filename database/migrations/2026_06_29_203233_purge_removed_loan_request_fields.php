<?php

use App\LoanRequestPersonRole;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LoanRequestDataEntry::query()
            ->whereIn('field_key', ['authorization_reason', 'authorized_recipient_contact'])
            ->delete();

        LoanRequestPerson::query()
            ->whereIn('role', [
                LoanRequestPersonRole::CoMakerOne->value,
                LoanRequestPersonRole::CoMakerTwo->value,
            ])
            ->update([
                'civil_status' => null,
                'housing_status' => null,
            ]);
    }

    public function down(): void
    {
        // Irreversible — deleted rows and nulled values cannot be recovered.
    }
};
