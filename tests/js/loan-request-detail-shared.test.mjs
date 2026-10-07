import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const read = (...parts) =>
    readFile(resolve('resources', 'js', ...parts), 'utf8');

test('member, staff and admin loan request pages share one detail view', async () => {
    for (const role of ['client', 'staff', 'admin']) {
        const page = await read('pages', role, 'loan-request-show.tsx');

        assert.match(page, /<LoanRequestDetailView/, role);
        assert.doesNotMatch(page, /LoanRequestRecordHeader/, role);
    }
});

test('detail view member-only controls are optional for staff', async () => {
    const view = await read(
        'components',
        'loan-request',
        'loan-request-detail.tsx',
    );

    assert.match(view, /cancellation\?: \{/);
    assert.match(view, /canChangePaymentMethod\?: boolean/);
    assert.match(view, /headerActions\?: ReactNode/);
});
