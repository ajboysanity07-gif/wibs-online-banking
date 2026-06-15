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
        if (Schema::hasTable('loan_request_data_changes')) {
            return;
        }

        Schema::create('loan_request_data_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('field_key');
            $table->text('before_value_json')->nullable();
            $table->text('after_value_json')->nullable();
            $table->string('reason');
            $table->string('information_source');
            $table->text('metadata_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_request_data_changes');
    }
};
