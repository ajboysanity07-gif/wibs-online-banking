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
        /Optional — recorded automatically using the approving manager's\s*\n\s*name if left blank when the request is approved\./,
    );
    assert.match(
        staffShowPageFile,
        /Witness 2 recorded automatically at approval if left blank/,
    );
    assert.match(
        staffShowPageFile,
        /showWitnessTwoCaveat\s*=\s*\n?\s*document\.key\s*===\s*\n?\s*'loan_information'\s*\|\|\s*\n?\s*document\.key\s*===\s*\n?\s*'promissory_note'/,
    );
});
