<?php

return [
    'pdf_driver' => env('REPORT_PDF_DRIVER', 'chromium'),
    'chromium' => [
        'timeout' => env('REPORT_PDF_TIMEOUT', 120),
        'no_sandbox' => env('REPORT_PDF_NO_SANDBOX', true),
    ],
    'paper' => [
        'width' => env('REPORT_PDF_WIDTH', 8.5),
        'height' => env('REPORT_PDF_HEIGHT', 13),
        'unit' => env('REPORT_PDF_UNIT', 'in'),
    ],
];
