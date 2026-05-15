import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('admin corrected request page includes the correction warning banner and approval guard copy', async () => {
    const pageFile = await readFile(
        resolve('resources', 'js', 'pages', 'admin', 'loan-request-show.tsx'),
        'utf8',
    );
    const detailFile = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-detail-page.tsx',
        ),
        'utf8',
    );

    assert.match(pageFile, /Correction required before approval/);
    assert.match(pageFile, /This request was created from a cancelled/);
    assert.match(
        pageFile,
        /Review and save the corrected[\s\S]*details before approving\./,
    );
    assert.match(pageFile, /Continue correction/);
    assert.match(
        detailFile,
        /Please save the correction before approving this admin-corrected request\./,
    );
});
