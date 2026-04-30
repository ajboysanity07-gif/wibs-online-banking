<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\PayMongoCheckoutRequest;
use App\Models\OnlinePayment;
use App\Repositories\Admin\MemberLoansRepository;
use App\Services\Payments\PayMongoService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PayMongoCheckoutController extends Controller
{
    public function __invoke(
        PayMongoCheckoutRequest $request,
        string $loanNumber,
        MemberLoansRepository $loans,
        PayMongoService $payMongo,
    ): RedirectResponse|Response {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $acctno = is_string($user->acctno) ? trim($user->acctno) : '';

        if ($acctno === '') {
            abort(404);
        }

        $loan = $loans->findLoan($acctno, trim($loanNumber));

        if ($loan === null) {
            abort(404);
        }

        $amountCentavos = $this->amountToCentavos((string) $request->validated('amount'));
        $outstandingCentavos = $this->decimalToCentavos($loan->balance ?? null);

        if ($outstandingCentavos !== null && $outstandingCentavos > 0 && $amountCentavos > $outstandingCentavos) {
            return back()->withErrors([
                'amount' => 'The payment amount cannot exceed the outstanding balance.',
            ])->onlyInput('amount');
        }

        $payment = OnlinePayment::query()->create([
            'user_id' => $user->getKey(),
            'acctno' => $acctno,
            'loan_number' => $loan->lnnumber,
            'amount' => $amountCentavos,
            'currency' => 'PHP',
            'provider' => 'paymongo',
            'status' => OnlinePayment::STATUS_PENDING,
        ]);

        try {
            $checkout = $payMongo->createCheckoutSession(
                $payment,
                route('client.online-payments.success', $payment),
                route('client.online-payments.failed', $payment),
            );
        } catch (Throwable $exception) {
            report($exception);

            $payment->update([
                'status' => OnlinePayment::STATUS_FAILED,
                'raw_payload' => [
                    'checkout_error' => $exception->getMessage(),
                ],
            ]);

            return back()->withErrors([
                'amount' => app()->environment('local')
                    ? $exception->getMessage()
                    : 'Online checkout is temporarily unavailable. Please try again later.',
            ])->onlyInput('amount');
        }

        $checkoutId = data_get($checkout, 'data.id');
        $checkoutUrl = data_get($checkout, 'data.attributes.checkout_url');

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            $payment->update([
                'status' => OnlinePayment::STATUS_FAILED,
                'raw_payload' => $checkout,
            ]);

            return back()->withErrors([
                'amount' => 'PayMongo did not return a checkout URL. Please try again later.',
            ])->onlyInput('amount');
        }

        $payment->update([
            'provider_checkout_id' => is_string($checkoutId) ? $checkoutId : null,
        ]);

        if ($request->headers->has('X-Inertia')) {
            return Inertia::location($checkoutUrl);
        }

        return redirect()->away($checkoutUrl);
    }

    private function amountToCentavos(string $amount): int
    {
        [$pesos, $centavos] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $pesos * 100) + (int) str_pad(substr($centavos, 0, 2), 2, '0');
    }

    private function decimalToCentavos(mixed $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $amount = number_format((float) $amount, 2, '.', '');

        return $this->amountToCentavos($amount);
    }
}
