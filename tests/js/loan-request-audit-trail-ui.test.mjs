import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => readFile(resolve(...segments), 'utf8');

test('loan request audit trail component renders workflow labels and staff metadata', async () => {
    const componentFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-audit-trail.tsx',
    ]);
    const serializerFile = await readSource([
        'app',
        'Services',
        'LoanRequests',
        'LoanRequestPayloadSerializer.php',
    ]);

    for (const label of [
        'Submitted',
        'Review Started',
        'Recommended for Approval',
        'Converted to Loan',
    ]) {
        assert.match(serializerFile, new RegExp(label));
    }

    assert.match(componentFile, /entry\.action_label/);
    assert.match(componentFile, /Actor:/);
    assert.match(componentFile, /formatDateTime/);
    assert.match(componentFile, /audience === 'staff'/);
    assert.match(componentFile, /entry\.metadata\.length > 0/);
    assert.match(componentFile, /No workflow history available yet\./);
});

test('loan request detail pages wire the audit trail for staff and member audiences', async () => {
    const detailFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-detail-page.tsx',
    ]);
    const adminPageFile = await readSource([
        'resources',
        'js',
        'pages',
        'admin',
        'loan-request-show.tsx',
    ]);
    const staffPageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);
    const clientPageFile = await readSource([
        'resources',
        'js',
        'pages',
        'client',
        'loan-request-show.tsx',
    ]);

    assert.match(detailFile, /LoanRequestAuditTrail/);
    assert.match(detailFile, /auditTrailAudience = 'staff'/);
    assert.match(adminPageFile, /auditTrailAudience="staff"/);
    assert.match(staffPageFile, /auditTrailAudience="staff"/);
    assert.match(clientPageFile, /auditTrailAudience="member"/);
    assert.match(clientPageFile, /setCurrentAuditTrail/);
});
