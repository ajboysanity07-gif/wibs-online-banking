import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('loan request status timeline uses a full primary line and distinct current marker', async () => {
    const file = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-detail-page.tsx',
        ),
        'utf8',
    );

    assert.match(file, /bg-primary\/50/);
    assert.match(file, /ring-4 ring-primary\/20/);
    assert.match(file, /h-3\.5 w-3\.5/);
    assert.match(file, /h-2\.5 w-2\.5/);
    assert.match(file, /h-2 w-2/);
});
