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
        if (Schema::hasTable('user_role_changes')) {
            return;
        }

        Schema::create('user_role_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('target_user_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('action');
            $table->string('role_name')->nullable();
            $table->json('before_roles_json');
            $table->json('after_roles_json');
            $table->string('before_staff_status')->nullable();
            $table->string('after_staff_status')->nullable();
            $table->text('reason');
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $targetForeignKey = $table->foreign('target_user_id')
                ->references('user_id')
                ->on('appusers');
            $actorForeignKey = $table->foreign('actor_user_id')
                ->references('user_id')
                ->on('appusers');

            if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                $targetForeignKey->onDelete('no action');
                $actorForeignKey->onDelete('no action');
            } else {
                $targetForeignKey
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $actorForeignKey
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
        Schema::dropIfExists('user_role_changes');
    }
};
