<?php

use App\LoanRequestDocumentReadinessStatus;
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
        if (Schema::hasTable('loan_request_documents')) {
            return;
        }

        Schema::create('loan_request_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->string('document_key');
            $table->boolean('is_applicable')->default(true);
            $table->string('readiness_status')
                ->default(LoanRequestDocumentReadinessStatus::NotStarted->value);
            $table->string('template_version')->nullable();
            $table->string('source_hash')->nullable();
            $table->unsignedInteger('source_version')->default(0);
            $table->unsignedInteger('generated_version')->default(0);
            $table->string('generated_disk')->nullable();
            $table->string('generated_path')->nullable();
            $table->string('generated_filename')->nullable();
            $table->string('generated_mime_type')->nullable();
            $table->unsignedBigInteger('generated_size_bytes')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->text('failure_information_json')->nullable();
            $table->text('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['loan_request_id', 'document_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_request_documents');
    }
};
