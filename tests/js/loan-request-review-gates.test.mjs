import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';

const path = (...parts) => resolve('resources', 'js', ...parts);
const read = (...parts) => readFile(path(...parts), 'utf8');
const load = (...parts) => import(pathToFileURL(path(...parts)).href);

const docs = (statuses) =>
    statuses.map((status) => ({ is_applicable: true, status }));

test('recommend gates name every open gate and clear only when all are clear', async () => {
    const { buildRecommendGates } = await load('lib', 'loan-request-gates.ts');

    const open = buildRecommendGates({
        conditions: { done: 3, total: 5 },
        blockingCount: 1,
        exceptionRowCount: 2,
        documents: docs(['generated_current', 'ready_to_generate']),
        enforced: true,
    });

    assert.equal(open.openCount, 3);
    assert.equal(
        open.blockedReason,
        '2 conditions - 1 exception - documents not all current (1/2) to clear before you can recommend approval.',
    );
    assert.deepEqual(open.exceptions, { done: 1, total: 2 });

    const clear = buildRecommendGates({
        conditions: { done: 5, total: 5 },
        blockingCount: 0,
        exceptionRowCount: 1,
        documents: docs(['generated_current']),
        enforced: true,
    });

    assert.equal(clear.openCount, 0);
    assert.equal(clear.blockedReason, null);
});

test('gates only disable: not enforced, or conditions unavailable, never block', async () => {
    const { buildRecommendGates } = await load('lib', 'loan-request-gates.ts');

    const legacy = buildRecommendGates({
        conditions: { done: 0, total: 5 },
        blockingCount: 2,
        exceptionRowCount: 2,
        documents: docs(['ready_to_generate']),
        enforced: false,
    });

    assert.equal(legacy.blockedReason, null);

    // Conditions table not deployed / viewer can't sign off: not counted.
    const noConditions = buildRecommendGates({
        conditions: null,
        blockingCount: 0,
        exceptionRowCount: 0,
        documents: docs(['generated_current']),
        enforced: true,
    });

    assert.equal(noConditions.conditions, null);
    assert.equal(noConditions.blockedReason, null);
    assert.equal(noConditions.openCount, 0);

    // Non-applicable documents never count against the gate.
    const notApplicable = buildRecommendGates({
        conditions: null,
        blockingCount: 0,
        exceptionRowCount: 0,
        documents: [
            { is_applicable: true, status: 'generated_current' },
            { is_applicable: false, status: 'not_applicable' },
        ],
        enforced: true,
    });

    assert.deepEqual(notApplicable.documents, { done: 1, total: 1 });
});

test('task exceptions: blocking rows open, confirmed category resolved, fix routes', async () => {
    const { buildTaskExceptions, summarizeTasks } = await load(
        'lib',
        'loan-request-gates.ts',
    );

    const exceptions = buildTaskExceptions({
        rows: [
            {
                key: 'blockers-authority_to_deduct',
                tone: 'blocking',
                title: 'Authority to Deduct: missing information',
                description: 'Officer 1 name is required.',
                action: { key: 'open-documents' },
            },
            {
                key: 'failed-plan_of_payment',
                tone: 'blocking',
                title: 'Plan of Payment failed to generate',
                description: 'Try again.',
                action: { key: 'open-documents' },
            },
            {
                key: 'stale-documents',
                tone: 'warning',
                title: '1 document out of date',
                description: '',
            },
        ],
        categoryConfirmedAfterMismatch: true,
        isSignatoryBlocker: (key) => key === 'blockers-authority_to_deduct',
    });

    // Warnings never become exceptions; resolved category leads.
    assert.deepEqual(
        exceptions.map((e) => [e.key, e.open, e.action?.key ?? null]),
        [
            ['category-mismatch', false, null],
            ['blockers-authority_to_deduct', true, 'fix-signatories'],
            ['failed-plan_of_payment', true, 'open-documents'],
        ],
    );

    const category = buildTaskExceptions({
        rows: [
            {
                key: 'category-mismatch',
                tone: 'blocking',
                title: 'x',
                description: 'y',
                action: { key: 'edit-category' },
            },
        ],
        categoryConfirmedAfterMismatch: false,
        isSignatoryBlocker: () => false,
    });

    assert.deepEqual(category[0].action, {
        key: 'confirm-category',
        label: 'Confirm',
    });

    assert.deepEqual(
        summarizeTasks({
            conditions: { done: 3, total: 5 },
            exceptions,
            documents: { done: 12, total: 13 },
        }),
        {
            done: 4,
            total: 9,
            left: '2 conditions, 2 exceptions, the document package',
        },
    );
    assert.deepEqual(
        summarizeTasks({
            conditions: null,
            exceptions: [],
            documents: { done: 2, total: 2 },
        }),
        { done: 1, total: 1, left: null },
    );
});

test('staff page wires the task panel without touching permissions', async () => {
    const page = await read('pages', 'staff', 'loan-request-show.tsx');
    const rail = await read(
        'components',
        'loan-request',
        'loan-request-review-rail.tsx',
    );
    const actions = await read(
        'components',
        'loan-request',
        'loan-request-workflow-actions.tsx',
    );

    // Gate is derived only for a viewer who can already recommend.
    assert.match(
        page,
        /blockedReason = canRecommendApproval \? gates\.blockedReason : null/,
    );
    assert.match(
        page,
        /currentConditions\.available && currentConditions\.can_verify/,
    );
    assert.match(page, /adminApi\.updateLoanRequestCondition/);
    assert.match(page, /<LoanRequestTasksCard/);
    assert.match(page, /<LoanRequestStatusRailCard/);
    assert.doesNotMatch(page, /LoanRequestConditionsCard/);
    assert.doesNotMatch(page, /LoanRequestHealthCard/);
    // Rail button exists only for viewers who may recommend, uses the same gate,
    // and opens the header's existing dialog (one submit path).
    assert.match(
        page,
        /recommend=\{\s*canRecommendApproval\s*\?\s*\{\s*disabledReason: blockedReason/,
    );
    assert.match(page, /openRecommendSignal=\{openRecommendSignal\}/);
    assert.match(
        actions,
        /workflow\?\.recommendApproval\?\.show && !recommendBlockedReason/,
    );

    assert.match(rail, /Package current/);
    assert.match(rail, /Not verified/);
    assert.match(rail, /cursor-not-allowed opacity-45/);
    assert.match(rail, /aria-disabled=\{recommend\.disabledReason !== null\}/);
    assert.match(rail, /What happens next:/);
    assert.match(rail, /pending deployment/);
    assert.match(rail, /disabled=\{[\s\S]*?readOnlyConditions/);
});

test('audit entries are new only within the last 24 hours and tolerate bad dates', async () => {
    const { isRecentAuditEntry } = await load('lib', 'loan-request-audit.ts');
    const now = Date.parse('2026-09-30T12:00:00Z');

    assert.equal(isRecentAuditEntry('2026-09-30T00:00:00Z', now), true);
    assert.equal(isRecentAuditEntry('2026-09-29T11:59:00Z', now), false);
    assert.equal(isRecentAuditEntry('2026-10-01T00:00:00Z', now), false);
    assert.equal(isRecentAuditEntry('not a date', now), false);
    assert.equal(isRecentAuditEntry(null, now), false);
});
