import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const readSource = (segments) => {
    return readFile(resolve(...segments), 'utf8');
};

test('loan request entry flow shows physical signature guidance instead of signature pads', async () => {
    const requestPageFile = await readSource([
        'resources',
        'js',
        'pages',
        'client',
        'loan-request.tsx',
    ]);
    const stepsFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-steps.tsx',
    ]);

    assert.match(
        requestPageFile,
        /Signatures will be collected physically upon loan release\./,
    );
    assert.match(
        stepsFile,
        /Signatures will be collected physically upon loan release\./,
    );
    assert.ok(!requestPageFile.includes('SignaturePadField'));
    assert.ok(!stepsFile.includes('SignaturePadField'));
    assert.ok(!stepsFile.includes('signature_data'));
});

test('loan request admin and detail pages do not render digital signing workflow controls', async () => {
    const detailPageFile = await readSource([
        'resources',
        'js',
        'components',
        'loan-request',
        'loan-request-detail-page.tsx',
    ]);
    const adminShowPageFile = await readSource([
        'resources',
        'js',
        'pages',
        'admin',
        'loan-request-show.tsx',
    ]);
    const profilePageFile = await readSource([
        'resources',
        'js',
        'pages',
        'settings',
        'profile.tsx',
    ]);

    for (const disallowedCopy of [
        'Approve and Sign',
        'Generate signature link',
        'Copy signature link',
        'Regenerate signature link',
        'Signature status',
    ]) {
        assert.ok(!detailPageFile.includes(disallowedCopy));
        assert.ok(!adminShowPageFile.includes(disallowedCopy));
    }

    assert.ok(!profilePageFile.includes('loan-manager-signature'));
    assert.match(detailPageFile, /Approve Request/);
});
