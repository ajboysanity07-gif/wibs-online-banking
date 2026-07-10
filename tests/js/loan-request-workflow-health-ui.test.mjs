import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => {
    return readFile(resolve(...segments), 'utf8');
};

test('workflow health card renders as a summary bar plus fixed 2-column plain grid, not the old bordered tiles', async () => {
    const pageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);

    const cardStart = pageFile.indexOf('Workflow health');
    const cardBlock = pageFile.slice(
        cardStart,
        pageFile.indexOf('Notification history', cardStart),
    );

    assert.ok(
        !cardBlock.includes(
            'rounded-xl border border-border/40 bg-muted/10',
        ),
    );
    assert.ok(cardBlock.includes('grid gap-x-6 gap-y-4 grid-cols-2'));
    assert.ok(!cardBlock.includes('sm:grid-cols-4'));
    assert.ok(!cardBlock.includes('sm:col-span-4'));

    assert.ok(cardBlock.includes('All clear — no issues detected'));
    assert.ok(cardBlock.includes('issue${workflowHealthIssueCount === 1'));

    assert.ok(pageFile.includes('PROCESSING_AGE_ISSUE_THRESHOLD_DAYS'));
});

test('staff page composes Audit trail, Workflow health, and Notification history into the sidebar footer in that order', async () => {
    const pageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);

    const footerStart = pageFile.indexOf('const sidebarFooterContent');
    assert.ok(footerStart !== -1);

    const auditTrailIndex = pageFile.indexOf('LoanRequestAuditTrail', footerStart);
    const workflowHealthIndex = pageFile.indexOf('Workflow health', footerStart);
    const notificationHistoryIndex = pageFile.indexOf(
        'Notification history',
        footerStart,
    );

    assert.ok(auditTrailIndex !== -1);
    assert.ok(workflowHealthIndex !== -1);
    assert.ok(notificationHistoryIndex !== -1);
    assert.ok(auditTrailIndex < workflowHealthIndex);
    assert.ok(workflowHealthIndex < notificationHistoryIndex);

    assert.match(
        pageFile,
        /<LoanRequestDetailPage[\s\S]*?sidebarFooter=\{sidebarFooterContent\}/,
    );

    const applicantCardIndex = pageFile.indexOf('<LoanRequestApplicantCard');
    assert.ok(applicantCardIndex !== -1);
    const sectionBStart = pageFile.lastIndexOf(
        '<section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">',
        applicantCardIndex,
    );
    const sectionBEnd = pageFile.indexOf('</section>', sectionBStart);
    const sectionBBlock = pageFile.slice(sectionBStart, sectionBEnd);

    assert.match(sectionBBlock, /LoanRequestApplicantCard/);
    assert.match(sectionBBlock, /LoanRequestCoMakersCard/);
    assert.ok(!sectionBBlock.includes('LoanRequestAuditTrail'));
    assert.ok(!sectionBBlock.includes('Workflow health'));
    assert.ok(!sectionBBlock.includes('Notification history'));
});
