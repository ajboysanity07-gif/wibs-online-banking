<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MemberLoanResource;
use App\Http\Resources\Admin\MemberLoanScheduleResource;
use App\Http\Resources\Admin\MemberLoanSummaryResource;
use App\Models\AppUser;
use App\Services\Admin\MemberLoans\MemberLoanService;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MemberLoanScheduleController extends Controller
{
    public function show(
        AppUser $user,
        string $loanNumber,
        MemberLoanService $service,
    ): Response {
        $memberName = $user->username;

        if (Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');
            $memberName = $user->wmaster?->bname ?? $memberName;
        }

        $payload = $service->getSchedulePageData($user, $loanNumber);

        return Inertia::render('admin/member-loan-schedule', [
            'member' => [
                'user_id' => $user->user_id,
                'member_name' => $memberName,
                'acctno' => $user->acctno,
            ],
            'loan' => (new MemberLoanResource($payload['loan']))->resolve(),
            'summary' => (new MemberLoanSummaryResource($payload['summary']))->resolve(),
            'schedule' => [
                'items' => MemberLoanScheduleResource::collection(
                    $payload['schedule'],
                )->resolve(),
            ],
        ]);
    }
}
