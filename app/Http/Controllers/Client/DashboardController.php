<?php

namespace App\Http\Controllers\Client;

use App\Domains\MemberAccounts\Resources\MemberAccountsSummaryResource;
use App\Domains\MemberAccounts\Resources\MemberRecentAccountActionResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Support\SchemaCapabilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        MemberAccountsService $service,
        SchemaCapabilities $schemaCapabilities,
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('userProfile.reviewedBy', 'adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $memberName = $user->username;

        try {
            if ($schemaCapabilities->hasTable('wmaster')) {
                $wmaster = $user->wmaster()->first([
                    'acctno',
                    'fname',
                    'mname',
                    'lname',
                    'bname',
                ]);
                $wmasterName = $wmaster?->displayName();

                if (is_string($wmasterName) && trim($wmasterName) !== '') {
                    $memberName = $wmasterName;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $summary = null;
        $summaryError = null;

        try {
            $summary = $service->getDashboardSummary($user);
        } catch (Throwable $exception) {
            report($exception);
            $summaryError = 'Unable to load summary.';
        }

        $memberPayload = $this->sanitizePayload([
            'name' => $memberName,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phoneno,
            'acctno' => $user->acctno,
            'status' => $user->userProfile?->status,
            'created_at' => $user->created_at?->toDateTimeString(),
            'reviewed_by' => $user->userProfile?->reviewedBy
                ? [
                    'user_id' => $user->userProfile->reviewedBy->user_id,
                    'name' => $user->userProfile->reviewedBy->name,
                ]
                : null,
            'reviewed_at' => $user->userProfile?->reviewed_at?->toDateTimeString(),
            'avatar_url' => $user->avatar,
        ]);
        $summaryPayload = $summary === null
            ? null
            : $this->sanitizePayload(
                (new MemberAccountsSummaryResource($summary))->resolve(),
            );

        $actionsPage = (int) $request->query('actions_page', 1);
        $actionsPage = max(1, $actionsPage);
        $actionsPerPage = 5;
        $recentAccountActionsPayload = null;
        $recentAccountActionsError = null;

        try {
            $paginator = $service->getPaginatedRecentActions(
                $user,
                $actionsPerPage,
                $actionsPage,
            );
            $items = MemberRecentAccountActionResource::collection(
                $paginator->items(),
            )->resolve();
            $recentAccountActionsPayload = [
                'items' => $items,
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ];
        } catch (Throwable $exception) {
            report($exception);
            $recentAccountActionsError = 'Unable to load account actions.';
        }

        $recentAccountActionsPayload = $recentAccountActionsPayload === null
            ? null
            : $this->sanitizePayload($recentAccountActionsPayload);

        return Inertia::render('client/dashboard', [
            'member' => $memberPayload,
            'summary' => $summaryPayload,
            'summaryError' => $summaryError,
            'recentAccountActions' => $recentAccountActionsPayload,
            'recentAccountActionsError' => $recentAccountActionsError,
        ]);
    }

    private function sanitizePayload(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizePayload($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        return $value;
    }

    private function sanitizeString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding(
                $value,
                'UTF-8',
                'UTF-8,ISO-8859-1,Windows-1252',
            );

            if (is_string($converted) && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($converted === false) {
            return '';
        }

        return $converted;
    }
}
