import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const requestsPagePath = resolve(
    'resources',
    'js',
    'pages',
    'admin',
    'requests.tsx',
);

test('admin requests table includes action column and view link', async () => {
    const file = await readFile(requestsPagePath, 'utf8');

    assert.match(file, /Action/);
    assert.match(file, /View request/);
});

test('admin requests member column is plain text', async () => {
    const file = await readFile(requestsPagePath, 'utf8');
    const memberBlockMatch = file.match(
        /accessorKey:\s*'member_name'[\s\S]*?accessorKey:\s*'loan_type'/,
    );

    assert.ok(memberBlockMatch);
    assert.match(memberBlockMatch[0], /row\.original\.member_name \?\? '--'/);
    assert.ok(!memberBlockMatch[0].includes('<Link'));
});
