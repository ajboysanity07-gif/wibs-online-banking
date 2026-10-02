import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const read = (...parts) =>
    readFile(resolve('resources', 'js', ...parts), 'utf8');

// About you is read-only (it mirrors the member profile), so the review step
// no longer offers an "update my profile" opt-out: submission always syncs
// the wizard's editable answers (disbursement, beneficiaries, health) back to
// the profile, the backend default when update_profile is absent.
test('review step has no update-profile checkbox and submit sends no opt-out', async () => {
    const steps = await read(
        'components',
        'loan-request',
        'loan-request-steps.tsx',
    );
    const page = await read('pages', 'client', 'loan-request.tsx');

    assert.doesNotMatch(steps, /id="update_profile"/);
    assert.doesNotMatch(steps, /Also update my profile/);
    assert.doesNotMatch(page, /update_profile/);
});
