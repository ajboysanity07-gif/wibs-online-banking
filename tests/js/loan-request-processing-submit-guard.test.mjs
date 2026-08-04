import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('processing details submit no longer requires remarks/reason before saving', async () => {
    const file = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'processing-details-panel.tsx',
        ),
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

    assert.doesNotMatch(
        submitFnBody,
        /processingForm\.reason\.trim\(\) === ''/,
        'remarks are optional now — the empty-reason guard must be gone',
    );

    // The reason field must still be forwarded as-is (the backend fills in
    // an auto-generated summary when it's blank).
    assert.match(
        submitFnBody,
        /reason:\s*processingForm\.reason/,
        'reason must still be submitted to updateProcessingDetails',
    );
});
