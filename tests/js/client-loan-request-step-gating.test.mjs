import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const pageFile = await readFile(
    resolve('resources', 'js', 'pages', 'client', 'loan-request.tsx'),
    'utf8',
);

test('client wizard sidebar clicks go through the gated handler', () => {
    assert.match(pageFile, /onStepClick=\{handleSidebarStepClick\}/);
    assert.doesNotMatch(pageFile, /onStepClick=\{handleStepChange\}/);
    assert.match(
        pageFile,
        /step > currentStep &&\s*\(currentStepBlockers\.length > 0 \|\| step > highestStepReached\)/,
    );
});

test('client wizard blocks Next and shows why for unchecked confirm checkboxes', () => {
    for (const message of [
        'Confirmation that your details are correct',
        'Confirmation of your work & income details',
        'Confirmation of your bank details',
    ]) {
        assert.ok(pageFile.includes(message), `missing blocker: ${message}`);
    }

    assert.match(pageFile, /currentStepBlockers\.length > 0 \?/);
    assert.match(
        pageFile,
        /if \(currentStepBlockers\.length > 0\) \{\s*return;/,
    );
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
    assert.doesNotMatch(pageFile, /STEP_INDEX\['dependents'\]/);
    assert.doesNotMatch(pageFile, /LoanRequestDependentsStep/);
});
