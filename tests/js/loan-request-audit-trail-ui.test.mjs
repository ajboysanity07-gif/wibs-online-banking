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

test('loan request audit trail component supports a compact sidebar mode', async () => {
    const componentFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-audit-trail.tsx',
    ]);
    const detailFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-detail-page.tsx',
    ]);
    const staffPageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);

    assert.match(componentFile, /compact\?: boolean;/);
    assert.match(componentFile, /compact = false,/);
    assert.match(
        componentFile,
        /compact\s*\?\s*'flex flex-col gap-1'\s*:\s*'flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between'/,
    );
    assert.match(
        componentFile,
        /compact\s*\?\s*'grid gap-2'\s*:\s*'grid gap-2 sm:grid-cols-2'/,
    );

    assert.match(detailFile, /sidebarFooter\?: ReactNode;/);
    assert.match(detailFile, /\{sidebarFooter \?\? null\}/);

    assert.match(staffPageFile, /<LoanRequestAuditTrail[\s\S]*?compact[\s\S]*?\/>/);
    assert.match(staffPageFile, /sidebarFooter=\{sidebarFooterContent\}/);
});

test('loan request audit trail component shows the most recent entry first and is collapsible', async () => {
    const componentFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-audit-trail.tsx',
    ]);

    assert.match(
        componentFile,
        /orderedEntries\s*=\s*useMemo\(\(\)\s*=>\s*\[\.\.\.entries\]\.reverse\(\),\s*\[entries\]\)/,
    );
    assert.match(componentFile, /orderedEntries\.map\(\(entry\)/);
    assert.match(
        componentFile,
        /from\s+'@\/components\/ui\/collapsible'/,
    );
    assert.match(componentFile, /<Collapsible open=\{open\} onOpenChange=\{setOpen\}>/);
    assert.match(componentFile, /<CollapsibleTrigger asChild>/);
    assert.match(componentFile, /<CollapsibleContent>/);
});

test('loan request detail page keeps the audit trail in the sidebar and offers a processing-details slot after co-makers', async () => {
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

    const mainColumnStart = detailFile.indexOf('!hideMainColumn ? (');
    const mainColumnEnd = detailFile.indexOf(
        ') : null}',
        mainColumnStart,
    );
    const mainColumnBlock = detailFile.slice(mainColumnStart, mainColumnEnd);

    assert.match(mainColumnBlock, /LoanRequestCoMakersCard/);
    assert.match(mainColumnBlock, /\{processingDetails \?\? null\}/);
    assert.doesNotMatch(mainColumnBlock, /LoanRequestAuditTrail/);

    const sidebarAuditTrailIndex = detailFile.indexOf(
        '<LoanRequestAuditTrail',
        mainColumnEnd,
    );
    const sidebarFooterIndex = detailFile.indexOf(
        '{sidebarFooter ?? null}',
        mainColumnEnd,
    );

    assert.ok(sidebarAuditTrailIndex !== -1);
    assert.ok(sidebarAuditTrailIndex < sidebarFooterIndex);
    assert.match(detailFile, /processingDetails\?: ReactNode;/);

    assert.doesNotMatch(
        adminPageFile,
        /<section[^>]*>\s*<ProcessingDetailsPanel/,
    );
    assert.match(adminPageFile, /processingDetails=\{/);
    assert.match(
        adminPageFile,
        /processingDetails=\{[\s\S]*?<ProcessingDetailsPanel/,
    );
});
