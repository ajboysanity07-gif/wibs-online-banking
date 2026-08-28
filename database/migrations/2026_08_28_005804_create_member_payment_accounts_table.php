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
        Schema::create('member_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_application_profile_id')
                ->constrained('member_application_profiles')
                ->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_type')->nullable();
            $table->string('atm_number')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('atm_holder_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_payment_accounts');
    }
};
