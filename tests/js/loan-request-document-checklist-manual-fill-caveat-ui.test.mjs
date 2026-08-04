import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => {
    return readFile(resolve(...segments), 'utf8');
};

test('document checklist card shows a card-level manual-fill alert for old records with blank fields', async () => {
    const cardFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-document-checklist-card.tsx',
    ]);

    assert.match(cardFile, /is_relaxed_old_record/);
    assert.match(cardFile, /manual_fill_fields/);
    assert.match(cardFile, /<Alert variant="warning"/);
    assert.match(
        cardFile,
        /filled out\s+manually\s+by\s+the\s+member\s+in\s+person\s+during\s+release/,
    );
    assert.match(cardFile, /Old record — the following fields are blank/);
    assert.match(cardFile, /Manual fill required at release/);
    assert.match(cardFile, /relaxedEntries\.map/);
    assert.match(cardFile, /list-disc pl-5/);
    assert.match(
        cardFile,
        /document\.manual_fill_fields\.map/,
        'each document lists its own blank fields',
    );

    assert.doesNotMatch(
        cardFile,
        /pl-6 text-\[11px\] font-medium text-amber-700/,
        'per-row inline warning paragraph should be removed',
    );
});

test('loan request checklist item type exposes the old-record manual-fill fields', async () => {
    const typesFile = await readSource([
        'resources',
        'js',
        'types',
        'loan-requests.ts',
    ]);

    assert.match(typesFile, /is_relaxed_old_record: boolean/);
    assert.match(typesFile, /manual_fill_fields: string\[\]/);
});
