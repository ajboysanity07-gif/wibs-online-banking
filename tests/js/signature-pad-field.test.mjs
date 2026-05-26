import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('signature pad field uses the safe canvas fallback on draw end', async () => {
    const source = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'signature-pad-field.tsx',
        ),
        'utf8',
    );

    assert.doesNotMatch(source, /getTrimmedCanvas\s*\(/);
    assert.match(source, /getCanvas\s*\(\)/);
    assert.match(source, /toDataURL\('image\/png'\)/);
});
