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
        'Pending Processing',
        'In Processing',
        'Awaiting Member Correction',
        'For Loan Manager Review',
        'Rejected During Processing',
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
    assert.match(queueFile, /Assigned Loan Processor/);
    assert.match(queueFile, /assignmentFilters/);
    assert.match(queueFile, /assignmentOfficers/);
    assert.match(queueFile, /All loan processors/);
    assert.match(queueFile, /High workload/);
    assert.doesNotMatch(queueFile, /status === 'pending_review' \|\|/);
    assert.match(adminPageFile, /workspace="admin"/);
    assert.match(staffPageFile, /workspace="staff"/);
    assert.match(staffPageFile, /buildStaffLoanRequestQueueStatusOptions/);
    assert.doesNotMatch(staffPageFile, /auth\.isAdmin/);
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
    assert.match(detailFile, /Approved - awaiting processing in WIBS\./);
    assert.match(listFile, /Pending Processing/);
    assert.match(listFile, /In Processing/);
    assert.match(listFile, /Awaiting Member Correction/);
    assert.match(listFile, /Approved\/Converted/);
});
