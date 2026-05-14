import type { LoanRequestStatusValue } from '@/types/loan-requests';

export type NotificationPayload = {
    type: string;
    title: string;
    message: string;
    status?: LoanRequestStatusValue | string | null;
    entity_type?: string | null;
    entity_id?: number | string | null;
    loan_request_id?: number | string | null;
    reference?: string | null;
    decision_notes?: string | null;
    cancellation_reason?: string | null;
    correction_reason?: string | null;
    report_id?: number | string | null;
    issue_description?: string | null;
    correct_information?: string | null;
    supporting_note?: string | null;
    corrected_from_id?: number | string | null;
    old_loan_request_id?: number | string | null;
    old_reference?: string | null;
    corrected_loan_request_id?: number | string | null;
    corrected_loan_request_reference?: string | null;
    reviewed_at?: string | null;
    member_id?: number | null;
    member_name?: string | null;
    member_acctno?: string | null;
    actor_id?: number | null;
    actor_name?: string | null;
    actor_role?: string | null;
    loan_type_code?: string | null;
    loan_type_label?: string | null;
    requested_amount?: number | string | null;
    requested_term?: number | string | null;
    submitted_at?: string | null;
    updated_at?: string | null;
    changed_fields?: string[] | null;
    [key: string]: unknown;
};

export type NotificationItem = {
    id: string;
    data: NotificationPayload;
    read_at: string | null;
    created_at: string | null;
};

export type NotificationListResponse = {
    items: NotificationItem[];
};

export type NotificationUnreadCountResponse = {
    unreadCount: number;
};

export type NotificationReadResponse = {
    notification: NotificationItem;
    unreadCount: number;
};

export type NotificationReadAllResponse = {
    unreadCount: number;
    readAt: string;
};
