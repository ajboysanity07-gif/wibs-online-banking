import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('branding theme mapping uses organization colors', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'theme', 'branding-theme.ts'),
        'utf8',
    );

    assert.match(file, /resolveBrandingTheme/);
    assert.match(file, /brandPrimaryColor/);
    assert.match(file, /brandAccentColor/);
});

test('app applies branding theme on navigation', async () => {
    const file = await readFile(resolve('resources', 'js', 'app.tsx'), 'utf8');

    assert.match(file, /injectClientTheme/);
    assert.match(file, /resolveBrandingTheme/);
});
