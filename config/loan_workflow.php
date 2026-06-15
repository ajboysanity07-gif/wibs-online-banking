<?php

return [
    'officer_workload_warning_threshold' => (int) env(
        'LOAN_WORKFLOW_OFFICER_WORKLOAD_WARNING_THRESHOLD',
        30,
    ),
];
