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
        Schema::create('authority_to_deduct_institution_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name');
            $table->string('institution_name_normalized')->unique();
            $table->string('officer_1_name')->nullable();
            $table->string('officer_1_title')->nullable();
            $table->string('officer_2_name')->nullable();
            $table->string('officer_2_title')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authority_to_deduct_institution_contacts');
    }
};
