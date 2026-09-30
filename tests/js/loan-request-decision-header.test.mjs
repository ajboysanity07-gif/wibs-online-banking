import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const read = (...parts) =>
    readFile(resolve('resources', 'js', ...parts), 'utf8');

test('staff request review uses a sticky decision header with header-layout actions', async () => {
    const page = await read('pages', 'staff', 'loan-request-show.tsx');
    const header = await read(
        'components',
        'loan-request',
        'loan-request-decision-header.tsx',
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

    assert.match(header, /sticky top-14 z-10/);
    assert.match(page, /<LoanRequestWorkflowActions[\s\S]*?layout="header"/);
    assert.doesNotMatch(page, /LoanRequestSummaryHeader/);
    // Sidebar must not render the workflow actions a second time.
    assert.doesNotMatch(page, /<LoanRequestDetailPage[\s\S]*?workflow=\{/);
    // The gate only disables Recommend approval; it never enables an action.
    assert.match(actions, /blockedReason: recommendBlockedReason/);
    assert.match(bar, /aria-disabled/);
    assert.match(bar, /modal=\{false\}/);
});
