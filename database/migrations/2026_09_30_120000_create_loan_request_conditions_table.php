<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_request_conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->string('condition_key', 40);
            $table->unsignedBigInteger('verified_by');
            $table->timestamp('verified_at');
            $table->timestamps();

            $table->foreign('loan_request_id')
                ->references('id')
                ->on('loan_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('verified_by')
                ->references('user_id')
                ->on('appusers');

            $table->unique(['loan_request_id', 'condition_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_request_conditions');
    }
};
