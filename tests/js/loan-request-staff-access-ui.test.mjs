import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('sidebar exposes a dedicated staff workflow navigation surface', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'components', 'app-sidebar.tsx'),
        'utf8',
    );

    assert.match(file, /auth\.canAccessLoanWorkflow/);
    assert.match(file, /Loan Workflow/);
    assert.match(file, /staffLoanRequestsIndex/);
    assert.match(file, /sidebar-loan-workflow-collapsed/);
});
