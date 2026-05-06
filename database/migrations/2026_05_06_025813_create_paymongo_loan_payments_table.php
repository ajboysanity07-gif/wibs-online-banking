<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CHECKOUT_SESSION_INDEX = 'paymongo_loan_payments_checkout_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('paymongo_loan_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('acctno')->index();
            $table->string('loan_number')->index();
            $table->string('currency')->default('PHP');
            $table->string('payment_method')->index();
            $table->string('payment_method_label')->nullable();
            $table->string('payment_method_type')->nullable();
            $table->unsignedInteger('base_amount_cents');
            $table->unsignedInteger('service_fee_cents');
            $table->unsignedInteger('gross_amount_cents');
            $table->string('status')->default('pending')->index();
            $table->string('provider')->default('paymongo');
            $table->string('provider_checkout_session_id')->nullable();
            $table->string('provider_payment_intent_id')->nullable()->index();
            $table->string('provider_reference_number')->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::CHECKOUT_SESSION_INDEX.' ON paymongo_loan_payments (provider_checkout_session_id) WHERE provider_checkout_session_id IS NOT NULL',
            );

            return;
        }

        Schema::table('paymongo_loan_payments', function (Blueprint $table) {
            $table->unique(
                'provider_checkout_session_id',
                self::CHECKOUT_SESSION_INDEX,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paymongo_loan_payments');
    }
};
