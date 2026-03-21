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
        Schema::create('loan_request_people', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_request_id');
            $table->string('role');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('nickname')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('birthplace')->nullable();
            $table->text('address')->nullable();
            $table->string('length_of_stay')->nullable();
            $table->string('housing_status')->nullable();
            $table->string('cell_no', 20)->nullable();
            $table->string('civil_status')->nullable();
            $table->string('educational_attainment')->nullable();
            $table->unsignedTinyInteger('number_of_children')->nullable();
            $table->string('spouse_name')->nullable();
            $table->unsignedTinyInteger('spouse_age')->nullable();
            $table->string('spouse_cell_no', 20)->nullable();
            $table->string('employment_type')->nullable();
            $table->string('employer_business_name')->nullable();
            $table->text('employer_business_address')->nullable();
            $table->string('telephone_no', 20)->nullable();
            $table->string('current_position')->nullable();
            $table->string('nature_of_business')->nullable();
            $table->string('years_in_work_business')->nullable();
            $table->decimal('gross_monthly_income', 12, 2)->nullable();
            $table->string('payday')->nullable();
            $table->timestamps();

            $table->foreign('loan_request_id')
                ->references('id')
                ->on('loan_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unique(['loan_request_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_request_people');
    }
};
