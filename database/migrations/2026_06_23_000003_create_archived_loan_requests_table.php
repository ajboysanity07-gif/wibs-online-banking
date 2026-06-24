<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_loan_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->unique();
            $table->string('reference')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('original_submitted_at')->nullable();
            $table->timestamp('original_approved_at')->nullable();
            $table->timestamp('original_rejected_at')->nullable();
            $table->json('data_json');
            $table->timestamp('archived_at');
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->timestamps();

            $table->index(['archived_at']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_loan_requests');
    }
};
