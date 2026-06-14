import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('sidebar exposes the superadmin staff management entry', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'components', 'app-sidebar.tsx'),
        'utf8',
    );

    assert.match(file, /Staff management/);
    assert.match(file, /superadminStaffIndex/);
    assert.match(file, /auth\.isAdmin && auth\.hasActiveStaffAccess/);
});

test('superadmin staff page requires a reason for every mutation', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'superadmin', 'staff.tsx'),
        'utf8',
    );

    assert.match(file, /Reason is required for every staff mutation\./);
    assert.match(file, /Document why this role change is necessary\./);
    assert.match(file, /Document why this access change is necessary\./);
});

test('legacy admin and member roles are not editable in the new staff page', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'superadmin', 'staff.tsx'),
        'utf8',
    );

    assert.doesNotMatch(file, /value:\s*'admin'/);
    assert.doesNotMatch(file, /value:\s*'member'/);
    assert.match(
        file,
        /Member is[\s\S]*visible in the directory when present/,
    );
    assert.match(file, /never assigned or removed from this page\./);
});

test('staff page renders API and history load errors', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'pages', 'superadmin', 'staff.tsx'),
        'utf8',
    );

    assert.match(file, /Unable to load staff accounts/);
    assert.match(file, /Unable to load staff history/);
    assert.match(file, /showErrorToast/);
});
