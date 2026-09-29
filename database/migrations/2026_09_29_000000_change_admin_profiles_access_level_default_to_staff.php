<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The legacy 'admin' access level was retired (see
// 2026_08_07_090000_retire_legacy_admin_role); a profile created without an
// explicit level must not default to it. 'staff' carries no privilege --
// only 'superadmin' is ever checked (AdminProfile::ACCESS_LEVEL_SUPERADMIN).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->string('access_level')->default('staff')->change();
        });
    }

    public function down(): void
    {
        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->string('access_level')->default('admin')->change();
        });
    }
};
