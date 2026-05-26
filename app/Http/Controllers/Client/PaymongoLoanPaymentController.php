<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StorePaymongoLoanPaymentRequest;
use App\Models\AppUser;
use App\Models\PaymongoLoanPayment;
use App\Services\Admin\MemberLoans\MemberLoanService;
use App\Services\Payments\PaymongoCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PaymongoLoanPaymentController extends Controller
{
    public function store(
        StorePaymongoLoanPaymentRequest $request,
        string $loanNumber,
        MemberLoanService $memberLoanService,
        PaymongoCheckoutService $checkoutService,
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            abort(403);
        }

        $acctno = is_string($user->acctno) ? trim($user->acctno) : '';

        if ($acctno === '') {
            throw ValidationException::withMessages([
                'amount' => 'A member account number is required before paying online.',
            ]);
        }

        $validated = $request->validated();
        $baseAmountCents = $this->amountToCents($validated['amount']);
        $loanPayload = $memberLoanService->getSchedulePageData(
            $user,
            $loanNumber,
        );

        $balance = $loanPayload['summary']['balance'] ?? null;

        if (is_numeric($balance)) {
            $balanceCents = $this->amountToCents($balance);

            if ($baseAmountCents > $balanceCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Loan payment amount cannot exceed the outstanding balance.',
                ]);
            }
        }

        try {
            $checkout = $checkoutService->create(
                $user,
                $loanPayload['loan'],
                $acctno,
                $baseAmountCents,
                $validated['payment_method'],
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Online payments are not available right now.',
            ], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'PayMongo could not start checkout. Please try again.',
            ], 502);
        }

        return response()->json([
            'payment_id' => $checkout['payment']->getKey(),
            'checkout_url' => $checkout['checkout_url'],
            'base_amount' => $checkout['amounts']['base_amount_cents'] / 100,
            'service_fee' => $checkout['amounts']['service_fee_cents'] / 100,
            'total_amount' => $checkout['amounts']['gross_amount_cents'] / 100,
            'payment_method' => $checkout['amounts']['method'],
        ]);
    }

    public function success(
        Request $request,
        PaymongoLoanPayment $payment,
    ): RedirectResponse {
        $this->ensurePaymentBelongsToUser($request, $payment);

        $message = $payment->status === PaymongoLoanPayment::STATUS_PAID
            ? 'Payment confirmed. It will be reconciled against your loan account.'
            : 'Payment is being processed. We will update your record once PayMongo confirms it.';

        return redirect()
            ->route('client.loan-payments', $payment->loan_number)
            ->with('status', $message);
    }

    public function cancel(
        Request $request,
        PaymongoLoanPayment $payment,
    ): RedirectResponse {
        $this->ensurePaymentBelongsToUser($request, $payment);

        if ($payment->status === PaymongoLoanPayment::STATUS_PENDING) {
            $payment->forceFill([
                'status' => PaymongoLoanPayment::STATUS_CANCELLED,
                'metadata' => array_replace_recursive(
                    $payment->metadata ?? [],
                    [
                        'cancelled_from' => [
                            'source' => 'return_url',
                            'at' => now()->toISOString(),
                        ],
                    ],
                ),
            ])->save();
        }

        return redirect()
            ->route('client.loan-payments', $payment->loan_number)
            ->with('status', 'PayMongo checkout was cancelled.');
    }

    private function ensurePaymentBelongsToUser(
        Request $request,
        PaymongoLoanPayment $payment,
    ): void {
        $user = $request->user();

        if (! $user instanceof AppUser || (int) $payment->user_id !== (int) $user->getKey()) {
            abort(404);
        }
    }

    private function amountToCents(string|int|float $amount): int
    {
        $amount = trim((string) $amount);

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            $amount = number_format((float) $amount, 2, '.', '');
        }

        [$pesos, $centavos] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $pesos * 100) + (int) str_pad($centavos, 2, '0');
    }
}
