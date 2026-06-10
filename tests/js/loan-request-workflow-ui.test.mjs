import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => readFile(resolve(...segments), 'utf8');

test('loan request status badge exposes the expanded workflow labels', async () => {
    const file = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-status-badge.tsx',
    ]);

    for (const label of [
        'Pending Review',
        'Needs Revision',
        'Recommended for Approval',
        'Rejected',
        'Converted to Loan',
    ]) {
        assert.match(file, new RegExp(label));
    }
});

test('admin requests page keeps pending review distinct and exposes workflow filters', async () => {
    const file = await readSource([
        'resources',
        'js',
        'pages',
        'admin',
        'requests.tsx',
    ]);

    assert.match(file, /pending_review/);
    assert.match(file, /needs_revision/);
    assert.match(file, /recommended_for_approval/);
    assert.match(file, /rejected/);
    assert.match(file, /converted_to_loan/);
    assert.doesNotMatch(file, /status === 'pending_review' \|\|/);
});

test('client loan request pages surface revision and conversion workflow states', async () => {
    const detailFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-detail-page.tsx',
    ]);
    const listFile = await readSource([
        'resources',
        'js',
        'pages',
        'client',
        'loan-requests.tsx',
    ]);

    assert.match(detailFile, /Revision remarks/);
    assert.match(detailFile, /converted_to_loan/);
    assert.match(listFile, /Pending Review/);
    assert.match(listFile, /Needs Revision/);
    assert.match(listFile, /Approved\/Converted/);
});
