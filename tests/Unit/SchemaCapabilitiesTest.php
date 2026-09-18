<?php

uses(Tests\TestCase::class);

use App\Support\SchemaCapabilities;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

test('schema capabilities mirror schema checks', function () {
    if (Schema::hasTable('schema_capabilities_test')) {
        Schema::drop('schema_capabilities_test');
    }

    Schema::create('schema_capabilities_test', function (Blueprint $table) {
        $table->id();
        $table->string('value')->nullable();
    });

    $schema = app(SchemaCapabilities::class);

    expect($schema->hasTable('schema_capabilities_test'))->toBeTrue();
    expect($schema->hasColumn('schema_capabilities_test', 'value'))->toBeTrue();
    expect($schema->hasColumn('schema_capabilities_test', 'missing'))->toBeFalse();
    expect($schema->hasTable('missing_table'))->toBeFalse();

    Schema::drop('schema_capabilities_test');
});

test('schema capabilities cache results across instances, not just per-request', function () {
    if (Schema::hasTable('schema_capabilities_cache_test')) {
        Schema::drop('schema_capabilities_cache_test');
    }

    Schema::create('schema_capabilities_cache_test', function (Blueprint $table) {
        $table->id();
        $table->string('value')->nullable();
    });

    $first = new SchemaCapabilities;

    expect($first->hasTable('schema_capabilities_cache_test'))->toBeTrue();
    expect($first->hasColumn('schema_capabilities_cache_test', 'value'))->toBeTrue();

    // Drop the table directly (bypassing SchemaCapabilities) — a fresh instance
    // should still report the cached result rather than re-querying the schema.
    Schema::drop('schema_capabilities_cache_test');

    $second = new SchemaCapabilities;

    expect($second->hasTable('schema_capabilities_cache_test'))->toBeTrue();
    expect($second->hasColumn('schema_capabilities_cache_test', 'value'))->toBeTrue();

    Cache::forget('schema_capabilities:table:default|schema_capabilities_cache_test');
    Cache::forget('schema_capabilities:column:default|schema_capabilities_cache_test|value');

    $third = new SchemaCapabilities;

    expect($third->hasTable('schema_capabilities_cache_test'))->toBeFalse();
});
