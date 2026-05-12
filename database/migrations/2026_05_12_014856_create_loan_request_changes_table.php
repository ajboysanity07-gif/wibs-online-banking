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
        if (Schema::hasTable('loan_request_changes')) {
            return;
        }

        Schema::create('loan_request_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('action')->nullable();
            $table->text('reason')->nullable();
            $table->json('before_json');
            $table->json('after_json');
            $table->json('changed_fields_json')->nullable();
            $table->timestamps();

            $table->foreign('loan_request_id')
                ->references('id')
                ->on('loan_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $changedByForeignKey = $table->foreign('changed_by')
                ->references('user_id')
                ->on('appusers');

            if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                $changedByForeignKey->onDelete('no action');
            } else {
                $changedByForeignKey
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_request_changes');
    }
};
