<?php

return [
    'retention_years' => (int) env('LOAN_WORKFLOW_RETENTION_YEARS', 5),
    'officer_workload_warning_threshold' => (int) env(
        'LOAN_WORKFLOW_OFFICER_WORKLOAD_WARNING_THRESHOLD',
        30,
    ),
    'report_aging_threshold_days' => (int) env('LOAN_WORKFLOW_REPORT_AGING_DAYS', 3),
    'wibs_encoding_stale_days' => (int) env('LOAN_WORKFLOW_WIBS_STALE_DAYS', 5),
    // Member loan calculator. Interest and service charge are set per request
    // by the loan processor; leave these unset and the estimate excludes them
    // (and says so) instead of guessing a rate.
    'estimate' => [
        'interest_rate' => env('LOAN_ESTIMATE_INTEREST_RATE') !== null
            ? (float) env('LOAN_ESTIMATE_INTEREST_RATE')
            : null,
        'service_charge_rate' => env('LOAN_ESTIMATE_SERVICE_CHARGE_RATE') !== null
            ? (float) env('LOAN_ESTIMATE_SERVICE_CHARGE_RATE')
            : null,
        'max_term_months' => (int) env('LOAN_ESTIMATE_MAX_TERM_MONTHS', 36),
        // Slider range only -- the typed amount is not capped.
        'slider_max_amount' => (int) env('LOAN_ESTIMATE_SLIDER_MAX_AMOUNT', 500000),
    ],
    'documents' => [
        'disk' => env('LOAN_WORKFLOW_DOCUMENT_DISK', 'local'),
        'directory' => env(
            'LOAN_WORKFLOW_DOCUMENT_DIRECTORY',
            'loan-request-documents',
        ),
        'legacy_directory' => 'loan-request-documents',
        'temporary_directory' => env(
            'LOAN_WORKFLOW_TEMP_DIRECTORY',
            'tmp/loan-workflow',
        ),
        'temporary_retention_hours' => (int) env(
            'LOAN_WORKFLOW_TEMP_RETENTION_HOURS',
            24,
        ),
    ],
    'notifications' => [
        'queue' => env('LOAN_WORKFLOW_NOTIFICATION_QUEUE', 'loan-workflow'),
        'sms_queue' => env(
            'LOAN_WORKFLOW_NOTIFICATION_SMS_QUEUE',
            'loan-workflow-notifications',
        ),
        'mail_queue' => env(
            'LOAN_WORKFLOW_NOTIFICATION_MAIL_QUEUE',
            'loan-workflow-notifications',
        ),
        'tries' => (int) env('LOAN_WORKFLOW_NOTIFICATION_TRIES', 5),
        'timeout' => (int) env('LOAN_WORKFLOW_NOTIFICATION_TIMEOUT', 120),
        'backoff_seconds' => array_values(array_filter(array_map(
            static fn (string $value): ?int => is_numeric(trim($value))
                ? max(1, (int) trim($value))
                : null,
            explode(',', (string) env(
                'LOAN_WORKFLOW_NOTIFICATION_BACKOFF_SECONDS',
                '60,300,900',
            )),
        ))),
        'reminder_after_days' => (int) env(
            'LOAN_WORKFLOW_NOTIFICATION_REMINDER_AFTER_DAYS',
            3,
        ),
        'max_reminders' => (int) env(
            'LOAN_WORKFLOW_NOTIFICATION_MAX_REMINDERS',
            1,
        ),
        'action_link_ttl_hours' => (int) env(
            'LOAN_WORKFLOW_ACTION_LINK_TTL_HOURS',
            168,
        ),
    ],
];
