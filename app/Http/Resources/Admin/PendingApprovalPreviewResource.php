<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PendingApprovalPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'member_name' => $this->wmaster?->bname ?? $this->username,
            'username' => $this->username,
            'email' => $this->email,
            'acctno' => $this->acctno,
            'created_at' => $this->created_at?->toDateTimeString(),
            'status' => $this->userProfile?->status,
        ];
    }
}
