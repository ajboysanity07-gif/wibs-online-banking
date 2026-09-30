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

test('staff page wires conditions, gates and the review rail without touching permissions', async () => {
    const page = await read('pages', 'staff', 'loan-request-show.tsx');
    const rail = await read(
        'components',
        'loan-request',
        'loan-request-review-rail.tsx',
    );
    const card = await read(
        'components',
        'loan-request',
        'loan-request-conditions-card.tsx',
    );

    // Gate is derived only for a viewer who can already recommend.
    assert.match(
        page,
        /blockedReason = canRecommendApproval \? gates\.blockedReason : null/,
    );
    // Conditions gate only while they can actually be signed off.
    assert.match(
        page,
        /currentConditions\.available && currentConditions\.can_verify/,
    );
    assert.match(page, /adminApi\.updateLoanRequestCondition/);
    assert.match(page, /<LoanRequestConditionsCard/);
    assert.match(page, /<LoanRequestReadyCard/);
    assert.match(page, /<LoanRequestStatusRailCard/);
    assert.match(page, /<LoanRequestHealthCard/);
    assert.match(page, /categoryConfirmation=/);

    assert.match(rail, /Ready to recommend\?/);
    assert.match(rail, /gates\.openCount === 1 \? '' : 's'\} open/);
    assert.match(rail, /What happens next:/);
    assert.match(rail, /Loan Manager decides/);

    // Read-only for viewers who lack the permission; pending deployment copy.
    assert.match(card, /disabled=\{[\s\S]*?readOnly/);
    assert.match(card, /pending deployment/);
    assert.match(card, /type="checkbox"/);
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
