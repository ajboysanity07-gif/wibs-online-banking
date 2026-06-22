export interface ReportingMetrics {
    pending_count: number;
    approved_count: number;
    rejected_count: number;
    approval_rate: number;
    average_processing_days: number | null;
    portfolio_total: number;
    status_breakdown: Record<string, number>;
    daily_trend: Record<string, number>;
}

export interface StaffPerformanceRow {
    processor_id: number;
    name: string;
    assigned: number;
    approved: number;
    rejected: number;
    avg_days: number | null;
}

export interface ProcessorQueueItem {
    id: number;
    reference: string;
    status: string;
    created_at: string;
    business_days_in_queue: number;
    is_aging: boolean;
}

export interface ProcessorQueueData {
    queue_count: number;
    aging_count: number;
    aging_threshold_days: number;
    items: ProcessorQueueItem[];
}

export interface DateRange {
    from: string | null;
    to: string | null;
}

export type ReportType =
    | 'monthly-applications'
    | 'rejection-reasons'
    | 'turnaround-time'
    | 'processor-workload';
