<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_request_people', function (Blueprint $table) {
            $table->date('employer_date_employed')->nullable()->after('years_in_work_business');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_request_people', function (Blueprint $table) {
            $table->dropColumn('employer_date_employed');
        });
    }
};
