<?php

uses(Tests\TestCase::class);

use App\Support\SchemaCapabilities;
use Illuminate\Database\Schema\Blueprint;
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
