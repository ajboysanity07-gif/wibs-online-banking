import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';

const read = (...parts) =>
    readFile(resolve('resources', 'js', ...parts), 'utf8');

test('staff request review uses a scrolling record header with header-layout actions', async () => {
    const page = await read('pages', 'staff', 'loan-request-show.tsx');
    const header = await read(
        'components',
        'loan-request',
        'loan-request-record-header.tsx',
    );
    const actions = await read(
        'components',
        'loan-request',
        'loan-request-workflow-actions.tsx',
    );
    const bar = await read(
        'components',
        'loan-request',
        'loan-request-action-bar.tsx',
    );

    // Scrolls with the page; only the app header stays sticky.
    assert.doesNotMatch(header, /sticky/);
    assert.match(header, /bg-card shadow-card/);
    // Key figures strip and chevron stage path.
    assert.match(page, /label: 'Net proceeds'[\s\S]*?tone: 'primary'/);
    assert.match(page, /PROCESSING_AGE_ISSUE_THRESHOLD_DAYS\}d`/);
    assert.match(page, /\? 'You'/);
    assert.match(page, /title: 'My queue'/);
    assert.match(header, /clip-path:polygon/);
    assert.match(header, /Stage guidance:/);
    assert.match(header, /scrollbar-hide/);
    assert.doesNotMatch(
        page,
        /LoanRequestProgressCard|LoanRequestDecisionHeader/,
    );
    assert.match(page, /<LoanRequestWorkflowActions[\s\S]*?layout="header"/);
    assert.doesNotMatch(page, /LoanRequestSummaryHeader/);
    // Sidebar must not render the workflow actions a second time.
    assert.doesNotMatch(page, /<LoanRequestDetailPage[\s\S]*?workflow=\{/);
    // The gate only disables Recommend approval; it never enables an action.
    assert.match(actions, /blockedReason: recommendBlockedReason/);
    assert.match(bar, /aria-disabled/);
    assert.match(bar, /modal=\{false\}/);
    // Mobile: header buttons hidden, fixed bottom bar with More + primary.
    assert.match(bar, /hidden flex-wrap items-center gap-2 sm:flex/);
    assert.match(
        bar,
        /fixed inset-x-0 bottom-0[\s\S]*?safe-area-inset-bottom[\s\S]*?sm:hidden/,
    );
    assert.match(page, /h-24 sm:hidden/);
});

test('stage path labels and guidance line up with every step', async () => {
    const { PROGRESS_STEPS, STAGE_GUIDANCE, resolveLoanRequestProgress } =
        await import(
            pathToFileURL(
                resolve('resources', 'js', 'lib', 'loan-request-progress.ts'),
            ).href
        );

    assert.deepEqual(
        [...PROGRESS_STEPS],
        ['Draft', 'Submitted', 'In processing', 'Manager approval', 'Release'],
    );
    // One line per step plus one for released (all done).
    assert.equal(STAGE_GUIDANCE.length, PROGRESS_STEPS.length + 1);
    assert.match(
        STAGE_GUIDANCE[resolveLoanRequestProgress('under_review').step],
        /verify the conditions, clear exceptions, generate the document package, then recommend approval to the Loan Manager/,
    );
    assert.ok(STAGE_GUIDANCE[resolveLoanRequestProgress('released').step]);
});
