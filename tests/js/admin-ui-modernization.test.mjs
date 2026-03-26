import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('admin dashboard uses shared hero and shell primitives', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'admin', 'dashboard.tsx'),
        'utf8',
    );

    assert.match(file, /<PageShell/);
    assert.match(file, /<PageHero/);
});

test('admin requests uses shared surface layout primitives', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'admin', 'requests.tsx'),
        'utf8',
    );

    assert.match(file, /<PageHero/);
    assert.match(file, /<SurfaceCard/);
    assert.match(file, /<SectionHeader/);
});

test('loan request detail page uses the shared shell', async () => {
    const file = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-detail-page.tsx',
        ),
        'utf8',
    );

    assert.match(file, /<PageShell/);
});

test('admin members list uses the shared layout primitives', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'admin', 'watchlist.tsx'),
        'utf8',
    );

    assert.match(file, /<PageHero/);
    assert.match(file, /<SurfaceCard/);
    assert.match(file, /<SectionHeader/);
});

test('admin pending approvals uses the shared layout primitives', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'admin', 'pending-users.tsx'),
        'utf8',
    );

    assert.match(file, /<PageHero/);
    assert.match(file, /<SurfaceCard/);
    assert.match(file, /<SectionHeader/);
});

test('organization settings uses the shared page hero', async () => {
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

    assert.match(file, /<PageHero/);
});

test('settings layout uses the shared shell and hero', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'layouts', 'settings', 'layout.tsx'),
        'utf8',
    );

    assert.match(file, /<PageShell/);
    assert.match(file, /<PageHero/);
});

test('auth layout uses the shared surface card', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'layouts', 'auth', 'auth-simple-layout.tsx'),
        'utf8',
    );

    assert.match(file, /<SurfaceCard/);
});
