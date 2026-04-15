import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('error page uses shared surface and support primitives', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'errors', 'error.tsx'),
        'utf8',
    );

    assert.match(file, /<SurfaceCard/);
    assert.match(file, /<Alert/);
    assert.match(file, /<SupportContact/);
    assert.match(file, /<AppLogo/);
});

test('error page keeps safe auth and brand fallbacks in the component', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'errors', 'error.tsx'),
        'utf8',
    );

    assert.match(file, /auth\?: Partial<Auth> \| null/);
    assert.match(file, /branding\?: Branding \| null/);
    assert.match(file, /const brandLabel =/);
    assert.match(file, /resolvePrimaryCta\(auth\)/);
});
