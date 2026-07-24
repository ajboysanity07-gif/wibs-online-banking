<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The physical Form B "Dependents Information" section has a
     * New/Old + cycle number field for Spouse too, not just the repeatable
     * categories -- Spouse is a singleton row here rather than a
     * member_dependents slot, so its cycle fields live directly on the
     * profile.
     */
    public function up(): void
    {
        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->string('spouse_cycle_status')->nullable();
            $table->unsignedTinyInteger('spouse_cycle_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->dropColumn(['spouse_cycle_status', 'spouse_cycle_number']);
        });
    }
};
