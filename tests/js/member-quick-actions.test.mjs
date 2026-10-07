import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

test('member quick actions no longer offer a payment shortcut', async () => {
    const source = await readFile(
        'resources/js/components/member/member-quick-actions.tsx',
        'utf8',
    );

    assert.doesNotMatch(source, /Make a payment/);
    assert.match(source, /Statement of account/);
});
