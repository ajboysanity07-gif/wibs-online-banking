import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => readFile(resolve(...segments), 'utf8');

test('workspace chooser surfaces member and staff cards from workspace availability', async () => {
    const file = await readSource([
        'resources',
        'js',
        'pages',
        'dashboard.tsx',
    ]);

    assert.match(file, /auth\.availableWorkspaces\.includes\('member'\)/);
    assert.match(file, /auth\.availableWorkspaces\.includes\('staff'\)/);
    assert.doesNotMatch(file, /auth\.isAdmin && auth\.hasActiveStaffAccess/);
    assert.match(
        file,
        /Manage your personal account, loan[\s\S]*documents, and status\./,
    );
    assert.match(
        file,
        /Review and manage loan applications\s+according to your assigned staff roles\./,
    );
});

test('user menu only shows the workspace switcher for multi workspace users', async () => {
    const file = await readSource([
        'resources',
        'js',
        'components',
        'user-menu-content.tsx',
    ]);

    assert.match(file, /auth\.hasMultipleWorkspaces/);
    assert.match(file, /Switch to Member Portal/);
    assert.match(file, /Switch to Staff Workspace/);
    assert.match(file, /View all workspaces/);
    assert.match(file, /switchWorkspace\.url/);
});

test('sidebar renders one workspace navigation context at a time', async () => {
    const file = await readSource([
        'resources',
        'js',
        'components',
        'app-sidebar.tsx',
    ]);

    assert.match(file, /activeWorkspace === 'member'/);
    assert.match(file, /activeWorkspace === 'staff'/);
    assert.match(file, /label="Member Portal"/);
    assert.match(file, /label="Staff Workspace"/);
    assert.match(file, /auth\.availableWorkspaces\.includes\('staff'\)/);
    assert.match(file, /auth\.availableWorkspaces\.includes\('member'\)/);
});
