<?php

namespace App\Services\LoanRequests;

use App\LoanPaydayOption;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataChange;
use App\Models\LoanRequestDataEntry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LoanRequestDataService
{
    public function __construct(
        private SavedPaymentAccountsService $savedPaymentAccountsService,
    ) {}

    private const OWNER_MEMBER = 'member';

    private const OWNER_STAFF = 'staff';

    /**
     * @var array<string, array{label:string, owner:string, sensitive:bool, required_on_submit:bool, section:string, type:string, detail_of?:string|string[], visible_when?:array{field:string, equals:string|bool}, options?:string[]}>
     */
    private const FIELD_DEFINITIONS = [
        'beneficiary_primary_name' => [
            'label' => 'Primary beneficiary name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'insurance',
            'type' => 'string',
        ],
        'beneficiary_primary_relationship' => [
            'label' => 'Primary beneficiary relationship',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'insurance',
            'type' => 'string',
        ],
        'beneficiary_primary_birthdate' => [
            'label' => 'Primary beneficiary birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'insurance',
            'type' => 'date',
        ],
        'beneficiary_secondary_name' => [
            'label' => 'Secondary beneficiary name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'insurance',
            'type' => 'string',
        ],
        'beneficiary_secondary_relationship' => [
            'label' => 'Secondary beneficiary relationship',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'insurance',
            'type' => 'string',
        ],
        'beneficiary_secondary_birthdate' => [
            'label' => 'Secondary beneficiary birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'insurance',
            'type' => 'date',
        ],
        'health_smoking_status' => [
            'label' => 'Do you currently smoke cigarettes?',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'health',
            'type' => 'string',
            'options' => ['none', 'light', 'heavy'],
        ],
        'health_smoking_status_details' => [
            'label' => 'Smoking details (GLAPI Q11)',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'health_smoking_status',
        ],
        'health_hypertension' => [
            'label' => 'Have you ever been diagnosed with or treated for hypertension (high blood pressure)?',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'health',
            'type' => 'boolean',
        ],
        'health_hypertension_details' => [
            'label' => 'Hypertension details (GLAPI Q3)',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'health_hypertension',
        ],
        // GLAPI (Generali) 17-item health questionnaire. Kept in its own
        // 'health_glapi' section rather than folded into 'health' because
        // several items overlap in subject with the plain 'health' booleans
        // above but ask a materially different question (narrower/wider
        // scope, different thresholds) -- see gl_health_q02e, q05. Item 11
        // (smoking) was merged into 'health_smoking_status' above instead of
        // staying split, since a single 3-way answer covers both forms.
        'gl_health_q01_weight_change' => [
            'label' => 'Weight change of more than 5 lbs in the last 5 months',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q01_weight_change_details' => [
            'label' => 'Weight change details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q01_weight_change',
        ],
        'gl_health_q02a_neuro' => [
            'label' => 'Epilepsy, fainting, or nervous/mental disorder',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02a_neuro_details' => [
            'label' => 'Epilepsy/nervous disorder details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q02a_neuro',
        ],
        'gl_health_q02b_respiratory' => [
            'label' => 'Asthma, bronchitis, or lung problem',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02b_respiratory_details' => [
            'label' => 'Respiratory condition details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q02b_respiratory',
        ],
        'gl_health_q02c_cardiac' => [
            'label' => 'Chest pain, stroke, or heart disorder',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02c_cardiac_details' => [
            'label' => 'Cardiac condition details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q02c_cardiac',
        ],
        'gl_health_q02d_digestive' => [
            'label' => 'Indigestion, ulcer, or digestive system disorder',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02d_digestive_details' => [
            'label' => 'Digestive condition details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q02d_digestive',
        ],
        'gl_health_q02e_diabetes' => [
            'label' => 'Diabetes',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02e_kidney' => [
            'label' => 'Kidney disorder',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02e_liver' => [
            'label' => 'Liver disorder',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02e_urinary' => [
            'label' => 'Urinary disorder',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02e_diabetes_renal_details' => [
            'label' => 'Diabetes/kidney/liver/urinary condition details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => [
                'gl_health_q02e_diabetes',
                'gl_health_q02e_kidney',
                'gl_health_q02e_liver',
                'gl_health_q02e_urinary',
            ],
        ],
        'gl_health_q02f_musculoskeletal' => [
            'label' => 'Rheumatic fever, arthritis, gout, or joint/bone disorder',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02f_musculoskeletal_details' => [
            'label' => 'Joint/bone condition details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q02f_musculoskeletal',
        ],
        'gl_health_q02g_oncology_blood' => [
            'label' => 'Cancer, tumor, enlarged gland, or blood disorder',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02g_oncology_blood_details' => [
            'label' => 'Oncology/blood condition details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q02g_oncology_blood',
        ],
        'gl_health_q02h_dermatologic' => [
            'label' => 'Unexplained fever, weight loss, or skin disorder',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02h_dermatologic_details' => [
            'label' => 'Skin condition details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q02h_dermatologic',
        ],
        'gl_health_q02i_std_viral' => [
            'label' => 'Sexually transmitted or viral disease (e.g. hepatitis B, HIV/AIDS)',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02i_std_viral_details' => [
            'label' => 'STD/viral disease details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q02i_std_viral',
        ],
        'gl_health_q02j_other_illness' => [
            'label' => 'Any other illness or injury not mentioned above',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q02j_other_illness_details' => [
            'label' => 'Other illness/injury details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q02j_other_illness',
        ],
        'gl_health_q04_prescribed_drugs' => [
            'label' => 'Prescribed drugs for any of the above conditions',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q04_prescribed_drugs_details' => [
            'label' => 'Prescribed drugs details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q04_prescribed_drugs',
        ],
        'gl_health_q05_confinement_5yr' => [
            'label' => 'Hospital/nursing home confinement or surgery in the last 5 years',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q05_confinement_5yr_details' => [
            'label' => 'Confinement/surgery details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q05_confinement_5yr',
        ],
        // Distinct from gl_health_q05_confinement_5yr above (which asks about
        // a 5-year window): this is GREPALIFE's own recent-hospitalization
        // check with no documented time window, kept as its own item
        // immediately after item 5 rather than merged into it.
        'health_recent_hospitalization' => [
            'label' => 'Have you been confined in a hospital or undergone surgery recently?',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q06_abnormal_labs' => [
            'label' => 'Abnormal laboratory or diagnostic test results',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q06_abnormal_labs_details' => [
            'label' => 'Abnormal test result details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q06_abnormal_labs',
        ],
        'gl_health_q07_confinement_contemplated' => [
            'label' => 'Hospital confinement or surgery being contemplated',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q07_confinement_contemplated_details' => [
            'label' => 'Contemplated confinement/surgery details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q07_confinement_contemplated',
        ],
        'gl_health_q08_blood_transfusion' => [
            'label' => 'Blood products or blood transfusion received',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q08_blood_transfusion_details' => [
            'label' => 'Blood transfusion details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q08_blood_transfusion',
        ],
        'gl_health_q09_other_disease' => [
            'label' => 'Any other disease or complaint not mentioned above',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q09_other_disease_details' => [
            'label' => 'Other disease/complaint details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q09_other_disease',
        ],
        'gl_health_q10_narcotics' => [
            'label' => 'Use of shabu, cocaine, heroin, marijuana, or other narcotics',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q10_narcotics_details' => [
            'label' => 'Narcotics use details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q10_narcotics',
        ],
        'gl_health_q12_alcohol' => [
            'label' => 'Drinks more than 6 units of alcohol per day',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q12_alcohol_details' => [
            'label' => 'Alcohol use details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q12_alcohol',
        ],
        'gl_health_q13_advised_stop' => [
            'label' => 'Advised by a physician to stop smoking or drinking',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q13_advised_stop_details' => [
            'label' => 'Physician advice details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q13_advised_stop',
        ],
        'gl_health_q14_current_medication' => [
            'label' => 'Currently taking medication or under medical care',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q14_current_medication_details' => [
            'label' => 'Current medication/care details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q14_current_medication',
        ],
        'gl_health_q15_pregnancy' => [
            'label' => 'Pregnant or experiencing pregnancy complications',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
            'visible_when' => ['field' => 'applicant.sex', 'equals' => 'Female'],
        ],
        'gl_health_q15_pregnancy_details' => [
            'label' => 'Pregnancy details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q15_pregnancy',
            'visible_when' => ['field' => 'applicant.sex', 'equals' => 'Female'],
        ],
        'gl_health_q16_relative_pep' => [
            'label' => 'Relative holds/held a senior government, political, or military position',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q16_relative_pep_details' => [
            'label' => 'Relative PEP details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q16_relative_pep',
        ],
        'gl_health_q17_pending_reinstatement' => [
            'label' => 'Pending application for reinstatement of life insurance',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'gl_health_q17_pending_reinstatement_details' => [
            'label' => 'Pending reinstatement details',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'gl_health_q17_pending_reinstatement',
        ],
        'gl_health_q17_with_glapi' => [
            'label' => 'Pending reinstatement with GLAPI',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
            'detail_of' => 'gl_health_q17_pending_reinstatement',
        ],
        'gl_health_q17_with_glapi_amount' => [
            'label' => 'Pending reinstatement amount with GLAPI',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'number',
            'detail_of' => 'gl_health_q17_with_glapi',
        ],
        'gl_health_q17_with_other_companies' => [
            'label' => 'Pending reinstatement with other companies',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
            'detail_of' => 'gl_health_q17_pending_reinstatement',
        ],
        'gl_health_q17_with_other_companies_amount' => [
            'label' => 'Pending reinstatement amount with other companies',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'number',
            'detail_of' => 'gl_health_q17_with_other_companies',
        ],
        // Generali Individual Application Form's own PEP question ("Are you a
        // Politically Exposed Person?") -- distinct from gl_health_q16_relative_pep,
        // which asks about a relative, not the applicant. Self-attested by the
        // applicant like the other GLAPI health questions.
        'applicant_pep_status' => [
            'label' => 'Applicant is a Politically Exposed Person (PEP)',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'boolean',
        ],
        'applicant_pep_status_details' => [
            'label' => 'PEP role, function, and date assumed',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'health_glapi',
            'type' => 'string',
            'detail_of' => 'applicant_pep_status',
        ],

        'release_method' => [
            'label' => 'Release method',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'banking',
            'type' => 'string',
        ],
        'release_saved_account_id' => [
            'label' => 'Release saved account',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'banking',
            'type' => 'integer',
        ],
        'payment_option' => [
            'label' => 'Payment option',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'banking',
            'type' => 'string',
        ],
        'payment_saved_account_id' => [
            'label' => 'Payment saved account',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'banking',
            'type' => 'integer',
        ],

        'declaration_existing_loans' => [
            'label' => 'Do you have any previous or existing loan(s)?',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'declarations',
            'type' => 'boolean',
        ],
        'declaration_pending_cases' => [
            'label' => 'Do you have any pending case(s) filed against you?',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'declarations',
            'type' => 'boolean',
        ],

        // Existing/previous loan detail rows (GLAPI section 1.1). Fixed
        // slots for the same reason as Dependents (see comment above) --
        // 3 slots to match both the PDF table's printed row capacity and
        // the Beneficiaries 2-slot precedent's pattern of a small fixed
        // cap rather than a truly dynamic array. Gated by
        // declaration_existing_loans via visible_when, same mechanism as
        // the civil_status-gated dependent slots.
        'existing_loan_1_date' => [
            'label' => 'Existing loan 1 date',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'declarations',
            'type' => 'date',
            'visible_when' => ['field' => 'declaration_existing_loans', 'equals' => true],
        ],
        'existing_loan_1_type' => [
            'label' => 'Existing loan 1 type',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'declarations',
            'type' => 'string',
            'visible_when' => ['field' => 'declaration_existing_loans', 'equals' => true],
        ],
        'existing_loan_1_amount' => [
            'label' => 'Existing loan 1 amount',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'declarations',
            'type' => 'number',
            'visible_when' => ['field' => 'declaration_existing_loans', 'equals' => true],
        ],
        'existing_loan_2_date' => [
            'label' => 'Existing loan 2 date',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'declarations',
            'type' => 'date',
            'visible_when' => ['field' => 'declaration_existing_loans', 'equals' => true],
        ],
        'existing_loan_2_type' => [
            'label' => 'Existing loan 2 type',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'declarations',
            'type' => 'string',
            'visible_when' => ['field' => 'declaration_existing_loans', 'equals' => true],
        ],
        'existing_loan_2_amount' => [
            'label' => 'Existing loan 2 amount',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'declarations',
            'type' => 'number',
            'visible_when' => ['field' => 'declaration_existing_loans', 'equals' => true],
        ],
        'existing_loan_3_date' => [
            'label' => 'Existing loan 3 date',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'declarations',
            'type' => 'date',
            'visible_when' => ['field' => 'declaration_existing_loans', 'equals' => true],
        ],
        'existing_loan_3_type' => [
            'label' => 'Existing loan 3 type',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'declarations',
            'type' => 'string',
            'visible_when' => ['field' => 'declaration_existing_loans', 'equals' => true],
        ],
        'existing_loan_3_amount' => [
            'label' => 'Existing loan 3 amount',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'declarations',
            'type' => 'number',
            'visible_when' => ['field' => 'declaration_existing_loans', 'equals' => true],
        ],
        'declaration_truth_confirmation' => [
            'label' => 'Truthfulness declaration',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'declarations',
            'type' => 'boolean',
        ],
        'declaration_data_privacy_consent' => [
            'label' => 'Data-privacy consent',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => true,
            'section' => 'declarations',
            'type' => 'boolean',
        ],
        'service_charge_rate' => [
            'label' => 'Service charge rate',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'insurance_rate' => [
            'label' => 'Insurance rate',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'insurance_term' => [
            'label' => 'Insurance term',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'integer',
        ],
        'loan_security_rate' => [
            'label' => 'Loan security rate',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'savings_rate' => [
            'label' => 'Savings rate',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'documentary_stamp_rate' => [
            'label' => 'Documentary stamp rate',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'notarial_fee' => [
            'label' => 'Notarial fee',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'other_charges_amount' => [
            'label' => 'Other charges amount',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'other_charges_description' => [
            'label' => 'Other charges description',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'guaranteed_net_take_home_pay' => [
            'label' => 'Guaranteed net take-home pay',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'penalty_rate_per_month' => [
            'label' => 'Penalty rate per month',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'witness_one_name' => [
            'label' => 'Witness one name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'witness_two_name' => [
            'label' => 'Witness two name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'witness_two_id' => [
            'label' => 'Witness two ID',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'integer',
        ],
        'barangay_official_name' => [
            'label' => 'Barangay official name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'barangay_official_title' => [
            'label' => 'Barangay official title',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'barangay_official_designation' => [
            'label' => 'Barangay official designation',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'barangay_agency_name' => [
            'label' => 'Agency name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'barangay_agency_address' => [
            'label' => 'Agency address',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'authority_to_deduct_institution_name' => [
            'label' => 'Authority to Deduct: institution name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'authority_to_deduct_officer_1_name' => [
            'label' => 'Authority to Deduct: officer 1 name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'authority_to_deduct_officer_1_title' => [
            'label' => 'Authority to Deduct: officer 1 title',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'authority_to_deduct_officer_2_name' => [
            'label' => 'Authority to Deduct: officer 2 name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'authority_to_deduct_officer_2_title' => [
            'label' => 'Authority to Deduct: officer 2 title',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'authority_to_deduct_officers_unknown' => [
            'label' => 'Authority to Deduct: officer information not yet known',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'boolean',
        ],
        'deped_school_id_number' => [
            'label' => 'DepEd School I.D. number',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'deped_deduction_amount' => [
            'label' => 'DepEd salary deduction amount',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'pension_provider' => [
            'label' => 'Pension provider',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'pension_bank_name' => [
            'label' => 'Pension bank name',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'pension_atm_card_number' => [
            'label' => 'Pension ATM card number',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'string',
        ],
        'pension_deduction_amount' => [
            'label' => 'Pension deduction amount',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'number',
        ],
        'employer_date_employed' => [
            'label' => 'Date employed',
            'owner' => self::OWNER_STAFF,
            'sensitive' => false,
            'required_on_submit' => false,
            'section' => 'processing',
            'type' => 'date',
        ],
        // Dependents (Form B). Fixed slots per category rather than a truly
        // dynamic array because this EAV field system needs stable field
        // keys -- caps (child x3, sibling x3, parent x2, extended x3) are
        // provisional. cycle_status ('New'|'Old') is required once the slot
        // it belongs to is actually in use (name filled in, or married for
        // Spouse, or unconditionally for Applicant) and cycle_number is
        // required only when 'Old' -- both enforced by
        // LoanRequestStoreRequest, not reflected in required_on_submit below
        // since that flag can't express "conditional". Mirrors the physical
        // form's per-entity cycle field, including for Spouse (a singleton,
        // not a repeatable slot). Relationship and Occupation were removed:
        // the physical form has no such columns for any
        // dependent category.
        'dependent_child_1_name' => [
            'label' => 'Child 1 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_1_birthdate' => [
            'label' => 'Child 1 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_1_cycle_status' => [
            'label' => 'Child 1 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_1_cycle_number' => [
            'label' => 'Child 1 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_2_name' => [
            'label' => 'Child 2 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_2_birthdate' => [
            'label' => 'Child 2 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_2_cycle_status' => [
            'label' => 'Child 2 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_2_cycle_number' => [
            'label' => 'Child 2 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_3_name' => [
            'label' => 'Child 3 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_3_birthdate' => [
            'label' => 'Child 3 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_3_cycle_status' => [
            'label' => 'Child 3 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_child_3_cycle_number' => [
            'label' => 'Child 3 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_sibling_1_name' => [
            'label' => 'Sibling 1 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_1_birthdate' => [
            'label' => 'Sibling 1 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_1_cycle_status' => [
            'label' => 'Sibling 1 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_1_cycle_number' => [
            'label' => 'Sibling 1 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_2_name' => [
            'label' => 'Sibling 2 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_2_birthdate' => [
            'label' => 'Sibling 2 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_2_cycle_status' => [
            'label' => 'Sibling 2 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_2_cycle_number' => [
            'label' => 'Sibling 2 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_3_name' => [
            'label' => 'Sibling 3 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_3_birthdate' => [
            'label' => 'Sibling 3 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_3_cycle_status' => [
            'label' => 'Sibling 3 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_sibling_3_cycle_number' => [
            'label' => 'Sibling 3 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_parent_1_name' => [
            'label' => 'Parent 1 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_parent_1_birthdate' => [
            'label' => 'Parent 1 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_parent_1_cycle_status' => [
            'label' => 'Parent 1 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_parent_1_cycle_number' => [
            'label' => 'Parent 1 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_parent_2_name' => [
            'label' => 'Parent 2 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_parent_2_birthdate' => [
            'label' => 'Parent 2 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_parent_2_cycle_status' => [
            'label' => 'Parent 2 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_parent_2_cycle_number' => [
            'label' => 'Parent 2 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Single'],
        ],
        'dependent_extended_1_name' => [
            'label' => 'Extended family member 1 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
        ],
        'dependent_extended_1_birthdate' => [
            'label' => 'Extended family member 1 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
        ],
        'dependent_extended_1_cycle_status' => [
            'label' => 'Extended family member 1 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
        ],
        'dependent_extended_1_cycle_number' => [
            'label' => 'Extended family member 1 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
        ],
        'dependent_extended_2_name' => [
            'label' => 'Extended family member 2 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
        ],
        'dependent_extended_2_birthdate' => [
            'label' => 'Extended family member 2 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
        ],
        'dependent_extended_2_cycle_status' => [
            'label' => 'Extended family member 2 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
        ],
        'dependent_extended_2_cycle_number' => [
            'label' => 'Extended family member 2 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
        ],
        'dependent_extended_3_name' => [
            'label' => 'Extended family member 3 name',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
        ],
        'dependent_extended_3_birthdate' => [
            'label' => 'Extended family member 3 birthdate',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'date',
        ],
        'dependent_extended_3_cycle_status' => [
            'label' => 'Extended family member 3 cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
        ],
        'dependent_extended_3_cycle_number' => [
            'label' => 'Extended family member 3 cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
        ],
        'dependent_spouse_cycle_status' => [
            'label' => 'Spouse cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        'dependent_spouse_cycle_number' => [
            'label' => 'Spouse cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
            'visible_when' => ['field' => 'applicant.civil_status', 'equals' => 'Married'],
        ],
        // Applicant's own enrollment cycle -- same New/Old + cycle-number
        // concept as the per-dependent cycle fields above, but for the
        // applicant. No longer collected from the member/wizard: it's
        // auto-computed from wlnmaster loan history via
        // LoanRequestCycleStateService and read directly from there by
        // ApprovedLoanDocumentDataBuilder. Kept here for the audit-trail
        // field-label lookup and legacy stored values only.
        'applicant_cycle_status' => [
            'label' => 'Applicant cycle status',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'string',
        ],
        'applicant_cycle_number' => [
            'label' => 'Applicant cycle number',
            'owner' => self::OWNER_MEMBER,
            'sensitive' => true,
            'required_on_submit' => false,
            'section' => 'dependents',
            'type' => 'number',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const SECTION_LABELS = [
        'insurance' => 'Insurance and beneficiaries',
        'health' => 'Health Insurance Questionnaire',
        'health_glapi' => 'Health Insurance Questionnaire',
        'banking' => 'Bank and payout information',
        'declarations' => 'Personal declarations and consent',
        'processing' => 'Processing details',
        'dependents' => 'Dependents',
    ];

    /**
     * @return array<string, array{label:string, fields:array<string, array{label:string, sensitive:bool, owner:string, type:string}>}>
     */
    public function sectionDefinitions(): array
    {
        $sections = [];

        foreach (self::SECTION_LABELS as $sectionKey => $label) {
            $sections[$sectionKey] = [
                'label' => $label,
                'fields' => [],
            ];
        }

        foreach (self::FIELD_DEFINITIONS as $fieldKey => $definition) {
            $sections[$definition['section']]['fields'][$fieldKey] = [
                'label' => $definition['label'],
                'sensitive' => $definition['sensitive'],
                'owner' => $definition['owner'],
                'type' => $definition['type'],
                'detail_of' => $definition['detail_of'] ?? null,
                'visible_when' => $definition['visible_when'] ?? null,
            ];
        }

        return $sections;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function emptySections(): array
    {
        $sections = [];

        foreach ($this->sectionDefinitions() as $sectionKey => $definition) {
            $sectionValues = [];

            foreach (array_keys($definition['fields']) as $fieldKey) {
                $sectionValues[$fieldKey] = null;
            }

            $sections[$sectionKey] = $sectionValues;
        }

        return $sections;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function serializeSections(LoanRequest $loanRequest): array
    {
        $flatValues = $this->loadFlatValues($loanRequest);
        $sections = $this->emptySections();

        foreach ($this->sectionDefinitions() as $sectionKey => $definition) {
            foreach (array_keys($definition['fields']) as $fieldKey) {
                if (array_key_exists($fieldKey, $flatValues)) {
                    $sections[$sectionKey][$fieldKey] = $flatValues[$fieldKey];
                }
            }
        }

        $sections['banking']['release_account_detail'] = $this->savedPaymentAccountDetails(
            $loanRequest,
            $sections['banking']['release_saved_account_id'] ?? null,
        );
        $sections['banking']['payment_account_detail'] = $this->savedPaymentAccountDetails(
            $loanRequest,
            $sections['banking']['payment_saved_account_id'] ?? null,
        );

        $institutionName = $sections['processing']['authority_to_deduct_institution_name'] ?? null;

        if (! is_string($institutionName) || trim($institutionName) === '') {
            $sections['processing']['authority_to_deduct_institution_name'] =
                (new LoanRequestDocumentCatalog)->suggestedAuthorityToDeductInstitutionName(
                    $loanRequest,
                    $flatValues,
                );
        }

        return $sections;
    }

    /**
     * Resolves the member's saved payment account of record for a loan request
     * into its printable fields so staff/admin/client screens can render the
     * resolved bank account details without holding the accounts list. The
     * frozen approval snapshot (loanRequest.account_snapshot) remains the
     * preferred source once a request is approved.
     *
     * @return array<string, string|null>|null
     */
    private function savedPaymentAccountDetails(
        LoanRequest $loanRequest,
        mixed $accountId,
    ): ?array {
        if (! is_numeric($accountId)) {
            return null;
        }

        $profile = $loanRequest->user?->memberApplicationProfile;

        if ($profile === null) {
            return null;
        }

        return $this->savedPaymentAccountsService->resolveAccountDetails(
            $profile,
            (int) $accountId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function loadFlatValues(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('dataEntries');

        $values = [];

        foreach ($loanRequest->dataEntries as $entry) {
            $values[$entry->field_key] = $this->entryValue($entry);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncMemberSections(
        LoanRequest $loanRequest,
        array $payload,
    ): void {
        foreach ($this->sectionDefinitions() as $sectionKey => $definition) {
            if ($this->sectionOwner($sectionKey) !== self::OWNER_MEMBER) {
                continue;
            }

            $sectionPayload = $payload[$sectionKey] ?? null;

            if (! is_array($sectionPayload)) {
                continue;
            }

            foreach (array_keys($definition['fields']) as $fieldKey) {
                if (! array_key_exists($fieldKey, $sectionPayload)) {
                    continue;
                }

                $this->persistField(
                    $loanRequest,
                    $fieldKey,
                    $sectionPayload[$fieldKey],
                    confirmedByMember: true,
                    confirmedAt: now(),
                );
            }
        }
    }

    /**
     * @var list<string>
     */
    private const MEMBER_PAYMENT_METHOD_FIELDS = [
        'release_method',
        'release_saved_account_id',
        'payment_option',
        'payment_saved_account_id',
    ];

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateMemberManagedPaymentMethodFields(LoanRequest $loanRequest, array $fields): void
    {
        foreach ($fields as $fieldKey => $value) {
            if (! in_array($fieldKey, self::MEMBER_PAYMENT_METHOD_FIELDS, true)) {
                continue;
            }

            $this->persistField(
                $loanRequest,
                $fieldKey,
                $value,
                confirmedByMember: true,
                confirmedAt: now(),
            );
        }
    }

    /**
     * Sections whose required fields are waived when the member requested a
     * 1-month Lumpsum repayment -- the insurance/health questionnaire is
     * skipped in the wizard for that choice (see
     * LoanRequestStoreRequest::isDueDateNoInsuranceRequested()).
     *
     * @var list<string>
     */
    private const DUE_DATE_WAIVED_SECTIONS = ['insurance', 'health', 'dependents'];

    public function missingRequiredMemberFields(LoanRequest $loanRequest): array
    {
        $flatValues = $this->loadFlatValues($loanRequest);
        $missing = [];
        $isDueDateNoInsurance = $loanRequest->requested_payment_frequency === LoanPaydayOption::DueDate->value
            && (int) $loanRequest->requested_term === 1;

        foreach (self::FIELD_DEFINITIONS as $fieldKey => $definition) {
            if (
                $definition['owner'] !== self::OWNER_MEMBER
                || ! $definition['required_on_submit']
            ) {
                continue;
            }

            if (
                $isDueDateNoInsurance
                && in_array($definition['section'], self::DUE_DATE_WAIVED_SECTIONS, true)
            ) {
                continue;
            }

            $value = $flatValues[$fieldKey] ?? null;

            if ($definition['type'] === 'boolean') {
                if (! is_bool($value)) {
                    $missing[] = $fieldKey;
                }

                continue;
            }

            if ($value === null || trim((string) $value) === '') {
                $missing[] = $fieldKey;
            }
        }

        if (
            ($flatValues['beneficiary_secondary_name'] ?? null) !== null
            && trim((string) ($flatValues['beneficiary_secondary_name'] ?? '')) !== ''
        ) {
            foreach ([
                'beneficiary_secondary_relationship',
                'beneficiary_secondary_birthdate',
            ] as $fieldKey) {
                $value = $flatValues[$fieldKey] ?? null;

                if ($value === null || trim((string) $value) === '') {
                    $missing[] = $fieldKey;
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @return list<string>
     */
    public function unconfirmedSensitiveFields(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('dataEntries');

        return $loanRequest->dataEntries
            ->filter(
                static fn (LoanRequestDataEntry $entry): bool => $entry->is_sensitive
                    && ! $entry->confirmed_by_member,
            )
            ->pluck('field_key')
            ->filter(static fn (mixed $fieldKey): bool => is_string($fieldKey))
            ->values()
            ->all();
    }

    public function hasUnconfirmedSensitiveFields(LoanRequest $loanRequest): bool
    {
        return $this->unconfirmedSensitiveFields($loanRequest) !== [];
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return list<string>
     */
    public function applyStaffUpdates(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $updates,
        string $reason,
    ): array {
        $normalizedReason = trim($reason);

        $changedFields = [];

        foreach ($updates as $fieldKey => $value) {
            if (! is_string($fieldKey) || ! isset(self::FIELD_DEFINITIONS[$fieldKey])) {
                continue;
            }

            $definition = self::FIELD_DEFINITIONS[$fieldKey];
            $entry = $this->resolveFieldEntry($loanRequest, $fieldKey);
            $before = $entry !== null ? $this->entryValue($entry) : null;
            $after = $this->normalizeFieldValue($fieldKey, $value);

            if ($before === $after) {
                continue;
            }

            // A sensitive field with no prior entry was never captured by the
            // member's own submission (e.g. a field added after this request
            // was already in flight) -- treat it as a legacy backfill and
            // trust the staff-relayed answer. A sensitive field that already
            // has an entry still requires the member to reconfirm any edit.
            $isLegacyBackfill = $definition['sensitive'] && $entry === null;
            $confirmedByMember = match (true) {
                ! $definition['sensitive'] => $entry?->confirmed_by_member ?? false,
                $isLegacyBackfill => true,
                default => false,
            };
            $confirmedAt = match (true) {
                ! $definition['sensitive'] => $entry?->confirmed_by_member_at,
                $isLegacyBackfill => now(),
                default => null,
            };

            $entry = $this->persistField(
                $loanRequest,
                $fieldKey,
                $after,
                ownerType: $definition['owner'],
                confirmedByMember: $confirmedByMember,
                confirmedAt: $confirmedAt,
            );

            LoanRequestDataChange::query()->create([
                'loan_request_id' => $loanRequest->id,
                'actor_user_id' => $actor->user_id,
                'field_key' => $fieldKey,
                'before_value_json' => ['value' => $before],
                'after_value_json' => ['value' => $after],
                'reason' => $normalizedReason !== ''
                    ? $normalizedReason
                    : 'Updated '.$definition['label'],
                'information_source' => '',
                'metadata_json' => [
                    'section' => $definition['section'],
                    'owner_type' => $entry->owner_type,
                    'confirmed_by_member' => $entry->confirmed_by_member,
                ],
            ]);

            $changedFields[] = $fieldKey;
        }

        return array_values(array_unique($changedFields));
    }

    /**
     * Writes a system-derived value for a current field definition, bypassing
     * the staff-correction audit trail. For one-time data migrations only
     * (e.g. backfilling a redesigned field from a retired one) -- not a
     * substitute for applyStaffUpdates() when a human is correcting a value.
     */
    public function backfillField(
        LoanRequest $loanRequest,
        string $fieldKey,
        mixed $value,
        bool $confirmedByMember = false,
    ): LoanRequestDataEntry {
        return $this->persistField($loanRequest, $fieldKey, $value, confirmedByMember: $confirmedByMember);
    }

    /**
     * @param  list<string>  $fieldKeys
     * @return array<int, array{field:string, label:string}>
     */
    public function fieldDescriptors(array $fieldKeys): array
    {
        $descriptors = [];

        foreach ($fieldKeys as $fieldKey) {
            if (! isset(self::FIELD_DEFINITIONS[$fieldKey])) {
                continue;
            }

            $descriptors[] = [
                'field' => $fieldKey,
                'label' => self::FIELD_DEFINITIONS[$fieldKey]['label'],
            ];
        }

        return $descriptors;
    }

    public function fieldLabel(string $fieldKey): string
    {
        return self::FIELD_DEFINITIONS[$fieldKey]['label'] ?? $fieldKey;
    }

    public function isSensitiveField(string $fieldKey): bool
    {
        return (bool) (self::FIELD_DEFINITIONS[$fieldKey]['sensitive'] ?? false);
    }

    /**
     * @return list<string>
     */
    public function processingFieldKeys(): array
    {
        return array_keys(array_filter(
            self::FIELD_DEFINITIONS,
            static fn (array $definition): bool => $definition['section'] === 'processing',
        ));
    }

    private function sectionOwner(string $sectionKey): string
    {
        foreach (self::FIELD_DEFINITIONS as $definition) {
            if ($definition['section'] === $sectionKey) {
                return $definition['owner'];
            }
        }

        return self::OWNER_MEMBER;
    }

    private function resolveFieldEntry(
        LoanRequest $loanRequest,
        string $fieldKey,
    ): ?LoanRequestDataEntry {
        $loanRequest->loadMissing('dataEntries');

        return $loanRequest->dataEntries
            ->first(
                static fn (LoanRequestDataEntry $entry): bool => $entry->field_key === $fieldKey,
            );
    }

    private function entryValue(LoanRequestDataEntry $entry): mixed
    {
        $value = $entry->value_json;

        if (! is_array($value)) {
            return null;
        }

        return $value['value'] ?? null;
    }

    private function persistField(
        LoanRequest $loanRequest,
        string $fieldKey,
        mixed $value,
        ?string $ownerType = null,
        bool $confirmedByMember = false,
        Carbon|string|null $confirmedAt = null,
        ?AppUser $updatedByStaff = null,
    ): LoanRequestDataEntry {
        $definition = self::FIELD_DEFINITIONS[$fieldKey] ?? null;

        if ($definition === null) {
            throw ValidationException::withMessages([
                'field' => 'The selected processing field is not supported.',
            ]);
        }

        /** @var LoanRequestDataEntry $entry */
        $entry = LoanRequestDataEntry::query()->firstOrNew([
            'loan_request_id' => $loanRequest->id,
            'field_key' => $fieldKey,
        ]);

        $entry->section_key = $definition['section'];
        $entry->owner_type = $ownerType ?? $definition['owner'];
        $entry->is_sensitive = $definition['sensitive'];
        $entry->confirmed_by_member = $confirmedByMember;
        $entry->confirmed_by_member_at = $confirmedByMember
            ? ($confirmedAt instanceof Carbon ? $confirmedAt : ($confirmedAt !== null ? Carbon::parse($confirmedAt) : now()))
            : null;
        $entry->value_json = ['value' => $this->normalizeFieldValue($fieldKey, $value)];
        $entry->metadata_json = [
            'label' => $definition['label'],
            'type' => $definition['type'],
            ...($updatedByStaff !== null ? [
                'updated_by_actor_type' => 'staff',
                'updated_by_user_id' => $updatedByStaff->user_id,
            ] : []),
        ];
        $entry->save();

        return $entry;
    }

    private function normalizeFieldValue(string $fieldKey, mixed $value): mixed
    {
        $definition = self::FIELD_DEFINITIONS[$fieldKey] ?? null;

        if ($definition === null) {
            return $value;
        }

        if ($definition['type'] === 'boolean') {
            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));

                if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                    return true;
                }

                if (in_array($normalized, ['0', 'false', 'no'], true)) {
                    return false;
                }
            }

            if (is_numeric($value)) {
                return (int) $value === 1;
            }

            return null;
        }

        if ($definition['type'] === 'integer') {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return (int) $value;
        }

        if ($definition['type'] === 'number') {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return trim((string) $value);
        }

        if ($definition['type'] === 'date') {
            if ($value === null) {
                return null;
            }

            $normalized = trim((string) $value);

            return $normalized !== '' ? $normalized : null;
        }

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
