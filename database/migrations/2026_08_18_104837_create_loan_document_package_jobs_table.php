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
        Schema::create('loan_document_package_jobs', function (Blueprint $table) {
            $table->id();
            // No DB-level FK to loan_requests either -- this codebase
            // avoids them broadly (see loan_request_documents), since SQL
            // Server rejects cascade actions that introduce multiple
            // cascade paths through the existing constraint graph.
            $table->unsignedBigInteger('loan_request_id');
            $table->string('status')->default('queued');
            $table->string('zip_disk')->nullable();
            $table->string('zip_path')->nullable();
            $table->string('zip_filename')->nullable();
            $table->text('error_message')->nullable();
            // No DB-level FK to appusers.user_id: mirrors
            // loan_request_documents.generated_by, which stays a plain
            // column for the same reason -- SQL Server rejects any cascade
            // action here as introducing multiple cascade paths through the
            // existing constraint graph. Referential integrity is enforced
            // at the application layer instead.
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['loan_request_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_document_package_jobs');
    }
};
