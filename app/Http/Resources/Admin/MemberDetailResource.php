<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviewedBy = $this->userProfile?->reviewedBy;
        $memberName = $this->relationLoaded('wmaster')
            ? ($this->wmaster?->bname ?? $this->username)
            : $this->username;

        return [
            'user_id' => $this->user_id,
            'member_name' => $memberName,
            'username' => $this->username,
            'email' => $this->email,
            'phoneno' => $this->phoneno,
            'acctno' => $this->acctno,
            'status' => $this->userProfile?->status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'reviewed_at' => $this->userProfile?->reviewed_at?->toDateTimeString(),
            'reviewed_by' => $reviewedBy
                ? [
                    'user_id' => $reviewedBy->user_id,
                    'name' => $reviewedBy->name,
                ]
                : null,
            'avatar_url' => $this->avatar,
        ];
    }
}
