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
        Schema::create('loan_request_correction_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->unsignedBigInteger('user_id');
            $table->text('issue_description');
            $table->text('correct_information');
            $table->text('supporting_note')->nullable();
            $table->string('status')->default('open');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('dismissed_by')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->foreign('loan_request_id')
                ->references('id')
                ->on('loan_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('appusers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $resolvedByForeignKey = $table->foreign('resolved_by')
                ->references('user_id')
                ->on('appusers');

            $dismissedByForeignKey = $table->foreign('dismissed_by')
                ->references('user_id')
                ->on('appusers');

            if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                $resolvedByForeignKey->onDelete('no action');
                $dismissedByForeignKey->onDelete('no action');
            } else {
                $resolvedByForeignKey
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $dismissedByForeignKey
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            $table->index('loan_request_id');
            $table->index('user_id');
            $table->index('status');
            $table->index(['loan_request_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_request_correction_reports');
    }
};
