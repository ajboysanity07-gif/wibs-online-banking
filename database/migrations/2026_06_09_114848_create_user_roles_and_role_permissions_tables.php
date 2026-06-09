<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');
                $table->timestamps();

                $table->foreign('role_id')
                    ->references('id')
                    ->on('roles')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->foreign('permission_id')
                    ->references('id')
                    ->on('permissions')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table->unique(['role_id', 'permission_id']);
            });
        }

        if (! Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('role_id');
                $table->timestamps();

                $userForeignKey = $table->foreign('user_id')
                    ->references('user_id')
                    ->on('appusers');

                if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                    $userForeignKey->onDelete('no action');
                } else {
                    $userForeignKey
                        ->cascadeOnUpdate()
                        ->cascadeOnDelete();
                }

                $table->foreign('role_id')
                    ->references('id')
                    ->on('roles')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table->unique(['user_id', 'role_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
    }
};
