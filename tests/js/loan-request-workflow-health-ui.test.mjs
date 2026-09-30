import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => {
    return readFile(resolve(...segments), 'utf8');
};

test('workflow health card is a rail card with a badge, processing age and pending member action', async () => {
    const railFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-review-rail.tsx',
    ]);
    const pageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);

    const cardStart = railFile.indexOf('export function LoanRequestHealthCard');
    assert.ok(cardStart !== -1);
    const cardBlock = railFile.slice(cardStart);

    assert.ok(cardBlock.includes('All clear'));
    assert.ok(cardBlock.includes("issue{issueCount === 1 ? '' : 's'}"));
    assert.ok(cardBlock.includes('Processing age'));
    assert.ok(cardBlock.includes('Member action'));
    assert.match(
        pageFile,
        /<LoanRequestHealthCard[\s\S]*?issueCount=\{workflowHealthIssueCount\}/,
    );
    assert.ok(pageFile.includes('PROCESSING_AGE_ISSUE_THRESHOLD_DAYS'));
});

test('staff page moves Audit trail and Notification history to the History section and keeps the rail out of the section panels', async () => {
    const pageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);

    assert.doesNotMatch(pageFile, /sidebarFooterContent/);
    assert.doesNotMatch(pageFile, /<LoanRequestDetailPage/);
    assert.match(
        pageFile,
        /<LoanRequestActivityTab[\s\S]*?notifications=\{currentNotificationHistory\}/,
    );

    const sectionWrapperMatches = [
        ...pageFile.matchAll(
            /<section className="mx-auto my-5 w-full max-w-\[1440px\] px-4 sm:px-6 lg:px-8">/g,
        ),
    ];
    assert.equal(
        sectionWrapperMatches.length,
        1,
        'expected only the three-pane workspace section (progress lives in the record header)',
    );

    // Inside the workspace section: section list, then panels in a fixed order.
    const mainSectionStart = sectionWrapperMatches[0].index;
    const order = [
        '<LoanRequestReviewTabs',
        '<ReviewTabPanel id="summary"',
        '<ReviewTabPanel id="terms"',
        '<ProcessingDetailsPanel',
        '<ReviewTabPanel id="signatories"',
        '<ReviewTabPanel id="applicant"',
        '<LoanRequestApplicantPanel',
        '<LoanRequestCoMakerCard',
        '<ReviewTabPanel id="docs"',
        '<LoanRequestDocumentChecklistCard',
        '<ReviewTabPanel id="history"',
    ].map((marker) => pageFile.indexOf(marker, mainSectionStart));

    order.forEach((index) => assert.ok(index !== -1));
    order.forEach((index, position) => {
        if (position > 0) {
            assert.ok(order[position - 1] < index);
        }
    });
});
