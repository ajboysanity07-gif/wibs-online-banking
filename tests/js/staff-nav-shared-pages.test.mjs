import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const read = (...segments) =>
    readFile(resolve('resources', 'js', ...segments), 'utf8');

test('staff sidebar builds one entry per destination, no legacy admin list', async () => {
    const file = await read('components', 'app-sidebar.tsx');

    assert.doesNotMatch(file, /legacyAdminNavItems|staffWorkflowNavItems/);
    assert.equal((file.match(/staffNavItems\(auth\)/g) ?? []).length, 1);
});

test('admin and staff members pages share the members directory component', async () => {
    for (const page of [
        ['pages', 'admin', 'watchlist.tsx'],
        ['pages', 'staff', 'members.tsx'],
    ]) {
        const file = await read(...page);

        assert.match(file, /<MembersDirectoryPage/);
        assert.doesNotMatch(file, /<DataTable/);
    }
});

test('admin and staff reported requests pages share one component', async () => {
    for (const page of [
        ['pages', 'admin', 'reported-requests.tsx'],
        ['pages', 'staff', 'reported-requests.tsx'],
    ]) {
        const file = await read(...page);

        assert.match(file, /<ReportedRequestsPage/);
        assert.doesNotMatch(file, /<DataTable|<LoanRequestQueuePage/);
    }
});
