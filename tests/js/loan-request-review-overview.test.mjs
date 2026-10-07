import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';

const path = (...parts) => resolve('resources', 'js', ...parts);
const read = (...parts) => readFile(path(...parts), 'utf8');
// Type-only imports are stripped, so the pure lib modules load directly.
const load = (...parts) => import(pathToFileURL(path(...parts)).href);

test('progress mapping covers every workflow status', async () => {
    const { resolveLoanRequestProgress, PROGRESS_STEPS } = await load(
        'lib',
        'loan-request-progress.ts',
    );
    const types = await read('types', 'loan-requests.ts');
    const block = types.match(/export type LoanRequestStatusValue =([^;]+);/);
    const statuses = [...block[1].matchAll(/'([a-z_]+)'/g)].map((m) => m[1]);

    assert.ok(statuses.length >= 19);

    for (const status of statuses) {
        const progress = resolveLoanRequestProgress(status);

        if (status === 'cancelled') {
            assert.deepEqual(progress, { step: -1, held: true });
        } else {
            assert.ok(
                progress.step >= 0 && progress.step <= PROGRESS_STEPS.length,
                `${status} has no step`,
            );
        }
    }

    assert.deepEqual(resolveLoanRequestProgress('under_review'), {
        step: 2,
        held: false,
    });
    // Off-path statuses keep their step and flag a status badge.
    assert.deepEqual(resolveLoanRequestProgress('needs_revision'), {
        step: 2,
        held: true,
    });
    assert.deepEqual(resolveLoanRequestProgress('rejected'), {
        step: 2,
        held: true,
    });
    assert.deepEqual(resolveLoanRequestProgress('declined'), {
        step: 3,
        held: true,
    });
    assert.equal(resolveLoanRequestProgress('released').step, 5);
    // Cancelled keeps the step it was cancelled from.
    assert.deepEqual(resolveLoanRequestProgress('cancelled', 'under_review'), {
        step: 2,
        held: true,
    });
});

const health = {
    processing_age_days: 1,
    stale_document_count: 0,
    failed_document_count: 0,
    legacy_blocker_count: 0,
    pending_member_action: false,
    notification_failure_count: 0,
    workflow_failed_job_count: 0,
};
const doc = (over) => ({
    key: 'application_form',
    label: 'Application form',
    is_applicable: true,
    status: 'generated_current',
    blockers: [],
    failure_message: null,
    ...over,
});
const base = {
    categoryMismatch: false,
    canEditCategory: true,
    documents: [doc()],
    health,
    processingAgeTargetDays: 3,
    memberAction: null,
    problemLoanMessage: null,
    managerStage: null,
};

test('attention rows derive from data and clear when it clears', async () => {
    const { buildAttentionRows } = await load(
        'lib',
        'loan-request-attention.ts',
    );

    assert.deepEqual(buildAttentionRows(base), { rows: [], blockingCount: 0 });

    const open = buildAttentionRows({
        ...base,
        categoryMismatch: true,
        documents: [
            doc({ blockers: ['officer 1 name'] }),
            doc({
                key: 'promissory_note',
                label: 'Note',
                is_applicable: false,
                blockers: ['x'],
            }),
        ],
        memberAction: { message: 'Confirm undertaking' },
        health: { ...health, processing_age_days: 3 },
    });

    // Not-applicable documents never raise a row.
    assert.equal(open.blockingCount, 2);
    assert.deepEqual(
        open.rows.map((r) => r.tone),
        ['blocking', 'blocking', 'warning', 'info'],
    );
    assert.equal(open.rows[0].action.key, 'edit-category');
    assert.equal(open.rows.at(-1).tag, 'Waiting on member');

    // Viewer without edit rights gets the row but no action.
    const readOnly = buildAttentionRows({
        ...base,
        categoryMismatch: true,
        canEditCategory: false,
    });
    assert.equal(readOnly.blockingCount, 1);
    assert.equal(readOnly.rows[0].action, undefined);

    // Soft problem-loan warning never counts as blocking.
    const soft = buildAttentionRows({
        ...base,
        problemLoanMessage: 'Past due',
    });
    assert.equal(soft.blockingCount, 0);
    assert.equal(soft.rows[0].action.key, 'view-loan-details');
});

test('staff review Overview top is wired into the page', async () => {
    const page = await read('pages', 'staff', 'loan-request-show.tsx');
    const panel = await read(
        'components',
        'loan-request',
        'processing-details-panel.tsx',
    );
    const summary = await read(
        'components',
        'loan-request',
        'loan-request-recommendation-summary.tsx',
    );
    const detail = await read(
        'components',
        'loan-request',
        'loan-request-detail-page.tsx',
    );

    assert.match(page, /<LoanRequestDetailView/);
    assert.match(page, /<LoanRequestAttentionCard/);
    assert.match(page, /<LoanRequestRecommendationSummary/);
    assert.match(page, /<LoanRequestApplicantSnapshot/);
    // Summary: request details, applicant snapshot, then the member strip.
    assert.match(page, /Waiting on member:/);
    assert.match(page, /This does not block your recommendation\./);
    assert.match(
        page,
        /memberWaitBlocks =\s*currentRequest\.member_action_type !== null \|\|\s*\(isV2Workflow && awaitingMemberDocuments\.length > 0\)/,
    );
    // Replaced banners are gone from the page.
    assert.doesNotMatch(page, /<LoanStatusWarning/);
    assert.doesNotMatch(page, /Pending member action<\/AlertTitle>/);
    assert.doesNotMatch(page, /managerStageAlert\.tone === 'ready'/);
    // The Applicant tab panel never repeats the category mismatch alert.
    assert.match(page, /<LoanRequestApplicantPanel/);
    // Phase 4 reads this as gate 2.
    assert.match(page, /attention\.blockingCount/);
    // Net proceeds come from the panel's existing preview, not a second call.
    assert.match(panel, /onPreviewChange\?\.\(baselinePreview\)/);
    assert.match(summary, /preview\?\.net_proceeds_raw/);
    assert.match(detail, /LoanRequestFactGrid/);
    assert.match(detail, /title="Request details"/);
});

test('category mismatch only blocks while it can still be confirmed', async () => {
    const { buildAttentionRows } = await load(
        'lib',
        'loan-request-attention.ts',
    );
    const mismatch = { ...base, categoryMismatch: true };

    assert.equal(buildAttentionRows(mismatch).blockingCount, 1);
    assert.equal(
        buildAttentionRows({ ...mismatch, categoryConfirmed: false })
            .blockingCount,
        1,
    );
    // Confirmed with the member: row is gone.
    assert.deepEqual(
        buildAttentionRows({ ...mismatch, categoryConfirmed: true }),
        { rows: [], blockingCount: 0 },
    );
    // No way to confirm here (table not deployed / can't sign off): warn only.
    const unconfirmable = buildAttentionRows({
        ...mismatch,
        categoryConfirmed: null,
    });
    assert.equal(unconfirmable.blockingCount, 0);
    assert.equal(unconfirmable.rows[0].tone, 'warning');
});
