import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => {
    return readFile(resolve(...segments), 'utf8');
};

test('workflow health card renders as a summary bar plus 4-column plain grid, not the old bordered tiles', async () => {
    const pageFile = await readSource([
        'resources',
        'js',
        'pages',
        'staff',
        'loan-request-show.tsx',
    ]);

    const cardStart = pageFile.indexOf('Workflow health');
    const cardBlock = pageFile.slice(
        cardStart,
        pageFile.indexOf('Notification history', cardStart),
    );

    assert.ok(
        !cardBlock.includes(
            'rounded-xl border border-border/40 bg-muted/10',
        ),
    );
    assert.ok(cardBlock.includes('grid-cols-2 sm:grid-cols-4'));

    assert.ok(cardBlock.includes('All clear — no issues detected'));
    assert.ok(cardBlock.includes('issue${workflowHealthIssueCount === 1'));

    assert.ok(pageFile.includes('PROCESSING_AGE_ISSUE_THRESHOLD_DAYS'));
});
