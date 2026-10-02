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

const validationFile = await readFile(
    resolve('resources', 'js', 'lib', 'loan-request-step-validation.ts'),
    'utf8',
);

test('loan details gates kind of loan and the other-loan name by loan type', () => {
    assert.match(
        validationFile,
        /context\.requiresKindOfLoan && blank\(data\.kind_of_loan\)/,
    );
    assert.match(
        validationFile,
        /context\.requiresOtherLoanTypeName &&\s*blank\(data\.other_loan_type_name\)/,
    );
    assert.match(
        pageFile,
        /requiresKindOfLoan: isMicroBusinessLoanLabel\(selectedLoanTypeLabel\)/,
    );
    assert.match(
        pageFile,
        /requiresOtherLoanTypeName: form\.data\.typecode === OTHER_LOAN_TYPECODE/,
    );
});

test('pensioners still need income and payday, which the server requires', () => {
    assert.match(
        validationFile,
        /isPensionerType\(values\.employment_type\)\s*\?\s*incomeChecks/,
    );
    assert.match(validationFile, /Number\(values\.gross_monthly_income\) > 0/);
});

test('saving or removing a co-maker clears its stale server errors', () => {
    assert.match(pageFile, /key\.startsWith\(`\$\{slot\}\.`\)/);
    assert.match(
        pageFile,
        /if \(keys\.length > 0\) \{\s*form\.clearErrors\(\.\.\.keys\);/,
    );
    assert.match(
        pageFile,
        /form\.setData\(slot, person\);\s*clearCoMakerErrors\(slot\);/,
    );
    assert.match(
        pageFile,
        /form\.setData\(slot, toPersonForm\(null\)\);\s*clearCoMakerErrors\(slot\);/,
    );
});
