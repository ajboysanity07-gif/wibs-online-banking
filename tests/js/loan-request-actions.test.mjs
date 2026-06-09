import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('loan request actions group document buttons and separate navigation', async () => {
    const detailFile = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-detail-page.tsx',
        ),
        'utf8',
    );
    const adminPageFile = await readFile(
        resolve('resources', 'js', 'pages', 'admin', 'loan-request-show.tsx'),
        'utf8',
    );
    const clientPageFile = await readFile(
        resolve('resources', 'js', 'pages', 'client', 'loan-request-show.tsx'),
        'utf8',
    );

    assert.match(detailFile, /Actions/);
    assert.match(detailFile, /Download PDF/);
    assert.match(detailFile, /Plan of Payment Excel/);
    assert.match(detailFile, /Print application/);
    assert.match(detailFile, /Cancel Application/);
    assert.match(detailFile, /backLabel/);
    assert.match(detailFile, /sm:grid-cols-2/);
    assert.match(detailFile, /variant="ghost"/);
    assert.match(detailFile, /loanRequest\.status === 'pending_review'/);
    assert.match(adminPageFile, /Cancel Approved Request/);
    assert.match(adminPageFile, /Cancel Application/);
    assert.match(
        clientPageFile,
        /\['submitted', 'pending_review', 'under_review'\]\.includes/,
    );
    assert.match(clientPageFile, /Reason \(optional\)/);
    assert.match(clientPageFile, /Confirm Cancellation/);
});
