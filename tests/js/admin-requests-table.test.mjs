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

    assert.match(file, /LoanRequestQueuePage/);
    assert.match(file, /showRequestHref/);
    assert.match(file, /requestsShow/);
});

test('admin requests member column is plain text', async () => {
    const file = await readFile(requestsPagePath, 'utf8');

    assert.match(file, /LoanRequestQueuePage/);
    assert.match(file, /showRequestHref/);
    assert.ok(!file.includes("accessorKey: 'member_name'"));
});
