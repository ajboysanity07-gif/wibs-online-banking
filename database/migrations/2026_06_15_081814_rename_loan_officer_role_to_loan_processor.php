<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('name', 'loan_officer')
                ->update([
                    'name' => 'loan_processor',
                    'display_name' => 'Loan Processor',
                ]);
        }

        if (Schema::hasTable('user_role_changes')) {
            if (Schema::hasColumn('user_role_changes', 'role_name')) {
                DB::table('user_role_changes')
                    ->where('role_name', 'loan_officer')
                    ->update(['role_name' => 'loan_processor']);
            }

            if (Schema::hasColumn('user_role_changes', 'before_roles_json')) {
                DB::table('user_role_changes')->update([
                    'before_roles_json' => DB::raw(
                        "REPLACE(before_roles_json, 'loan_officer', 'loan_processor')",
                    ),
                ]);
            }

            if (Schema::hasColumn('user_role_changes', 'after_roles_json')) {
                DB::table('user_role_changes')->update([
                    'after_roles_json' => DB::raw(
                        "REPLACE(after_roles_json, 'loan_officer', 'loan_processor')",
                    ),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('name', 'loan_processor')
                ->update([
                    'name' => 'loan_officer',
                    'display_name' => 'Loan Officer',
                ]);
        }

        if (Schema::hasTable('user_role_changes')) {
            if (Schema::hasColumn('user_role_changes', 'role_name')) {
                DB::table('user_role_changes')
                    ->where('role_name', 'loan_processor')
                    ->update(['role_name' => 'loan_officer']);
            }

            if (Schema::hasColumn('user_role_changes', 'before_roles_json')) {
                DB::table('user_role_changes')->update([
                    'before_roles_json' => DB::raw(
                        "REPLACE(before_roles_json, 'loan_processor', 'loan_officer')",
                    ),
                ]);
            }

            if (Schema::hasColumn('user_role_changes', 'after_roles_json')) {
                DB::table('user_role_changes')->update([
                    'after_roles_json' => DB::raw(
                        "REPLACE(after_roles_json, 'loan_processor', 'loan_officer')",
                    ),
                ]);
            }
        }
    }
};
