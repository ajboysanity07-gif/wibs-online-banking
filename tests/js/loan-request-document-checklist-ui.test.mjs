import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => {
    return readFile(resolve(...segments), 'utf8');
};

test('document checklist renders as a flat row list with an overflow menu, not the old grid card', async () => {
    const pageFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-document-checklist-card.tsx',
    ]);

    assert.ok(!pageFile.includes('grid-cols-1 gap-4 lg:grid-cols-2'));
    assert.ok(pageFile.includes('DropdownMenu'));
    assert.ok(pageFile.includes('DropdownMenuTrigger'));
    assert.ok(pageFile.includes('MoreHorizontal'));

    assert.ok(!pageFile.includes('expandedDocumentBlockers'));
    assert.ok(!pageFile.includes('toggleDocumentBlockers'));
    assert.ok(!pageFile.includes('Show details'));
    assert.ok(!pageFile.includes('dotColorClass'));

    assert.ok(pageFile.includes('checklistStatusIcon'));
    assert.ok(pageFile.includes("document.status !== 'incomplete'"));
});

test('document checklist rows show a status badge and the missing-field count as plain text', async () => {
    const pageFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-document-checklist-card.tsx',
    ]);

    const rowsStart = pageFile.indexOf('const missingFieldCount =');
    const rowsBlock = pageFile.slice(
        rowsStart,
        pageFile.indexOf('<Dialog', rowsStart),
    );

    // The redesigned rows carry a status icon circle and a status badge; the
    // missing-field count itself stays plain destructive text.
    assert.ok(rowsBlock.includes('missing'));
    assert.match(
        rowsBlock,
        /<span className="text-xs text-destructive">\s*\{missingFieldCount\}/,
    );
    assert.ok(rowsBlock.includes('displayChecklistStatusTone'));
});

test('document package card uses workspace status wording and a ready-documents action', async () => {
    const card = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-document-checklist-card.tsx',
        ),
        'utf8',
    );

    for (const [status, label] of [
        ['ready_to_generate', 'Ready'],
        ['generated_current', 'Current'],
        ['generated_stale', 'Outdated'],
        ['incomplete', 'Incomplete'],
        ['awaiting_member_confirmation', 'Awaiting member'],
        ['not_applicable', 'Not applicable'],
        ['generation_failed', 'Failed'],
    ]) {
        assert.ok(card.includes(`${status}: '${label}'`), status);
    }

    assert.match(card, /Document package/);
    assert.match(card, /applicable documents generated and current\./);
    // Nothing selected: generate every ready/outdated/failed document.
    assert.match(
        card,
        /selectedKeys\.size > 0 \? \[\.\.\.selectedKeys\] : readyKeys/,
    );
    assert.match(card, /'Generate ready documents'/);
    assert.match(card, /onClick=\{onFixMissingFields\}/);
    assert.match(card, /Download all as ZIP/);
});
