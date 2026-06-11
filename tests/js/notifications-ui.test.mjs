import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('app sidebar header shows notifications for admins and members', async () => {
    const file = await readFile(
        resolve('resources', 'js', 'components', 'app-sidebar-header.tsx'),
        'utf8',
    );

    assert.match(file, /auth\.isAdmin \|\| auth\.hasMemberAccess/);
    assert.doesNotMatch(file, /\{auth\.hasMemberAccess \? \(/);
});

test('notification bell renders generic notification metadata', async () => {
    const bellFile = await readFile(
        resolve('resources', 'js', 'components', 'notification-bell.tsx'),
        'utf8',
    );
    const helperFile = await readFile(
        resolve('resources', 'js', 'lib', 'notification-ui.ts'),
        'utf8',
    );

    assert.match(bellFile, /buildNotificationMetadataChips/);
    assert.match(helperFile, /payload\.changed_fields/);
    assert.match(helperFile, /payload\.actor_name/);
    assert.match(helperFile, /Pending Review/);
    assert.match(helperFile, /Under Review/);
});
