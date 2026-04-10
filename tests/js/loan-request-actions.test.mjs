import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('loan request actions group document buttons and separate navigation', async () => {
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

    assert.match(file, /Actions/);
    assert.match(file, /Download PDF/);
    assert.match(file, /Print application/);
    assert.match(file, /backLabel/);
    assert.match(file, /sm:grid-cols-2/);
    assert.match(file, /variant="ghost"/);
});
