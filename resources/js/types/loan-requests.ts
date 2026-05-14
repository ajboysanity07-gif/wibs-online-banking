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
    address2: string | null;
    address3: string | null;
    length_of_stay: string | null;
    housing_status: string | null;
    cell_no: string | null;
    civil_status: string | null;
    educational_attainment: string | null;
    number_of_children: number | string | null;
    spouse_name: string | null;
    spouse_age: number | string | null;
    spouse_cell_no: string | null;
    employment_type: string | null;
    employer_business_name: string | null;
    employer_business_address: string | null;
    employer_business_address1: string | null;
    employer_business_address2: string | null;
    employer_business_address3: string | null;
    telephone_no: string | null;
    current_position: string | null;
    nature_of_business: string | null;
    years_in_work_business: string | null;
    gross_monthly_income: string | null;
    payday: string | null;
};

export type LoanRequestReviewer = {
    user_id: number;
    name: string;
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

export type LoanRequestStatusValue =
    | 'draft'
    | 'submitted'
    | 'under_review'
    | 'approved'
    | 'declined'
    | 'cancelled';

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
    submitted_at: string | null;
    reviewed_by: LoanRequestReviewer | null;
    reviewed_at: string | null;
    approved_amount: number | string | null;
    approved_term: number | string | null;
    decision_notes: string | null;
    cancelled_by: LoanRequestReviewer | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    corrected_from_id: number | null;
    corrected_from_reference: string | null;
    corrected_request_id: number | null;
    corrected_request_reference: string | null;
    corrected_request_status: LoanRequestStatusValue | null;
    acctno: string | null;
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
    submitted_at: string | null;
    updated_at: string | null;
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
    address2: string;
    address3: string;
    length_of_stay: string;
    housing_status: string;
    cell_no: string;
    civil_status: string;
    educational_attainment: string;
    number_of_children: string;
    spouse_name: string;
    spouse_age: string;
    spouse_cell_no: string;
    employment_type: string;
    employer_business_name: string;
    employer_business_address1: string;
    employer_business_address2: string;
    employer_business_address3: string;
    telephone_no: string;
    current_position: string;
    nature_of_business: string;
    years_in_work_business: string;
    gross_monthly_income: string;
    payday: string;
};

export type LoanRequestFormData = {
    typecode: string;
    requested_amount: string;
    requested_term: string;
    loan_purpose: string;
    availment_status: string;
    undertaking_accepted: boolean;
    applicant: LoanRequestPersonFormData;
    co_maker_1: LoanRequestPersonFormData;
    co_maker_2: LoanRequestPersonFormData;
};

export type LoanRequestCorrectionPayload = Omit<
    LoanRequestFormData,
    'undertaking_accepted'
> & {
    change_reason: string;
};

export type LoanRequestCorrectionResult = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
};
