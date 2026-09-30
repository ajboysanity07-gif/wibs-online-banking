import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const read = (...parts) =>
    readFile(resolve('resources', 'js', ...parts), 'utf8');

test('member dashboard masks the account number and offers a balances toggle', async () => {
    const page = await read('pages', 'client', 'dashboard.tsx');

    assert.match(page, /maskAccountNumber\(currentMember\.acctno\)/);
    assert.doesNotMatch(page, /Account No: \{currentMember\.acctno/);
    assert.match(page, /useHideBalances\(\)/);
    assert.match(page, /hideBalances=\{balancesHidden\}/);
    assert.match(page, /<MemberRecentTransactions/);
    assert.match(page, /never asks for your password/);
});

test('account numbers are masked to the last four characters', async () => {
    const source = await read('lib', 'formatters.ts');

    assert.match(source, /`••••\$\{trimmed\.slice\(-4\)\}`/);
});

test('hide balances preference tolerates blocked storage', async () => {
    const source = await read('hooks', 'use-hide-balances.ts');

    assert.equal((source.match(/try \{/g) ?? []).length, 2);
    assert.match(source, /catch \{/);
});

test('recent transactions take direction from the ledger columns', async () => {
    const source = await read(
        'features',
        'member-accounts',
        'components',
        'member-recent-transactions.tsx',
    );

    assert.match(source, /\['principal', action\.principal, true\]/);
    assert.match(source, /\['payments', action\.payments, false\]/);
    assert.match(source, /\['withdrawal', action\.withdrawal, false\]/);
});
