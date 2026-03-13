<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $memberName = $this->relationLoaded('wmaster')
            ? ($this->wmaster?->bname ?? $this->username)
            : $this->username;

        return [
            'user_id' => $this->user_id,
            'member_name' => $memberName,
            'username' => $this->username,
            'email' => $this->email,
            'acctno' => $this->acctno,
            'status' => $this->userProfile?->status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'reviewed_at' => $this->userProfile?->reviewed_at?->toDateTimeString(),
        ];
    }
}
