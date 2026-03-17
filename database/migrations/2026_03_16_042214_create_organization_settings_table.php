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
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('company_logo_path')->nullable();
            $table->string('portal_label')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('brand_primary_color', 32)->nullable();
            $table->string('brand_accent_color', 32)->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 32)->nullable();
            $table->string('support_contact_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
