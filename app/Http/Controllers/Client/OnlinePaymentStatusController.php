<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\OnlinePayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnlinePaymentStatusController extends Controller
{
    public function success(
        Request $request,
        OnlinePayment $onlinePayment,
    ): Response {
        $this->ensureOwnedByAuthenticatedMember($request, $onlinePayment);

        return Inertia::render('client/online-payment-status', [
            'payment' => $this->paymentPayload($onlinePayment),
            'state' => 'success',
            'message' => $onlinePayment->status === OnlinePayment::STATUS_PAID
                ? 'Payment received.'
                : 'Payment submitted. We are waiting for PayMongo confirmation.',
        ]);
    }

    public function failed(
        Request $request,
        OnlinePayment $onlinePayment,
    ): Response {
        $this->ensureOwnedByAuthenticatedMember($request, $onlinePayment);

        return Inertia::render('client/online-payment-status', [
            'payment' => $this->paymentPayload($onlinePayment),
            'state' => 'failed',
            'message' => $onlinePayment->status === OnlinePayment::STATUS_PAID
                ? 'Payment received.'
                : 'Payment was not completed. No payment has been confirmed.',
        ]);
    }

    private function ensureOwnedByAuthenticatedMember(
        Request $request,
        OnlinePayment $onlinePayment,
    ): void {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $acctno = is_string($user->acctno) ? trim($user->acctno) : null;

        if (
            $onlinePayment->user_id !== $user->getKey() &&
            ($acctno === null || $acctno === '' || $onlinePayment->acctno !== $acctno)
        ) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(OnlinePayment $payment): array
    {
        return [
            'id' => $payment->id,
            'loan_number' => $payment->loan_number,
            'acctno' => $payment->acctno,
            'amount' => $payment->amount / 100,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'reference_number' => $payment->reference_number,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
            'created_at' => $payment->created_at?->toDateTimeString(),
        ];
    }
}
