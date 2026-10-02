import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const pageFile = await readFile(
    resolve('resources', 'js', 'pages', 'client', 'loan-request.tsx'),
    'utf8',
);

test('section status lists why a section is not done yet', () => {
    for (const message of [
        'Confirmation that your details are correct',
        'Confirmation of your work & income details',
        'Confirmation of your bank details',
        'Truthfulness declaration',
        'Data privacy consent',
    ]) {
        assert.ok(pageFile.includes(message), `missing blocker: ${message}`);
    }

    assert.match(pageFile, /complete: blockers\[section\]\.length === 0/);
});

test('submit stays disabled until every section is done', () => {
    assert.match(
        pageFile,
        /const allDone = nextIncompleteSection\(statuses\) === null/,
    );
    assert.match(pageFile, /!allDone \|\|\s*isSubmitting/);
});

test('confirmation ticks are saved with the draft', () => {
    assert.match(pageFile, /wizard_confirmations: \[/);
});

test('returning applicants land on the overview, first-time on the calculator', () => {
    assert.match(pageFile, /useState<View>\(draft \? 'hub' : 'calc'\)/);
});

test('dependents step is not part of the member wizard', async () => {
    const stepsFile = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-wizard-steps.ts',
        ),
        'utf8',
    );

    assert.doesNotMatch(stepsFile, /id: 'dependents'/);
    assert.doesNotMatch(pageFile, /LoanRequestDependentsStep/);
});

test('editing a field clears its stale validation error', () => {
    assert.match(
        pageFile,
        /form\.setData\(field, value\);\s*form\.clearErrors\(field\);/,
    );
    assert.match(
        pageFile,
        /form\.clearErrors\(\s*`\$\{sectionKey\}\.\$\{field\}`/,
    );
    assert.match(pageFile, /form\.clearErrors\('undertaking_accepted'\)/);
    assert.match(
        pageFile,
        /onTypecodeChange=\{\(value\) =>\s*handleLoanDetailChange\('typecode', value\)/,
    );
});
