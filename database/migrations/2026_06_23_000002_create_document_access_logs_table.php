<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('loan_request_id')->nullable();
            $table->string('document_key');
            $table->string('action'); // view|download
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('appusers');
            $table->foreign('loan_request_id')->references('id')->on('loan_requests')->nullOnDelete();
            $table->index(['user_id', 'accessed_at']);
            $table->index(['loan_request_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_logs');
    }
};
