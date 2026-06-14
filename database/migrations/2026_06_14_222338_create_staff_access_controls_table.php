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
        if (Schema::hasTable('staff_access_controls')) {
            return;
        }

        Schema::create('staff_access_controls', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('status')->default('active');
            $table->dateTime('suspended_at')->nullable();
            $table->unsignedBigInteger('suspended_by')->nullable()->index();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();

            $userForeignKey = $table->foreign('user_id')
                ->references('user_id')
                ->on('appusers');

            $suspendedByForeignKey = $table->foreign('suspended_by')
                ->references('user_id')
                ->on('appusers');

            if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                $userForeignKey->onDelete('no action');
                $suspendedByForeignKey->onDelete('no action');
            } else {
                $userForeignKey
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $suspendedByForeignKey
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            $table->index(['status', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_access_controls');
    }
};
