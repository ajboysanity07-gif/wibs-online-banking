import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('loan request actions group document buttons and separate navigation', async () => {
    const detailFile = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-detail-page.tsx',
        ),
        'utf8',
    );
    const workflowActionsFile = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-workflow-actions.tsx',
        ),
        'utf8',
    );
    const adminPageFile = await readFile(
        resolve('resources', 'js', 'pages', 'admin', 'loan-request-show.tsx'),
        'utf8',
    );
    const staffPageFile = await readFile(
        resolve('resources', 'js', 'pages', 'staff', 'loan-request-show.tsx'),
        'utf8',
    );
    const clientPageFile = await readFile(
        resolve('resources', 'js', 'pages', 'client', 'loan-request-show.tsx'),
        'utf8',
    );

    assert.match(detailFile, /Actions/);
    assert.match(detailFile, /Download PDF/);
    assert.match(detailFile, /Plan of Payment Excel/);
    assert.match(detailFile, /Print application/);
    assert.match(detailFile, /Cancel Application/);
    assert.match(detailFile, /backLabel/);
    assert.match(detailFile, /sm:grid-cols-2/);
    assert.match(detailFile, /variant="ghost"/);
    assert.match(workflowActionsFile, /Start Review/);
    assert.match(workflowActionsFile, /Claim/);
    assert.match(workflowActionsFile, /Assign Loan Processor/);
    assert.match(workflowActionsFile, /Reassign Loan Processor/);
    assert.match(workflowActionsFile, /Return to Queue/);
    assert.match(workflowActionsFile, /Request Revision/);
    assert.match(workflowActionsFile, /Recommend Approval/);
    assert.match(workflowActionsFile, /High workload/);
    assert.doesNotMatch(workflowActionsFile, /Convert to Loan/);
    assert.match(workflowActionsFile, /Reject During Processing/);
    assert.match(workflowActionsFile, /Return for Processing/);
    assert.match(workflowActionsFile, /Reopen Rejected Request/);
    assert.match(workflowActionsFile, /Upgrade to Document Workflow v2/);
    assert.match(workflowActionsFile, /Generate All Required Documents/);
    assert.match(workflowActionsFile, /hasProcessingActions/);
    assert.match(adminPageFile, /Cancel Approved Request/);
    assert.match(adminPageFile, /Cancel Application/);
    assert.match(adminPageFile, /workflowPermissions/);
    assert.match(adminPageFile, /Not ready for your review yet/);
    assert.match(adminPageFile, /Ready for your decision/);
    assert.match(adminPageFile, /isManagerViewer/);
    assert.match(staffPageFile, /LoanRequestDetailPage/);
    assert.match(staffPageFile, /useLoanRequestWorkflow/);
    assert.match(staffPageFile, /claimLoanRequest/);
    assert.match(staffPageFile, /assignLoanRequest/);
    assert.match(staffPageFile, /returnLoanRequestToQueue/);
    assert.match(staffPageFile, /currentRequest\.can_claim/);
    assert.match(staffPageFile, /currentEligibleOfficers/);
    assert.match(staffPageFile, /Back to workflow queue/);
    assert.match(staffPageFile, /Not ready for your review yet/);
    assert.match(staffPageFile, /Ready for your decision/);
    assert.match(staffPageFile, /isManagerViewer/);
    assert.doesNotMatch(staffPageFile, /convertToLoan/);
    assert.match(
        clientPageFile,
        /\['submitted', 'pending_review', 'under_review'\]\.includes/,
    );
    assert.match(clientPageFile, /Reason \(optional\)/);
    assert.match(clientPageFile, /Confirm Cancellation/);
});
