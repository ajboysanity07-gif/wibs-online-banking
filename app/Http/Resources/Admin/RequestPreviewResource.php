<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = is_array($this->resource) ? $this->resource : [];

        return [
            'id' => $resource['id'] ?? null,
            'reference' => $resource['reference'] ?? null,
            'member_name' => $resource['member_name'] ?? null,
            'status' => $resource['status'] ?? null,
            'created_at' => $resource['created_at'] ?? null,
            'summary' => $resource['summary'] ?? null,
            'loan_type' => $resource['loan_type'] ?? null,
            'requested_amount' => $resource['requested_amount'] ?? null,
            'submitted_at' => $resource['submitted_at'] ?? null,
            'approved_amount' => $resource['approved_amount'] ?? null,
            'reviewed_at' => $resource['reviewed_at'] ?? null,
            'member_acctno' => $resource['member_acctno'] ?? null,
            'has_open_correction_report' => (bool) ($resource['has_open_correction_report'] ?? false),
            'latest_correction_report_id' => $resource['latest_correction_report_id'] ?? null,
            'latest_correction_report_reported_at' => $resource['latest_correction_report_reported_at'] ?? null,
            'latest_correction_report_issue' => $resource['latest_correction_report_issue'] ?? null,
            'latest_correction_report_correct_information' => $resource['latest_correction_report_correct_information'] ?? null,
            'latest_correction_report_supporting_note' => $resource['latest_correction_report_supporting_note'] ?? null,
            'latest_correction_report_reported_by' => $resource['latest_correction_report_reported_by'] ?? null,
        ];
    }
}
