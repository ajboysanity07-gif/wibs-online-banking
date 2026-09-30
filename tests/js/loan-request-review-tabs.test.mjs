import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';

const path = (...parts) => resolve('resources', 'js', ...parts);
const read = (...parts) => readFile(path(...parts), 'utf8');
const load = (...parts) => import(pathToFileURL(path(...parts)).href);

test('review tab hash parsing falls back to overview', async () => {
    const { parseReviewTabHash, REVIEW_TABS } = await load(
        'lib',
        'loan-request-review-tab.ts',
    );

    assert.deepEqual(
        REVIEW_TABS.map((tab) => tab.id),
        ['overview', 'applicant', 'co-makers', 'documents', 'activity'],
    );
    assert.equal(parseReviewTabHash('#documents'), 'documents');
    assert.equal(parseReviewTabHash('co-makers'), 'co-makers');
    assert.equal(parseReviewTabHash(''), 'overview');
    assert.equal(parseReviewTabHash('#nope'), 'overview');
});

test('arrow keys wrap around the tab row and Home/End jump to the ends', async () => {
    const { nextReviewTab } = await load('lib', 'loan-request-review-tab.ts');

    assert.equal(nextReviewTab('overview', 'ArrowRight'), 'applicant');
    assert.equal(nextReviewTab('activity', 'ArrowRight'), 'overview');
    assert.equal(nextReviewTab('overview', 'ArrowLeft'), 'activity');
    assert.equal(nextReviewTab('documents', 'Home'), 'overview');
    assert.equal(nextReviewTab('documents', 'End'), 'activity');
    assert.equal(nextReviewTab('documents', 'Enter'), null);
});

test('staff page renders every tab panel mounted, with counts and cross-tab jumps', async () => {
    const page = await read('pages', 'staff', 'loan-request-show.tsx');
    const tabs = await read(
        'components',
        'loan-request',
        'loan-request-review-tabs.tsx',
    );

    for (const id of [
        'overview',
        'applicant',
        'co-makers',
        'documents',
        'activity',
    ]) {
        assert.match(page, new RegExp(`<ReviewTabPanel id="${id}"`));
    }

    // Counts on Documents (applicable) and Activity (audit entries).
    assert.match(page, /documents: currentDocumentChecklist\.filter/);
    assert.match(page, /activity: currentAuditTrail\.length/);
    // Attention actions and "Full profile" switch tab first.
    assert.match(page, /goToTab\(\s*'overview',\s*'processing-details',?\s*\)/);
    assert.match(page, /goToTab\(\s*'documents',\s*'document-checklist',?\s*\)/);
    assert.match(page, /onFullProfile=\{\(\) => goToTab\('applicant'\)\}/);

    // Hash-backed, accessible tablist; hidden panels stay mounted.
    assert.match(tabs, /window\.addEventListener\('hashchange'/);
    assert.match(tabs, /role="tablist"/);
    assert.match(tabs, /role="tab"/);
    assert.match(tabs, /aria-selected=\{active\}/);
    assert.match(tabs, /hidden=\{tab !== id\}/);
    assert.match(tabs, /\[&::-webkit-scrollbar\]:hidden/);
});

test('processing snapshot is grouped and marks policy-locked fields', async () => {
    const panel = await read(
        'components',
        'loan-request',
        'processing-details-panel.tsx',
    );

    for (const title of [
        'Recommendation',
        'Charges',
        'Computed preview',
        'Signatories',
        'Authority to deduct',
        'Cycle status',
        'Disbursement and repayment',
    ]) {
        assert.ok(
            panel.includes(`<SnapshotGroup title="${title}"`),
            `missing group ${title}`,
        );
    }

    assert.match(panel, /repeat\(auto-fill,minmax\(min\(200px,100%\),1fr\)\)/);
    assert.match(panel, /\(locked\)/);
    assert.match(panel, /Editing requires remarks after the first save/);
});
