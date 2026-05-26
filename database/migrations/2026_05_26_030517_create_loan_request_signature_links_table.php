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
        Schema::create('loan_request_signature_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_request_id')
                ->constrained('loan_requests')
                ->cascadeOnDelete();
            $table->foreignId('loan_request_person_id')
                ->constrained('loan_request_people')
                ->noActionOnDelete();
            $table->string('role', 32)->index();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at')->index();
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_request_signature_links');
    }
};
