<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds processor-confirmed cycle columns, distinct from the
     * member-self-reported cycle_status/cycle_number columns. Once a
     * loan processor has confirmed a person's (applicant/spouse/dependent)
     * Group Life Insurance cycle on a processed loan, the confirmed value
     * becomes the source of truth for every future loan -- the field
     * becomes read-only and locked to "Old" + confirmed_number + 1.
     */
    public function up(): void
    {
        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->string('applicant_confirmed_cycle_status')->nullable();
            $table->unsignedTinyInteger('applicant_confirmed_cycle_number')->nullable();
            $table->string('spouse_confirmed_cycle_status')->nullable();
            $table->unsignedTinyInteger('spouse_confirmed_cycle_number')->nullable();
        });

        Schema::table('member_dependents', function (Blueprint $table) {
            $table->string('confirmed_cycle_status')->nullable();
            $table->unsignedTinyInteger('confirmed_cycle_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'applicant_confirmed_cycle_status',
                'applicant_confirmed_cycle_number',
                'spouse_confirmed_cycle_status',
                'spouse_confirmed_cycle_number',
            ]);
        });

        Schema::table('member_dependents', function (Blueprint $table) {
            $table->dropColumn(['confirmed_cycle_status', 'confirmed_cycle_number']);
        });
    }
};
