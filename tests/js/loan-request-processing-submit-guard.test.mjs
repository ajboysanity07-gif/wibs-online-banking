import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('processing details submit guards against empty reason/information_source before saving', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'staff', 'loan-request-show.tsx'),
        'utf8',
    );

    const submitFnMatch = file.match(
        /const submitProcessingDetails = async \(([\s\S]*?)\n {4}\};/,
    );

    assert.ok(
        submitFnMatch,
        'expected to find the submitProcessingDetails function',
    );

    const submitFnBody = submitFnMatch[0];

    assert.match(
        submitFnBody,
        /processingForm\.reason\.trim\(\) === ''/,
        'guard must check reason for empty value',
    );
    assert.match(
        submitFnBody,
        /processingForm\.information_source\.trim\(\) === ''/,
        'guard must check information_source for empty value',
    );
    assert.match(
        submitFnBody,
        /setActiveProcessingSection\('confirmation'\)/,
        'guard must switch the nested nav to the Confirmation section',
    );
    assert.match(
        submitFnBody,
        /showErrorToast\(/,
        'guard must surface a message rather than relying on native validation',
    );

    // The guard's early return must come before updateProcessingDetails is
    // called, otherwise an empty submit would still reach the API.
    const guardIndex = submitFnBody.indexOf("processingForm.reason.trim()");
    const apiCallIndex = submitFnBody.indexOf('updateProcessingDetails(');

    assert.ok(guardIndex !== -1 && apiCallIndex !== -1);
    assert.ok(
        guardIndex < apiCallIndex,
        'guard check must run before updateProcessingDetails is called',
    );
});
