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
        'Under Review',
        'Needs Revision',
        'Recommended for Approval',
        'Rejected',
        'Converted to Loan',
    ]) {
        assert.match(file, new RegExp(label));
    }
});

test('loan request queue surfaces keep pending review distinct and expose workflow filters', async () => {
    const queueFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-queue-page.tsx',
    ]);
    const adminPageFile = await readSource([
        'resources',
        'js',
        'pages',
        'admin',
        'requests.tsx',
    ]);
    const staffPageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-requests.tsx',
    ]);

    assert.match(queueFile, /pending_review/);
    assert.match(queueFile, /needs_revision/);
    assert.match(queueFile, /recommended_for_approval/);
    assert.match(queueFile, /rejected/);
    assert.match(queueFile, /converted_to_loan/);
    assert.match(queueFile, /reported/);
    assert.doesNotMatch(queueFile, /status === 'pending_review' \|\|/);
    assert.match(adminPageFile, /workspace="admin"/);
    assert.match(staffPageFile, /workspace="staff"/);
    assert.match(staffPageFile, /buildStaffLoanRequestQueueStatusOptions/);
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
    assert.match(listFile, /Under Review/);
    assert.match(listFile, /Needs Revision/);
    assert.match(listFile, /Approved\/Converted/);
});
