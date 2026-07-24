<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * cycle_status/relationship/occupation had zero non-null rows at the
     * time of this migration (verified against production data before
     * writing it), so relationship/occupation are dropped outright rather
     * than deprecated in place -- the physical Form B "Dependents
     * Information" section has no Relationship or Occupation columns for
     * any category.
     */
    public function up(): void
    {
        Schema::table('member_dependents', function (Blueprint $table) {
            $table->dropColumn(['relationship', 'occupation']);
            $table->unsignedTinyInteger('cycle_number')->nullable()->after('cycle_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_dependents', function (Blueprint $table) {
            $table->dropColumn('cycle_number');
            $table->string('relationship')->nullable();
            $table->string('occupation')->nullable();
        });
    }
};
