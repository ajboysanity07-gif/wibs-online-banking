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
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('appusers', 'user_id')->nullOnDelete();
            $table->string('acctno', 20)->nullable()->index();
            $table->string('loan_number', 64)->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('PHP');
            $table->string('provider')->default('paymongo');
            $table->string('provider_checkout_id', 120)->nullable()->index();
            $table->string('provider_payment_id', 120)->nullable()->index();
            $table->string('reference_number', 120)->nullable()->index();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('appusers', 'user_id')->noActionOnDelete();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};
