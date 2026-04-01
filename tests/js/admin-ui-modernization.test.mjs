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

test('admin navigation removes member reviews entry', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'components', 'app-sidebar.tsx'),
        'utf8',
    );

    assert.doesNotMatch(file, /Member reviews/);
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
    assert.match(file, /<SurfaceCard/);
    assert.match(file, /<SectionHeader/);
});

test('member profile links loan actions to the payments page', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'admin', 'member-profile.tsx'),
        'utf8',
    );

    assert.match(file, /loanPayments/);
    assert.match(file, /action\.source !== 'LOAN'/);
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

test('client dashboard profile summary uses the premium layout', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'client', 'dashboard.tsx'),
        'utf8',
    );

    assert.match(file, /statusBadge/);
    assert.match(file, /Profile summary/);
});

test('member records card uses shared surface and section header', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'components', 'member-records-card.tsx'),
        'utf8',
    );

    assert.match(file, /<SurfaceCard/);
    assert.match(file, /<SectionHeader/);
});

test('settings pages wrap content in the shared surface card', async () => {
    const pages = [
        resolve('resources', 'js', 'pages', 'settings', 'appearance.tsx'),
        resolve('resources', 'js', 'pages', 'settings', 'password.tsx'),
        resolve('resources', 'js', 'pages', 'settings', 'profile.tsx'),
        resolve('resources', 'js', 'pages', 'settings', 'two-factor.tsx'),
    ];

    await Promise.all(
        pages.map(async (page) => {
            const file = await readFile(page, 'utf8');
            assert.match(file, /<SurfaceCard/);
        }),
    );
});

test('data table uses the modern rounded shell', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'components', 'ui', 'data-table.tsx'),
        'utf8',
    );

    assert.match(file, /rounded-2xl/);
    assert.match(file, /bg-card\/60/);
});
