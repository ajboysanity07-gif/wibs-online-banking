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
        'pages',
        'staff',
        'loan-request-show.tsx',
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

test('document checklist rows show missing-field count as plain text, not a badge', async () => {
    const pageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);

    const rowsStart = pageFile.indexOf('const missingFieldCount =');
    const rowsBlock = pageFile.slice(
        rowsStart,
        pageFile.indexOf('{showWibsTrackingSection ?', rowsStart),
    );

    assert.ok(rowsBlock.includes('missing'));
    assert.ok(!rowsBlock.includes('rounded-full'));
    assert.ok(!rowsBlock.includes('displayChecklistStatusTone'));
});
