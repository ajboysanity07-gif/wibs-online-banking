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
    address: string | null;
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
    telephone_no: string | null;
    current_position: string | null;
    nature_of_business: string | null;
    years_in_work_business: string | null;
    gross_monthly_income: string | null;
    payday: string | null;
};

export type LoanRequestReadOnlyMap = Record<string, boolean>;

export type LoanRequestDetail = {
    id: number;
    status: string | null;
    typecode: string | null;
    loan_type_label_snapshot: string | null;
    requested_amount: number | string | null;
    requested_term: number | string | null;
    loan_purpose: string | null;
    availment_status: string | null;
    submitted_at: string | null;
};
