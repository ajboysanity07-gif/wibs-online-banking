<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('performance indexes migration adds expected indexes', function () {
    if (! Schema::hasTable('wlnled')) {
        Schema::create('wlnled', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->dateTime('date_in')->nullable();
        });
    }

    if (! Schema::hasTable('wsavled')) {
        Schema::create('wsavled', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('svnumber');
            $table->integer('typecode')->default(0);
            $table->dateTime('date_in')->nullable();
        });
    }

    if (! Schema::hasTable('wsvmaster')) {
        Schema::create('wsvmaster', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('svnumber');
            $table->integer('typecode')->default(0);
            $table->dateTime('lastmove')->nullable();
        });
    }

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->dateTime('lastmove')->nullable();
            $table->dateTime('dateopen')->nullable();
        });
    }

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno');
        });
    }

    if (! Schema::hasTable('Amortsched')) {
        Schema::create('Amortsched', function (Blueprint $table) {
            $table->string('lnnumber');
            $table->dateTime('Date_pay')->nullable();
        });
    }

    $migration = require database_path(
        'migrations/2026_03_17_031200_add_performance_indexes_to_admin_tables.php',
    );
    $migration->up();

    expect(Schema::hasIndex('wlnled', 'idx_wlnled_acctno_lnnumber_date_in'))
        ->toBeTrue();
    expect(Schema::hasIndex('wsavled', 'idx_wsavled_acctno_typecode_date_in'))
        ->toBeTrue();
    expect(Schema::hasIndex('wsavled', 'idx_wsavled_acctno_svnumber'))
        ->toBeTrue();
    expect(Schema::hasIndex('wsvmaster', 'idx_wsvmaster_acctno_svnumber'))
        ->toBeTrue();
    expect(Schema::hasIndex('wsvmaster', 'idx_wsvmaster_acctno_typecode_lastmove'))
        ->toBeTrue();
    expect(Schema::hasIndex('wlnmaster', 'idx_wlnmaster_acctno_lnnumber'))
        ->toBeTrue();
    expect(Schema::hasIndex('wlnmaster', 'idx_wlnmaster_acctno_lastmove'))
        ->toBeTrue();
    expect(Schema::hasIndex('wmaster', 'idx_wmaster_acctno'))
        ->toBeTrue();
    expect(Schema::hasIndex('Amortsched', 'idx_amortsched_lnnumber_date_pay'))
        ->toBeTrue();
    expect(Schema::hasIndex('user_profiles', 'idx_user_profiles_status_user_id'))
        ->toBeTrue();
    expect(Schema::hasIndex('appusers', 'idx_appusers_created_at'))
        ->toBeTrue();
});
