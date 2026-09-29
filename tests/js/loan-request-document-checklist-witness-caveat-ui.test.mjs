import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => {
    return readFile(resolve(...segments), 'utf8');
};

test('document checklist shows the witness-2 auto-fill caveat for loan information and promissory note', async () => {
    const checklistCardFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-document-checklist-card.tsx',
    ]);

    assert.match(
        checklistCardFile,
        /Witness 2\s+recorded\s+automatically at\s+approval if left\s+blank/,
    );
    assert.match(
        checklistCardFile,
        /showWitnessTwoCaveat\s*=\s*\n?\s*document\.key\s*===\s*\n?\s*'loan_information'\s*\|\|\s*\n?\s*document\.key\s*===\s*\n?\s*'promissory_note'/,
    );
});

test('processing details witness-2 field is disabled with an auto-fill placeholder and tooltip', async () => {
    const processingPanelFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'processing-details-panel.tsx',
    ]);

    assert.match(
        processingPanelFile,
        /renderProcessingField\('witness_two_name',\s*\{\s*\n\s*disabled:\s*true,[\s\S]*?'Filled automatically upon approval'[\s\S]*?"Recorded automatically using the approving manager's name when the request is approved\."[\s\S]*?\}\)/,
    );
    assert.match(
        processingPanelFile,
        /options\?\.tooltip &&[\s\S]{0,300}<TooltipTrigger>[\s\S]{0,100}<Info /,
    );
});
