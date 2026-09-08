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
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->string('health_smoking_status')->nullable()->after('beneficiary_secondary_birthdate');
            $table->text('health_smoking_status_details')->nullable()->after('health_smoking_status');
            $table->boolean('health_hypertension')->nullable()->after('health_smoking_status_details');
            $table->text('health_hypertension_details')->nullable()->after('health_hypertension');

            $table->boolean('gl_health_q01_weight_change')->nullable()->after('health_hypertension_details');
            $table->text('gl_health_q01_weight_change_details')->nullable()->after('gl_health_q01_weight_change');
            $table->boolean('gl_health_q02a_neuro')->nullable()->after('gl_health_q01_weight_change_details');
            $table->text('gl_health_q02a_neuro_details')->nullable()->after('gl_health_q02a_neuro');
            $table->boolean('gl_health_q02b_respiratory')->nullable()->after('gl_health_q02a_neuro_details');
            $table->text('gl_health_q02b_respiratory_details')->nullable()->after('gl_health_q02b_respiratory');
            $table->boolean('gl_health_q02c_cardiac')->nullable()->after('gl_health_q02b_respiratory_details');
            $table->text('gl_health_q02c_cardiac_details')->nullable()->after('gl_health_q02c_cardiac');
            $table->boolean('gl_health_q02d_digestive')->nullable()->after('gl_health_q02c_cardiac_details');
            $table->text('gl_health_q02d_digestive_details')->nullable()->after('gl_health_q02d_digestive');
            $table->boolean('gl_health_q02e_diabetes')->nullable()->after('gl_health_q02d_digestive_details');
            $table->boolean('gl_health_q02e_kidney')->nullable()->after('gl_health_q02e_diabetes');
            $table->boolean('gl_health_q02e_liver')->nullable()->after('gl_health_q02e_kidney');
            $table->boolean('gl_health_q02e_urinary')->nullable()->after('gl_health_q02e_liver');
            $table->text('gl_health_q02e_diabetes_renal_details')->nullable()->after('gl_health_q02e_urinary');
            $table->boolean('gl_health_q02f_musculoskeletal')->nullable()->after('gl_health_q02e_diabetes_renal_details');
            $table->text('gl_health_q02f_musculoskeletal_details')->nullable()->after('gl_health_q02f_musculoskeletal');
            $table->boolean('gl_health_q02g_oncology_blood')->nullable()->after('gl_health_q02f_musculoskeletal_details');
            $table->text('gl_health_q02g_oncology_blood_details')->nullable()->after('gl_health_q02g_oncology_blood');
            $table->boolean('gl_health_q02h_dermatologic')->nullable()->after('gl_health_q02g_oncology_blood_details');
            $table->text('gl_health_q02h_dermatologic_details')->nullable()->after('gl_health_q02h_dermatologic');
            $table->boolean('gl_health_q02i_std_viral')->nullable()->after('gl_health_q02h_dermatologic_details');
            $table->text('gl_health_q02i_std_viral_details')->nullable()->after('gl_health_q02i_std_viral');
            $table->boolean('gl_health_q02j_other_illness')->nullable()->after('gl_health_q02i_std_viral_details');
            $table->text('gl_health_q02j_other_illness_details')->nullable()->after('gl_health_q02j_other_illness');
            $table->boolean('gl_health_q04_prescribed_drugs')->nullable()->after('gl_health_q02j_other_illness_details');
            $table->text('gl_health_q04_prescribed_drugs_details')->nullable()->after('gl_health_q04_prescribed_drugs');
            $table->boolean('gl_health_q05_confinement_5yr')->nullable()->after('gl_health_q04_prescribed_drugs_details');
            $table->text('gl_health_q05_confinement_5yr_details')->nullable()->after('gl_health_q05_confinement_5yr');
            $table->boolean('gl_health_q06_abnormal_labs')->nullable()->after('gl_health_q05_confinement_5yr_details');
            $table->text('gl_health_q06_abnormal_labs_details')->nullable()->after('gl_health_q06_abnormal_labs');
            $table->boolean('gl_health_q07_confinement_contemplated')->nullable()->after('gl_health_q06_abnormal_labs_details');
            $table->text('gl_health_q07_confinement_contemplated_details')->nullable()->after('gl_health_q07_confinement_contemplated');
            $table->boolean('gl_health_q08_blood_transfusion')->nullable()->after('gl_health_q07_confinement_contemplated_details');
            $table->text('gl_health_q08_blood_transfusion_details')->nullable()->after('gl_health_q08_blood_transfusion');
            $table->boolean('gl_health_q09_other_disease')->nullable()->after('gl_health_q08_blood_transfusion_details');
            $table->text('gl_health_q09_other_disease_details')->nullable()->after('gl_health_q09_other_disease');
            $table->boolean('gl_health_q10_narcotics')->nullable()->after('gl_health_q09_other_disease_details');
            $table->text('gl_health_q10_narcotics_details')->nullable()->after('gl_health_q10_narcotics');
            $table->boolean('gl_health_q12_alcohol')->nullable()->after('gl_health_q10_narcotics_details');
            $table->text('gl_health_q12_alcohol_details')->nullable()->after('gl_health_q12_alcohol');
            $table->boolean('gl_health_q13_advised_stop')->nullable()->after('gl_health_q12_alcohol_details');
            $table->text('gl_health_q13_advised_stop_details')->nullable()->after('gl_health_q13_advised_stop');
            $table->boolean('gl_health_q14_current_medication')->nullable()->after('gl_health_q13_advised_stop_details');
            $table->text('gl_health_q14_current_medication_details')->nullable()->after('gl_health_q14_current_medication');
            $table->boolean('gl_health_q15_pregnancy')->nullable()->after('gl_health_q14_current_medication_details');
            $table->text('gl_health_q15_pregnancy_details')->nullable()->after('gl_health_q15_pregnancy');
            $table->boolean('gl_health_q16_relative_pep')->nullable()->after('gl_health_q15_pregnancy_details');
            $table->text('gl_health_q16_relative_pep_details')->nullable()->after('gl_health_q16_relative_pep');
            $table->boolean('gl_health_q17_pending_reinstatement')->nullable()->after('gl_health_q16_relative_pep_details');
            $table->text('gl_health_q17_pending_reinstatement_details')->nullable()->after('gl_health_q17_pending_reinstatement');
            $table->boolean('gl_health_q17_with_glapi')->nullable()->after('gl_health_q17_pending_reinstatement_details');
            $table->decimal('gl_health_q17_with_glapi_amount', 12, 2)->nullable()->after('gl_health_q17_with_glapi');
            $table->boolean('gl_health_q17_with_other_companies')->nullable()->after('gl_health_q17_with_glapi_amount');
            $table->decimal('gl_health_q17_with_other_companies_amount', 12, 2)->nullable()->after('gl_health_q17_with_other_companies');
            $table->boolean('health_recent_hospitalization')->nullable()->after('gl_health_q17_with_other_companies_amount');
            $table->boolean('applicant_pep_status')->nullable()->after('health_recent_hospitalization');
            $table->text('applicant_pep_status_details')->nullable()->after('applicant_pep_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'health_smoking_status',
                'health_smoking_status_details',
                'health_hypertension',
                'health_hypertension_details',
                'gl_health_q01_weight_change',
                'gl_health_q01_weight_change_details',
                'gl_health_q02a_neuro',
                'gl_health_q02a_neuro_details',
                'gl_health_q02b_respiratory',
                'gl_health_q02b_respiratory_details',
                'gl_health_q02c_cardiac',
                'gl_health_q02c_cardiac_details',
                'gl_health_q02d_digestive',
                'gl_health_q02d_digestive_details',
                'gl_health_q02e_diabetes',
                'gl_health_q02e_kidney',
                'gl_health_q02e_liver',
                'gl_health_q02e_urinary',
                'gl_health_q02e_diabetes_renal_details',
                'gl_health_q02f_musculoskeletal',
                'gl_health_q02f_musculoskeletal_details',
                'gl_health_q02g_oncology_blood',
                'gl_health_q02g_oncology_blood_details',
                'gl_health_q02h_dermatologic',
                'gl_health_q02h_dermatologic_details',
                'gl_health_q02i_std_viral',
                'gl_health_q02i_std_viral_details',
                'gl_health_q02j_other_illness',
                'gl_health_q02j_other_illness_details',
                'gl_health_q04_prescribed_drugs',
                'gl_health_q04_prescribed_drugs_details',
                'gl_health_q05_confinement_5yr',
                'gl_health_q05_confinement_5yr_details',
                'gl_health_q06_abnormal_labs',
                'gl_health_q06_abnormal_labs_details',
                'gl_health_q07_confinement_contemplated',
                'gl_health_q07_confinement_contemplated_details',
                'gl_health_q08_blood_transfusion',
                'gl_health_q08_blood_transfusion_details',
                'gl_health_q09_other_disease',
                'gl_health_q09_other_disease_details',
                'gl_health_q10_narcotics',
                'gl_health_q10_narcotics_details',
                'gl_health_q12_alcohol',
                'gl_health_q12_alcohol_details',
                'gl_health_q13_advised_stop',
                'gl_health_q13_advised_stop_details',
                'gl_health_q14_current_medication',
                'gl_health_q14_current_medication_details',
                'gl_health_q15_pregnancy',
                'gl_health_q15_pregnancy_details',
                'gl_health_q16_relative_pep',
                'gl_health_q16_relative_pep_details',
                'gl_health_q17_pending_reinstatement',
                'gl_health_q17_pending_reinstatement_details',
                'gl_health_q17_with_glapi',
                'gl_health_q17_with_glapi_amount',
                'gl_health_q17_with_other_companies',
                'gl_health_q17_with_other_companies_amount',
                'health_recent_hospitalization',
                'applicant_pep_status',
                'applicant_pep_status_details',
            ]);
        });
    }
};
