import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const read = (...parts) =>
    readFile(resolve('resources', 'js', ...parts), 'utf8');

test('internal notes: staff-only card wired to the JSON client, newest-first list, clears only on save', async () => {
    const page = await read('pages', 'staff', 'loan-request-show.tsx');
    const rail = await read(
        'components',
        'loan-request',
        'loan-request-review-rail.tsx',
    );
    const api = await read('lib', 'api', 'admin.ts');

    assert.match(
        page,
        /<LoanRequestInternalNotesCard[\s\S]*?notes=\{currentInternalNotes\}/,
    );
    assert.match(page, /adminApi\.addLoanRequestNote/);
    assert.match(
        api,
        /workflowNoteStoreRoute\(loanRequestId\)\.url, \{ body \}/,
    );

    // Hidden entirely when the server did not share notes (members, applicant, pre-migration).
    assert.match(rail, /if \(!notes\.available\) \{\s*return null;/);
    assert.match(rail, /notes\.can_add \?/);
    assert.match(rail, /if \(await onAdd\(body\)\) \{\s*setDraft\(''\);/);
    assert.match(rail, /placeholder="Add a note for staff"/);
});
