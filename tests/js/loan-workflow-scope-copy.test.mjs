import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('welcome page no longer claims the portal tracks loan release', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'welcome.tsx'),
        'utf8',
    );

    assert.doesNotMatch(file, /submission to release/);
    // Tracking copy stops at approval; release is handled in WIBS Desktop.
    assert.match(file, /submission through review, recommendation, and approval/);
});

test('organization settings approved sms default ends at WIBS processing', async () => {
    const file = await readFile(
        resolve(
            'resources',
            'js',
            'pages',
            'admin',
            'organization-settings.tsx',
        ),
        'utf8',
    );

    assert.doesNotMatch(file, /finalize your loan/);
    assert.match(file, /awaiting processing in WIBS/);
});
