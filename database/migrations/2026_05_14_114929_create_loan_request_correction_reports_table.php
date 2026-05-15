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

            /*
             * Keep cascade from loan_requests because correction reports belong
             * to a loan request and should be removed if the request is removed.
             */
            $table->foreign('loan_request_id')
                ->references('id')
                ->on('loan_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
             * Do not cascade appusers foreign keys.
             *
             * SQL Server rejects multiple cascade paths when this table points
             * directly to appusers and also indirectly through loan_requests.
             *
             * These reports are audit/history records, so appuser references
             * should use NO ACTION instead of cascade.
             */
            $table->foreign('user_id')
                ->references('user_id')
                ->on('appusers')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->foreign('resolved_by')
                ->references('user_id')
                ->on('appusers')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->foreign('dismissed_by')
                ->references('user_id')
                ->on('appusers')
                ->onUpdate('no action')
                ->onDelete('no action');

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