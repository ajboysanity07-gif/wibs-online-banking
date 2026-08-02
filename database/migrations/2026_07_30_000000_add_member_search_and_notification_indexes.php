<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wmaster')) {
            foreach (['lname', 'fname', 'mname', 'bname', 'email_address'] as $column) {
                if (Schema::hasColumn('wmaster', $column)) {
                    $this->tryAddIndex('wmaster', [$column], "idx_wmaster_{$column}");
                }
            }

            if (Schema::hasColumn('wmaster', 'datemem')) {
                $this->tryAddIndex('wmaster', ['datemem'], 'idx_wmaster_datemem');
            }
        }

        if (Schema::hasTable('notifications')) {
            $this->tryAddIndex(
                'notifications',
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_notifiable_read_at_index',
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wmaster')) {
            foreach (['lname', 'fname', 'mname', 'bname', 'email_address'] as $column) {
                $this->tryDropIndex('wmaster', "idx_wmaster_{$column}");
            }

            $this->tryDropIndex('wmaster', 'idx_wmaster_datemem');
        }

        if (Schema::hasTable('notifications')) {
            $this->tryDropIndex('notifications', 'notifications_notifiable_read_at_index');
        }
    }

    private function tryAddIndex(string $table, array $columns, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->index($columns, $name);
            });
        } catch (\Throwable) {
        }
    }

    private function tryDropIndex(string $table, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        } catch (\Throwable) {
        }
    }
};
