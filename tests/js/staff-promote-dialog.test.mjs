import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

test('promote member dialog uses the shared data table with pagination', async () => {
    const source = await readFile(
        'resources/js/pages/superadmin/staff.tsx',
        'utf8',
    );
    const dialog = source.slice(
        source.indexOf('Promote existing member</DialogTitle>'),
    );

    assert.match(dialog, /<DataTable[\s\S]*promoteColumns/);
    assert.match(dialog, /<RequestsPager/);
    assert.doesNotMatch(dialog, /<TableRow/);
});
