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
        Schema::create('loan_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('acctno');
            $table->string('typecode');
            $table->string('loan_type_label_snapshot');
            $table->decimal('requested_amount', 12, 2);
            $table->unsignedSmallInteger('requested_term');
            $table->string('loan_purpose');
            $table->string('availment_status');
            $table->string('status')->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->unsignedSmallInteger('approved_term')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('appusers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('reviewed_by')
                ->references('user_id')
                ->on('appusers')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index('user_id');
            $table->index('acctno');
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_requests');
    }
};
