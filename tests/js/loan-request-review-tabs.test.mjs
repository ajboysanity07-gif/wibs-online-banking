import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';

const path = (...parts) => resolve('resources', 'js', ...parts);
const read = (...parts) => readFile(path(...parts), 'utf8');
const load = (...parts) => import(pathToFileURL(path(...parts)).href);

test('section hash parsing maps legacy tab hashes and falls back to summary', async () => {
    const { parseReviewTabHash, REVIEW_TABS } = await load(
        'lib',
        'loan-request-review-tab.ts',
    );

    assert.deepEqual(
        REVIEW_TABS.map((tab) => tab.id),
        ['summary', 'applicant', 'terms', 'signatories', 'docs', 'history'],
    );
    assert.equal(parseReviewTabHash('#docs'), 'docs');
    assert.equal(parseReviewTabHash('terms'), 'terms');
    assert.equal(parseReviewTabHash('#overview'), 'summary');
    assert.equal(parseReviewTabHash('#documents'), 'docs');
    assert.equal(parseReviewTabHash('#activity'), 'history');
    assert.equal(parseReviewTabHash('#co-makers'), 'applicant');
    assert.equal(parseReviewTabHash(''), 'summary');
    assert.equal(parseReviewTabHash('#nope'), 'summary');
});

test('both arrow pairs wrap around the section list and Home/End jump to the ends', async () => {
    const { nextReviewTab } = await load('lib', 'loan-request-review-tab.ts');

    assert.equal(nextReviewTab('summary', 'ArrowDown'), 'applicant');
    assert.equal(nextReviewTab('summary', 'ArrowRight'), 'applicant');
    assert.equal(nextReviewTab('history', 'ArrowDown'), 'summary');
    assert.equal(nextReviewTab('summary', 'ArrowUp'), 'history');
    assert.equal(nextReviewTab('summary', 'ArrowLeft'), 'history');
    assert.equal(nextReviewTab('docs', 'Home'), 'summary');
    assert.equal(nextReviewTab('docs', 'End'), 'history');
    assert.equal(nextReviewTab('docs', 'Enter'), null);
});

test('section badges: missing signatory blockers and current/total documents', async () => {
    const { hasMissingSignatory, documentPackageCounts } = await load(
        'lib',
        'loan-request-review-tab.ts',
    );
    const definitions = {
        processing: {
            label: 'Processing',
            fields: { witness_one_name: { label: 'Witness 1 name' } },
        },
    };
    const doc = (overrides) => ({
        is_applicable: true,
        status: 'ready_to_generate',
        blockers: [],
        ...overrides,
    });

    assert.equal(
        hasMissingSignatory(
            [doc({ blockers: ['Witness 1 name is required.'] })],
            definitions,
        ),
        true,
    );
    assert.equal(
        hasMissingSignatory(
            [
                doc({
                    is_applicable: false,
                    blockers: ['Witness 1 name is required.'],
                }),
            ],
            definitions,
        ),
        false,
    );
    assert.equal(
        hasMissingSignatory(
            [doc({ blockers: ['Notarial fee is required.'] })],
            definitions,
        ),
        false,
    );
    assert.deepEqual(
        documentPackageCounts([
            doc({ status: 'generated_current' }),
            doc({ status: 'generated_stale' }),
            doc({ is_applicable: false, status: 'generated_current' }),
        ]),
        { current: 1, total: 2 },
    );
});

test('staff page renders every section panel mounted in a three-pane grid', async () => {
    const page = await read('pages', 'staff', 'loan-request-show.tsx');
    const tabs = await read(
        'components',
        'loan-request',
        'loan-request-review-tabs.tsx',
    );

    for (const id of [
        'summary',
        'applicant',
        'terms',
        'signatories',
        'docs',
        'history',
    ]) {
        assert.match(page, new RegExp(`<ReviewTabPanel id="${id}"`));
    }

    assert.match(
        page,
        /lg:grid-cols-\[210px_minmax\(0,1fr\)\] xl:grid-cols-\[210px_minmax\(0,1fr\)_320px\]/,
    );
    assert.match(page, /max-w-\[1440px\]/);
    assert.match(
        page,
        /lg:col-span-full xl:sticky xl:top-\[72px\] xl:col-span-1/,
    );
    // Attention actions and "Full profile" switch section first.
    assert.match(page, /goToTab\(\s*'terms',\s*'processing-details',?\s*\)/);
    assert.match(page, /goToTab\(\s*'docs',\s*'document-checklist',?\s*\)/);
    assert.match(page, /onFullProfile=\{\(\) => goToTab\('applicant'\)\}/);
    assert.match(page, /hasMissingSignatory\(/);
    assert.match(page, /label: `\$\{currentAuditTrail\.length\}`/);

    // Hash-backed, accessible tablist; hidden panels stay mounted.
    assert.match(tabs, /window\.addEventListener\('hashchange'/);
    assert.match(tabs, /role="tablist"/);
    assert.match(tabs, /role="tab"/);
    assert.match(tabs, /aria-selected=\{active\}/);
    assert.match(tabs, /hidden=\{tab !== id\}/);
    assert.match(tabs, /lg:sticky lg:top-\[72px\]/);
    assert.match(tabs, /shadow-\[inset_3px_0_0_var\(--primary\)\]/);
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
