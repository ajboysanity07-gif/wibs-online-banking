export type LoanTypeOption = {
    typecode: string;
    label: string;
};

export type LoanRequestMemberSummary = {
    name: string;
    acctno: string | null;
};

export type LoanRequestPersonData = {
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    nickname: string | null;
    birthdate: string | null;
    birthplace: string | null;
    birthplace_city: string | null;
    birthplace_province: string | null;
    address: string | null;
    address1: string | null;
    address_barangay: string | null;
    address2: string | null;
    address3: string | null;
    address_zip: string | null;
    length_of_stay: string | null;
    housing_status: string | null;
    cell_no: string | null;
    civil_status: string | null;
    sex: string | null;
    educational_attainment: string | null;
    number_of_children: number | string | null;
    spouse_name: string | null;
    spouse_age: number | string | null;
    spouse_cell_no: string | null;
    employment_type: string | null;
    employer_business_name: string | null;
    employer_business_address: string | null;
    employer_business_address1: string | null;
    employer_business_address_barangay: string | null;
    employer_business_address2: string | null;
    employer_business_address3: string | null;
    employer_business_address_zip: string | null;
    telephone_no: string | null;
    current_position: string | null;
    nature_of_business: string | null;
    years_in_work_business: string | null;
    employer_date_employed: string | null;
    gross_monthly_income: string | null;
    payday: string | null;
};

export type SavedCoMakerOption = {
    id: number;
    label: string;
    last_used_at: string | null;
};

export type LoanRequestPersonFormData = {
    first_name: string;
    middle_name: string;
    last_name: string;
    nickname: string;
    birthdate: string;
    birthplace_city: string;
    birthplace_province: string;
    address1: string;
    address_barangay: string;
    address2: string;
    address3: string;
    address_zip: string;
    length_of_stay: string;
    housing_status: string;
    cell_no: string;
    civil_status: string;
    sex: string;
    educational_attainment: string;
    number_of_children: string;
    spouse_name: string;
    spouse_age: string;
    spouse_cell_no: string;
    employment_type: string;
    employer_business_name: string;
    employer_business_address1: string;
    employer_business_address_barangay: string;
    employer_business_address2: string;
    employer_business_address3: string;
    employer_business_address_zip: string;
    telephone_no: string;
    current_position: string;
    nature_of_business: string;
    years_in_work_business: string;
    employer_date_employed: string;
    gross_monthly_income: string;
    payday: string;
    // Saved co-maker reuse (co_maker_1/co_maker_2 only, unused for
    // applicant) -- see SavedCoMakersService.
    save_for_reuse: boolean;
    saved_co_maker_id: string;
    saved_co_maker_label: string;
};

export type LoanRequestReviewer = {
    user_id: number;
    name: string;
    display_code?: string;
};

export type LoanManagerOption = {
    id: number;
    name: string;
    active_loans: number;
};

export type LoanRequestAssignmentState =
    | 'unassigned'
    | 'assigned_to_me'
    | 'assigned_to_other';

export type LoanRequestAssignmentOfficerOption = {
    user_id: number;
    name: string;
    display_code: string;
    username: string | null;
    active_assignment_count: number;
    has_workload_warning: boolean;
    workload_warning_label: string | null;
};

export type LoanRequestCorrectionReportStatus =
    | 'open'
    | 'resolved'
    | 'dismissed';

export type LoanRequestCorrectionReportUser = {
    user_id: number;
    name: string;
    acctno?: string | null;
};

export type LoanRequestCorrectionReport = {
    id: number;
    loan_request_id: number;
    status: LoanRequestCorrectionReportStatus;
    issue_description: string;
    correct_information: string;
    supporting_note: string | null;
    admin_notes: string | null;
    reported_at: string | null;
    reported_by: LoanRequestCorrectionReportUser | null;
    resolved_by: LoanRequestReviewer | null;
    resolved_at: string | null;
    dismissed_by: LoanRequestReviewer | null;
    dismissed_at: string | null;
};

export type LoanRequestCorrectionReportPayload = {
    issue_description: string;
    correct_information: string;
    supporting_note?: string | null;
};

export type LoanRequestCorrectionReportDismissPayload = {
    admin_notes?: string | null;
};

export type LoanRequestReadOnlyMap = Record<string, boolean>;

export type LoanRequestWorkflowVersion = 'legacy_v1' | 'document_workflow_v2';

export type LoanRequestStatusValue =
    | 'draft'
    | 'pending_co_maker_signatures'
    | 'submitted'
    | 'pending_review'
    | 'under_review'
    | 'needs_revision'
    | 'awaiting_member_information'
    | 'recommended_for_approval'
    | 'awaiting_member_acceptance'
    | 'rejected'
    | 'approved'
    | 'declined'
    | 'member_declined_terms'
    | 'converted_to_loan'
    | 'for_wibs_encoding'
    | 'wibs_loan_created'
    | 'release_scheduled'
    | 'released'
    | 'cancelled';

export type LoanRequestWorkflowPermission =
    | 'loan.view'
    | 'loan.create'
    | 'loan.review'
    | 'loan.claim'
    | 'loan.return_to_queue'
    | 'loan.manage_assignment'
    | 'loan.request_revision'
    | 'loan.reject'
    | 'loan.recommend_approval'
    | 'loan.approve'
    | 'loan.decline'
    | 'loan.wibs_encode';

export type LoanRequestWorkflowContext = {
    isOwnRequest: boolean;
};

export type LoanRequestAuditTrailAudience = 'staff' | 'member';

export type LoanRequestAuditActor = {
    user_id: number;
    name: string;
    acctno: string | null;
};

export type LoanRequestAuditMetadataItem = {
    key: string;
    label: string;
    value: string;
};

export type LoanRequestAuditEntry = {
    id: string;
    action: string;
    action_label: string;
    actor: LoanRequestAuditActor | null;
    from_status: LoanRequestStatusValue | null;
    from_status_label: string | null;
    to_status: LoanRequestStatusValue | null;
    to_status_label: string | null;
    reason: string | null;
    created_at: string | null;
    metadata: LoanRequestAuditMetadataItem[];
};

export type LoanRequestDataFieldType =
    | 'string'
    | 'boolean'
    | 'number'
    | 'integer'
    | 'date';

export type LoanRequestDataFieldVisibility = {
    field: string;
    equals: string | boolean;
};

export type LoanRequestDataFieldDefinition = {
    label: string;
    sensitive: boolean;
    owner: 'member' | 'staff';
    type: LoanRequestDataFieldType;
    detail_of: string | string[] | null;
    visible_when: LoanRequestDataFieldVisibility | null;
    options?: string[];
};

export type LoanRequestDataSectionDefinition = {
    label: string;
    fields: Record<string, LoanRequestDataFieldDefinition>;
};

export type LoanRequestDataSectionDefinitions = Record<
    string,
    LoanRequestDataSectionDefinition
>;

export type LoanRequestDataFieldValue = string | number | boolean | null;

export type LoanRequestDataSectionValues = Record<
    string,
    LoanRequestDataFieldValue
>;

export type LoanRequestDataSections = Record<
    string,
    LoanRequestDataSectionValues
>;

export type LoanRequestDocumentKey =
    | 'application_form'
    | 'grepalife'
    | 'affidavit_undertaking'
    | 'loan_information'
    | 'plan_of_payment'
    | 'disclosure_statement'
    | 'promissory_note'
    | 'undertaking_barangay'
    | 'loan_security_agreement'
    | 'generali'
    | 'authority_to_deduct'
    | 'deped_salary_deduction_waiver'
    | 'pension_deduction_waiver'
    | 'generali_application_form';

export type LoanRequestDocumentReadinessStatus =
    | 'not_started'
    | 'incomplete'
    | 'awaiting_member_confirmation'
    | 'ready_to_generate'
    | 'generated_current'
    | 'generated_stale'
    | 'generation_failed'
    | 'not_applicable'
    | 'legacy_data_incomplete';

export type LoanRequestDocumentChecklistItem = {
    key: LoanRequestDocumentKey;
    label: string;
    is_applicable: boolean;
    unavailable_reason: string | null;
    status: LoanRequestDocumentReadinessStatus;
    status_label: string;
    template_version: string | null;
    generated_at: string | null;
    generated_by: string | null;
    generated_filename: string | null;
    generated_mime_type: string | null;
    generated_version: number | null;
    source_version: number | null;
    blockers: string[];
    failure_message: string | null;
    is_relaxed_old_record: boolean;
    manual_fill_fields: string[];
};

export type LoanRequestDocumentGenerationResult = {
    key: LoanRequestDocumentKey;
    status: LoanRequestDocumentReadinessStatus | null;
    message: string | null;
};

export type LoanRequestMemberActionType =
    | 'needs_revision'
    | 'awaiting_member_information'
    | 'terms_acceptance'
    | 'awaiting_member_acceptance'
    | null;

export type LoanRequestMemberAction = {
    type: LoanRequestMemberActionType;
    message: string | null;
    fields: string[] | null;
    requested_at: string | null;
    resolved_at: string | null;
};

export type LoanRequestNotificationHistoryItem = {
    id: number;
    channel: string;
    event_type: string;
    event_label: string;
    status: string | null;
    queued_at: string | null;
    sent_at: string | null;
    failed_at: string | null;
    last_attempt_at: string | null;
    attempt_count: number;
    retry_count: number;
    reminder_attempts: number;
    provider_error: string | null;
};

export type LoanRequestWorkflowHealth = {
    processing_age_days: number | null;
    stale_document_count: number;
    failed_document_count: number;
    legacy_blocker_count: number;
    pending_member_action: boolean;
    notification_failure_count: number;
    workflow_failed_job_count: number;
};

export type LoanRequestCompleteness = {
    percentage: number;
    completed: string[];
    missing: string[];
    missing_documents: LoanRequestDocumentKey[];
};

export type AuthorityToDeductSavedContact = {
    officer_1_name: string | null;
    officer_1_title: string | null;
    officer_2_name: string | null;
    officer_2_title: string | null;
};

export type AuthorityToDeductGuidance = {
    applicable: boolean;
    category: 'blgu' | 'lgu' | 'mrdinc' | 'ldh' | null;
    recommended_officers: number;
    note: string;
    saved_contact: AuthorityToDeductSavedContact | null;
};

export type WaiverApplicability = {
    deped: { applicable: boolean };
    pension: { applicable: boolean };
};

export type LoanRequestDetail = {
    id: number;
    reference: string;
    status: LoanRequestStatusValue | null;
    typecode: string | null;
    loan_type_label_snapshot: string | null;
    requested_amount: number | string | null;
    requested_term: number | string | null;
    loan_purpose: string | null;
    availment_status: string | null;
    requested_payment_frequency: string | null;
    kind_of_loan: string | null;
    submitted_at: string | null;
    assigned_officer_id: number | string | null;
    assigned_processor_id: number | string | null;
    assigned_officer: LoanRequestReviewer | null;
    assigned_processor: LoanRequestReviewer | null;
    assignment_state: LoanRequestAssignmentState;
    can_claim: boolean;
    can_assign: boolean;
    can_reassign: boolean;
    can_return_to_queue: boolean;
    workflow_version: LoanRequestWorkflowVersion | null;
    recommended_amount: number | string | null;
    recommended_term: number | string | null;
    recommended_interest_rate: number | string | null;
    recommended_payment_frequency: string | null;
    reviewed_by: LoanRequestReviewer | null;
    reviewed_at: string | null;
    review_decision: string | null;
    review_remarks: string | null;
    review_rejection_category: string | null;
    rejected_by: LoanRequestReviewer | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    approved_by: LoanRequestReviewer | null;
    approved_at: string | null;
    approval_remarks: string | null;
    approved_amount: number | string | null;
    approved_term: number | string | null;
    approved_interest_rate: number | string | null;
    decision_notes: string | null;
    declined_by: LoanRequestReviewer | null;
    declined_at: string | null;
    decline_category: string | null;
    decline_reason: string | null;
    member_action_type: LoanRequestMemberActionType;
    member_action_message: string | null;
    member_action_fields: string[] | null;
    member_action_requested_by: LoanRequestReviewer | null;
    member_action_requested_at: string | null;
    member_action_resolved_at: string | null;
    cancelled_by: LoanRequestReviewer | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    corrected_from_id: number | null;
    corrected_from_reference: string | null;
    corrected_request_id: number | null;
    corrected_request_reference: string | null;
    corrected_request_status: LoanRequestStatusValue | null;
    correction_saved: boolean;
    requires_correction_before_approval: boolean;
    is_first_processing_save: boolean;
    acctno: string | null;
    wibs_loan_reference: string | null;
    wibs_release_date: string | null;
    wibs_encoded_at: string | null;
    wibs_released_at: string | null;
    completeness: LoanRequestCompleteness | null;
    applicant_loan_status: LoanStatusSummaryForStaff | null;
    authority_to_deduct_guidance: AuthorityToDeductGuidance | null;
    waiver_applicability: WaiverApplicability | null;
};

export type LoanRequestListItem = {
    id: number;
    reference: string;
    status: LoanRequestStatusValue | null;
    typecode: string | null;
    loan_type_label_snapshot: string | null;
    requested_amount: number | string | null;
    requested_term: number | string | null;
    submitted_at: string | null;
    updated_at: string | null;
    assigned_officer: {
        user_id: number;
        name: string;
        display_code: string | null;
    } | null;
};

export type LoanRequestListResponse = {
    items: LoanRequestListItem[];
};

export type LoanRequestDraft = {
    id: number;
    reference: string;
    status: LoanRequestStatusValue | null;
    typecode: string | null;
    loan_type_label_snapshot: string | null;
    requested_amount: number | string | null;
    requested_term: number | string | null;
    loan_purpose: string | null;
    availment_status: string | null;
    requested_payment_frequency: string | null;
    kind_of_loan: string | null;
    submitted_at: string | null;
    updated_at: string | null;
};

export type AutoFilledDeclarations = {
    declaration_existing_loans?: boolean;
    declaration_pending_cases?: boolean;
    existing_loan_1_date?: string | null;
    existing_loan_1_type?: string | null;
    existing_loan_1_amount?: number | null;
};

export type ProblemLoan = {
    lnnumber: string;
    lntype: string;
    lnstatus: string;
    lnstatus_label: string;
    principal: number;
    balance: number;
    date_rel: string | null;
    date_mat: string | null;
};

export type LoanStatusSummaryForStaff = {
    has_active: boolean;
    has_past_due: boolean;
    has_litigation: boolean;
    total_active: number;
    total_past_due: number;
    total_litigation: number;
    active_balance_total: number;
    past_due_balance_total: number;
    litigation_balance_total: number;
    requires_attention: boolean;
    warning_message: string | null;
    problem_loans: ProblemLoan[];
};

export type LoanStatusSummaryForMember = {
    total_loans: number;
    active_count: number;
    past_due_count: number;
    litigation_count: number;
    total_balance: number;
    active_balance: number;
    past_due_balance: number;
    litigation_balance: number;
    loans: ProblemLoan[];
};

export type LoanRequestFormData = {
    typecode: string;
    requested_amount: string;
    requested_term: string;
    loan_purpose: string;
    availment_status: string;
    requested_payment_frequency: string;
    kind_of_loan: string;
    undertaking_accepted: boolean;
    applicant: LoanRequestPersonFormData;
    co_maker_1: LoanRequestPersonFormData;
    co_maker_2: LoanRequestPersonFormData;
    insurance: LoanRequestDataSectionValues;
    health: LoanRequestDataSectionValues;
    health_glapi: LoanRequestDataSectionValues;
    banking: LoanRequestDataSectionValues;
    declarations: LoanRequestDataSectionValues;
    dependents: LoanRequestDataSectionValues;
};

export type LoanRequestCorrectionPayload = Pick<
    LoanRequestFormData,
    | 'typecode'
    | 'requested_amount'
    | 'requested_term'
    | 'loan_purpose'
    | 'availment_status'
    | 'applicant'
    | 'co_maker_1'
    | 'co_maker_2'
> &
    Partial<
        Pick<
            LoanRequestFormData,
            | 'insurance'
            | 'health'
            | 'health_glapi'
            | 'banking'
            | 'declarations'
            | 'dependents'
        >
    > & {
        change_reason: string;
    };

export type LoanRequestCorrectionResult = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    auditTrail: LoanRequestAuditEntry[];
};

export type LoanRequestWorkflowResult = LoanRequestCorrectionResult & {
    correctionReports: LoanRequestCorrectionReport[];
    eligibleOfficers: LoanRequestAssignmentOfficerOption[];
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    documentChecklist: LoanRequestDocumentChecklistItem[];
    notificationHistory: LoanRequestNotificationHistoryItem[];
    workflowHealth: LoanRequestWorkflowHealth;
    documentResults?: LoanRequestDocumentGenerationResult[];
    loan?: Record<string, unknown> | null;
};

export type LoanRequestDecisionResult = {
    loanRequest: LoanRequestDetail;
    correctionReports: LoanRequestCorrectionReport[];
    auditTrail: LoanRequestAuditEntry[];
};

export type LoanRequestCancellationResult = {
    loanRequest: LoanRequestDetail;
    correctionReports: LoanRequestCorrectionReport[];
    auditTrail: LoanRequestAuditEntry[];
};

export type LoanRequestMemberCancellationResult = {
    loanRequest: LoanRequestDetail;
    auditTrail: LoanRequestAuditEntry[];
};

export type LoanRequestMemberActionResolutionResult = {
    loanRequest: LoanRequestDetail;
    auditTrail: LoanRequestAuditEntry[];
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
};
