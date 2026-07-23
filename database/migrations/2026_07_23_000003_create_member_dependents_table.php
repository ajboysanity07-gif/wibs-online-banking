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
        Schema::create('member_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_dependent_profile_id')
                ->constrained('member_dependent_profiles')
                ->cascadeOnDelete();
            $table->string('category');
            $table->unsignedTinyInteger('slot');
            $table->string('name')->nullable();
            $table->string('relationship')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('occupation')->nullable();
            // Placeholder pending physical Form B confirmation -- see
            // LoanRequestDataService for the 'cycle_status' field notes.
            $table->string('cycle_status')->nullable();
            $table->timestamps();

            $table->unique(['member_dependent_profile_id', 'category', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_dependents');
    }
};
