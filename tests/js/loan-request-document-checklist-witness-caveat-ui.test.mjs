import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => {
    return readFile(resolve(...segments), 'utf8');
};

test('staff loan request page shows the witness-2 auto-fill caveat under the Personnel and document checklist sections', async () => {
    const staffShowPageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);

    assert.match(
        staffShowPageFile,
        /Witness 2 recorded automatically at approval if left blank/,
    );
    assert.match(
        staffShowPageFile,
        /renderProcessingField\('witness_two_name',\s*\{\s*\n\s*disabled:\s*true,\s*\n\s*placeholder:\s*\n?\s*'Filled automatically upon approval',\s*\n\s*tooltip:\s*\n?\s*"Recorded automatically using the approving manager's name when the request is approved\.",\s*\n\s*\}\)/,
    );
    assert.match(
        staffShowPageFile,
        /options\?\.tooltip &&[\s\S]{0,300}<TooltipTrigger>[\s\S]{0,100}<Info /,
    );
    assert.match(
        staffShowPageFile,
        /showWitnessTwoCaveat\s*=\s*\n?\s*document\.key\s*===\s*\n?\s*'loan_information'\s*\|\|\s*\n?\s*document\.key\s*===\s*\n?\s*'promissory_note'/,
    );
});
