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
        if (Schema::hasTable('loan_request_data_entries')) {
            return;
        }

        Schema::create('loan_request_data_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->string('section_key');
            $table->string('field_key');
            $table->string('owner_type')->default('member');
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('confirmed_by_member')->default(false);
            $table->timestamp('confirmed_by_member_at')->nullable();
            $table->text('value_json')->nullable();
            $table->text('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['loan_request_id', 'field_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_request_data_entries');
    }
};
