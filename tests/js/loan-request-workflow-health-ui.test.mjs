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
            'rounded-xl border border-border bg-muted/10',
        ),
    );
    assert.ok(cardBlock.includes('grid grid-cols-2 gap-x-6 gap-y-4'));
    assert.ok(!cardBlock.includes('sm:grid-cols-4'));
    assert.ok(!cardBlock.includes('sm:col-span-4'));

    assert.ok(cardBlock.includes('All clear — no issues detected'));
    assert.ok(cardBlock.includes('issue${workflowHealthIssueCount === 1'));

    assert.ok(pageFile.includes('PROCESSING_AGE_ISSUE_THRESHOLD_DAYS'));
});

test('staff page keeps Workflow health in the sidebar footer and moves Audit trail and Notification history to the Activity tab', async () => {
    const pageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);

    const footerStart = pageFile.indexOf('const sidebarFooterContent');
    assert.ok(footerStart !== -1);
    const footerBlock = pageFile.slice(
        footerStart,
        pageFile.indexOf('return (', footerStart),
    );

    assert.ok(footerBlock.includes('Workflow health'));
    assert.ok(!footerBlock.includes('LoanRequestAuditTrail'));
    assert.ok(!footerBlock.includes('Notification history'));

    assert.match(
        pageFile,
        /<LoanRequestDetailPage[\s\S]*?sidebarFooter=\{sidebarFooterContent\}/,
    );
    assert.match(
        pageFile,
        /<LoanRequestActivityTab[\s\S]*?notifications=\{currentNotificationHistory\}/,
    );

    const sectionWrapperMatches = [
        ...pageFile.matchAll(
            /<section className="mx-auto (?:mt-6 )?mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">/g,
        ),
    ];
    assert.equal(
        sectionWrapperMatches.length,
        2,
        'expected only the progress section and the consolidated tabbed main section',
    );

    // Inside the main section the tab panels appear in tab order.
    const mainSectionStart = sectionWrapperMatches[1].index;
    const order = [
        '<ReviewTabPanel id="overview"',
        '<ProcessingDetailsPanel',
        '<ReviewTabPanel id="applicant"',
        '<LoanRequestApplicantPanel',
        '<ReviewTabPanel id="co-makers"',
        '<LoanRequestCoMakerCard',
        '<ReviewTabPanel id="documents"',
        '<LoanRequestDocumentChecklistCard',
        '<ReviewTabPanel id="activity"',
    ].map((marker) => pageFile.indexOf(marker, mainSectionStart));

    order.forEach((index) => assert.ok(index !== -1));
    order.forEach((index, position) => {
        if (position > 0) {
            assert.ok(order[position - 1] < index);
        }
    });
});
