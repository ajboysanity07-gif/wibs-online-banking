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
    assert.match(
        page,
        /lg:col-span-full xl:sticky xl:top-\[72px\] xl:col-span-1/,
    );
    // Attention actions and "Full profile" switch section first.
    assert.match(page, /goToTab\(\s*'terms',\s*'processing-details',?\s*\)/);
    assert.match(page, /goToTab\(\s*'docs',\s*'document-checklist',?\s*\)/);
    assert.match(
        page,
        /onFullProfile=\{\(\) =>\s*goToTab\('applicant'\)\s*\}/,
    );
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

test('processing panel splits into terms and signatories views with one save path', async () => {
    const panel = await read(
        'components',
        'loan-request',
        'processing-details-panel.tsx',
    );
    const page = await read('pages', 'staff', 'loan-request-show.tsx');

    // Two views of the same record; the page mounts one of each.
    assert.match(panel, /view\?: 'all' \| 'terms' \| 'signatories'/);
    assert.match(page, /view="terms"/);
    assert.match(page, /view="signatories"/);
    assert.equal(
        page.match(/\{\.\.\.processingPanelProps\}/g)?.length,
        2,
        'both panels share the same props and save endpoint',
    );
    // Only the terms instance mirrors net proceeds and previews the checklist.
    assert.match(
        page,
        /view="terms"\s*onDocumentChecklistPreview=\{\s*applyDocumentChecklistPreview\s*\}\s*onPreviewChange=\{\s*setProcessingPreview\s*\}/,
    );
    // Every save still sends the full processing payload + passthrough.
    assert.match(
        panel,
        /processing: buildInlineProcessingPayload\(processingForm\.processing\)/,
    );
    assert.match(panel, /loan_request: buildLoanRequestPassthrough\(\)/);
    // Signatories never call the preview endpoint.
    assert.match(
        panel,
        /if \(!canUpdateProcessing \|\| view === 'signatories'\) \{\s*return;/,
    );

    for (const title of [
        'Signatories',
        'Authority to deduct',
        'Insurance cycle',
        'Disbursement and repayment',
    ]) {
        assert.ok(
            panel.includes(`title="${title}"`),
            `missing group ${title}`,
        );
    }

    // Read view: charges table with Policy tags, totals and net proceeds.
    for (const label of [
        'Service charge',
        'Loan security / savings',
        'Insurance premium',
        'Documentary stamp',
        'Notarial fee',
        'Other charges',
    ]) {
        assert.ok(panel.includes(`label: '${label}'`), `missing ${label}`);
    }
    assert.match(panel, /<PolicyTag \/>/);
    assert.match(panel, /Total deductions/);
    assert.match(panel, /bg-secondary px-3\.5 py-3 text-secondary-foreground/);
    assert.match(panel, /Penalty rate/);
    // Amounts come from the backend preview, never a client formula.
    assert.match(panel, /baselinePreview\?\.net_proceeds_raw/);
    assert.match(panel, /onPreviewChange\?\.\(baselinePreview\)/);
});

test('terms edit tracks changes, gates save and recomputes live via the backend', async () => {
    const panel = await read(
        'components',
        'loan-request',
        'processing-details-panel.tsx',
    );

    // Debounced backend preview on every keystroke.
    assert.match(
        panel,
        /setTimeout\(\s*\(\) => void recalculateGnthpRef\.current\(false\),\s*250,?\s*\)/,
    );
    assert.doesNotMatch(panel, /scheduleGnthpRecalculation/);
    assert.match(panel, /adminApi\.previewLoanRequestProcessingDetails/);

    // Changed markers and the save gate.
    assert.match(panel, /was \{previous === '—' \? 'blank' : previous\}/);
    assert.match(panel, /isChanged\(key\) \? 'border-primary' : undefined/);
    assert.match(
        panel,
        /isFirstProcessingSave \|\| \(changedKeys\.length > 0 && !isRemarksMissing\)/,
    );
    assert.match(panel, /disabled=\{isProcessing \|\| !canSaveProcessing\}/);
    assert.match(panel, /' · remarks required'/);
    assert.match(panel, /'Save terms'/);

    // Live computation and warnings.
    assert.match(panel, /Live computation/);
    assert.match(panel, /vs saved/);
    assert.match(panel, /Term under 2 months: insurance does not apply\./);
    assert.match(panel, /Recommended amount must be greater than 0\./);
    assert.match(panel, /The manager will see this flagged\./);

    // Saved banner flags stale documents.
    assert.match(panel, /Saved\. Changes are recorded in the audit trail\./);
    assert.match(panel, /document\.status === 'generated_stale'/);
});

test('change detection treats equal numbers as unchanged', async () => {
    const panel = await read(
        'components',
        'loan-request',
        'processing-details-panel.tsx',
    );
    const body = panel.match(
        /const sameFormValue = \(([\s\S]*?)\n\};/,
    )?.[0];

    assert.ok(body, 'expected sameFormValue');

    const sameFormValue = new Function(
        `${body.replace(/: [^,)=]+(?=[,)=])/g, '').replace(/\): boolean =>/, ') =>')}; return sameFormValue;`,
    )();

    assert.equal(sameFormValue('30000', '30000.00'), true);
    assert.equal(sameFormValue(0.02, '0.02'), true);
    assert.equal(sameFormValue(null, ''), true);
    assert.equal(sameFormValue('', '0'), false);
    assert.equal(sameFormValue('Monthly', 'Due date'), false);
});

test('missing signatory fields follow witness auto-fill and authority to deduct', async () => {
    const { missingSignatoryFields } = await load(
        'lib',
        'loan-request-review-tab.ts',
    );
    const applicable = {
        authorityToDeductApplicable: true,
        witnessOneFallback: null,
    };

    assert.deepEqual(missingSignatoryFields({}, applicable), [
        'witness_one_name',
        'authority_to_deduct_officer_1_name',
    ]);
    // Witness 1 is filled from the assigned processor on save.
    assert.deepEqual(
        missingSignatoryFields(
            { authority_to_deduct_officer_1_name: 'Juan' },
            { ...applicable, witnessOneFallback: 'Processor' },
        ),
        [],
    );
    // Officers marked unknown, or no authority to deduct: not required.
    assert.deepEqual(
        missingSignatoryFields(
            {
                witness_one_name: 'P',
                authority_to_deduct_officers_unknown: true,
            },
            applicable,
        ),
        [],
    );
    assert.deepEqual(
        missingSignatoryFields(
            { witness_one_name: 'P' },
            { ...applicable, authorityToDeductApplicable: false },
        ),
        [],
    );
});
