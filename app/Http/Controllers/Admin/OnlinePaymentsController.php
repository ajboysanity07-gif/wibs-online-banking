<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OnlinePaymentPostRequest;
use App\Http\Requests\Admin\OnlinePaymentsIndexRequest;
use App\Models\OnlinePayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnlinePaymentsController extends Controller
{
    public function index(OnlinePaymentsIndexRequest $request): Response
    {
        $filters = $this->normalizedFilters($request);
        $perPage = max(1, min((int) ($filters['perPage'] ?? 10), 50));

        $query = OnlinePayment::query()
            ->with('user:user_id,username,email,acctno')
            ->latest();

        $this->applyFilters($query, $filters);

        $paginator = $query->paginate($perPage)->withQueryString();

        return Inertia::render('admin/online-payments', [
            'payments' => [
                'items' => $paginator->getCollection()
                    ->map(fn (OnlinePayment $payment): array => $this->paymentPayload($payment))
                    ->values(),
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ],
            'filters' => $filters,
        ]);
    }

    public function show(OnlinePayment $onlinePayment): Response
    {
        $onlinePayment->load('user:user_id,username,email,acctno', 'poster:user_id,username,email,acctno');

        return Inertia::render('admin/online-payment-show', [
            'payment' => $this->paymentPayload($onlinePayment, includePayload: true),
        ]);
    }

    public function post(
        OnlinePaymentPostRequest $request,
        OnlinePayment $onlinePayment,
    ): RedirectResponse {
        if (
            $onlinePayment->status !== OnlinePayment::STATUS_PAID ||
            $onlinePayment->posted_at !== null ||
            $onlinePayment->posted_by !== null
        ) {
            abort(409, 'Only unposted paid online payments can be posted.');
        }

        /*
         * TODO: Connect this action to the official loan ledger posting service
         * once the authoritative write path for wlnled/wlnmaster is confirmed.
         */
        return back()->withErrors([
            'onlinePayment' => 'Ledger posting is not configured yet.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (is_string($filters['status'] ?? null) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (is_string($filters['start'] ?? null) && $filters['start'] !== '') {
            $query->whereDate('created_at', '>=', $filters['start']);
        }

        if (is_string($filters['end'] ?? null) && $filters['end'] !== '') {
            $query->whereDate('created_at', '<=', $filters['end']);
        }

        foreach (['loan_number', 'acctno', 'reference_number'] as $column) {
            if (is_string($filters[$column] ?? null) && trim($filters[$column]) !== '') {
                $query->where($column, 'like', '%'.trim($filters[$column]).'%');
            }
        }
    }

    /**
     * @return array{
     *     status: ?string,
     *     start: ?string,
     *     end: ?string,
     *     loan_number: ?string,
     *     acctno: ?string,
     *     reference_number: ?string,
     *     perPage: int
     * }
     */
    private function normalizedFilters(OnlinePaymentsIndexRequest $request): array
    {
        $validated = $request->validated();

        return [
            'status' => $this->nullableString($validated['status'] ?? null),
            'start' => $this->nullableString($validated['start'] ?? null),
            'end' => $this->nullableString($validated['end'] ?? null),
            'loan_number' => $this->nullableString($validated['loan_number'] ?? null),
            'acctno' => $this->nullableString($validated['acctno'] ?? null),
            'reference_number' => $this->nullableString($validated['reference_number'] ?? null),
            'perPage' => (int) ($validated['perPage'] ?? 10),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(
        OnlinePayment $payment,
        bool $includePayload = false,
    ): array {
        $payload = [
            'id' => $payment->id,
            'member_name' => $payment->user?->name,
            'acctno' => $payment->acctno,
            'loan_number' => $payment->loan_number,
            'amount' => $payment->amount / 100,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'provider_checkout_id' => $payment->provider_checkout_id,
            'provider_payment_id' => $payment->provider_payment_id,
            'reference_number' => $payment->reference_number,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
            'posted_at' => $payment->posted_at?->toDateTimeString(),
            'posted_by' => $payment->poster?->name,
            'created_at' => $payment->created_at?->toDateTimeString(),
            'updated_at' => $payment->updated_at?->toDateTimeString(),
        ];

        if ($includePayload) {
            $payload['raw_payload'] = $payment->raw_payload;
        }

        return $payload;
    }
}
