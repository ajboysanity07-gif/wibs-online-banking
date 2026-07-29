<?php

use App\Exports\RejectionReasonsExport;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;

function createRejectionReasonsExportActor(): AppUser
{
    $user = AppUser::factory()->create();

    $roleId = Role::query()->where('name', Role::LOAN_MANAGER)->value('id');
    $user->roles()->sync([$roleId]);

    return $user;
}

test('rejection reasons export includes the category column and resolves decided-by for both outcomes', function (): void {
    $exportActor = createRejectionReasonsExportActor();
    $processor = AppUser::factory()->create();
    $manager = AppUser::factory()->create();

    $rejected = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Rejected,
        'review_rejection_category' => 'Incomplete documents',
        'rejection_reason' => 'Missing valid ID.',
        'rejected_by' => $processor->user_id,
        'rejected_at' => now()->subDays(2),
    ]);

    $declined = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Declined,
        'decline_category' => 'Insufficient income',
        'decline_reason' => 'Debt-to-income ratio too high.',
        'declined_by' => $manager->user_id,
        'declined_at' => now()->subDay(),
    ]);

    $export = new RejectionReasonsExport($exportActor);

    expect($export->headings())->toContain('Category');

    $rows = $export->collection()->keyBy('id');

    $rejectedRow = $export->map($rows->get($rejected->id));
    $declinedRow = $export->map($rows->get($declined->id));

    expect($rejectedRow)->toBe([
        $rejected->reference,
        $rejected->user?->name ?? '',
        $rejected->loan_type_label_snapshot ?? '',
        $rejected->requested_amount,
        LoanRequestStatus::Rejected->value,
        'Incomplete documents',
        'Missing valid ID.',
        $rejected->rejected_at->toDateString(),
        $processor->name,
    ]);

    expect($declinedRow)->toBe([
        $declined->reference,
        $declined->user?->name ?? '',
        $declined->loan_type_label_snapshot ?? '',
        $declined->requested_amount,
        LoanRequestStatus::Declined->value,
        'Insufficient income',
        'Debt-to-income ratio too high.',
        $declined->declined_at->toDateString(),
        $manager->name,
    ]);
});
