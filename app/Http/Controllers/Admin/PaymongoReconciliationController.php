<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymongoReconciliationIndexRequest;
use App\Http\Requests\Admin\PaymongoReconciliationUpdateRequest;
use App\Models\PaymongoLoanPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymongoReconciliationController extends Controller
{
    public function index(PaymongoReconciliationIndexRequest $request): Response
    {
        $filters = $request->validated();
        $status = $filters['status'] ?? PaymongoLoanPayment::STATUS_PAID;
        $reconciliationStatus = $filters['reconciliation_status'] ?? null;
        $search = $filters['search'] ?? null;
        $perPage = (int) ($filters['perPage'] ?? 10);

        $query = PaymongoLoanPayment::query()
            ->with('reconciledBy')
            ->latest('paid_at')
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($reconciliationStatus !== null && $reconciliationStatus !== 'all') {
            $query->where('reconciliation_status', $reconciliationStatus);
        }

        if ($search !== null) {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('acctno', 'like', "%{$search}%")
                    ->orWhere('loan_number', 'like', "%{$search}%")
                    ->orWhere('provider_reference_number', 'like', "%{$search}%")
                    ->orWhere('desktop_reference_no', 'like', "%{$search}%")
                    ->orWhere('official_receipt_no', 'like', "%{$search}%");
            });
        }

        $payments = $query->paginate($perPage)->withQueryString();

        return Inertia::render('admin/paymongo-reconciliation', [
            'payments' => [
                'items' => collect($payments->items())
                    ->map(fn (PaymongoLoanPayment $payment): array => [
                        'id' => $payment->getKey(),
                        'paid_at' => $payment->paid_at?->toISOString(),
                        'acctno' => $payment->acctno,
                        'loan_number' => $payment->loan_number,
                        'base_amount' => $payment->baseAmount(),
                        'service_fee' => $payment->serviceFee(),
                        'gross_amount' => $payment->grossAmount(),
                        'payment_method' => $payment->payment_method,
                        'payment_method_label' => $payment->payment_method_label,
                        'provider_reference_number' => $payment->provider_reference_number,
                        'status' => $payment->status,
                        'reconciliation_status' => $payment->reconciliation_status
                            ?? PaymongoLoanPayment::RECONCILIATION_UNRECONCILED,
                        'desktop_reference_no' => $payment->desktop_reference_no,
                        'official_receipt_no' => $payment->official_receipt_no,
                        'reconciliation_notes' => $payment->reconciliation_notes,
                        'reconciled_at' => $payment->reconciled_at?->toISOString(),
                        'reconciled_by' => $payment->reconciledBy === null
                            ? null
                            : [
                                'id' => $payment->reconciledBy->getKey(),
                                'name' => $payment->reconciledBy->name,
                            ],
                    ])
                    ->values()
                    ->all(),
                'meta' => [
                    'page' => $payments->currentPage(),
                    'perPage' => $payments->perPage(),
                    'total' => $payments->total(),
                    'lastPage' => $payments->lastPage(),
                ],
                'filters' => [
                    'status' => $status,
                    'reconciliation_status' => $reconciliationStatus ?? 'all',
                    'search' => $search,
                ],
            ],
        ]);
    }

    public function update(
        PaymongoReconciliationUpdateRequest $request,
        PaymongoLoanPayment $payment,
    ): RedirectResponse {
        if ($payment->status !== PaymongoLoanPayment::STATUS_PAID) {
            throw ValidationException::withMessages([
                'payment' => 'Only paid PayMongo payments can be reconciled.',
            ]);
        }

        $data = $request->validated();

        $payment->forceFill([
            'reconciliation_status' => PaymongoLoanPayment::RECONCILIATION_RECONCILED,
            'reconciled_at' => now(),
            'reconciled_by' => $request->user()?->getKey(),
            'desktop_reference_no' => $data['desktop_reference_no'] ?? null,
            'official_receipt_no' => $data['official_receipt_no'] ?? null,
            'reconciliation_notes' => $data['reconciliation_notes'] ?? null,
        ])->save();

        return back()->with('success', 'PayMongo payment marked as reconciled.');
    }
}
