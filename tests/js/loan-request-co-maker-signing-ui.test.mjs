import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('loan request co-maker signing UI exposes in-person and remote signing flows', async () => {
    const stepsFile = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-steps.tsx',
        ),
        'utf8',
    );
    const pageFile = await readFile(
        resolve('resources', 'js', 'pages', 'client', 'loan-request.tsx'),
        'utf8',
    );

    assert.match(stepsFile, /Sign now on this device/);
    assert.match(stepsFile, /Share secure signing link/);
    assert.match(stepsFile, /Choose how this co-maker will sign\./);
    assert.match(stepsFile, /Only the co-maker should sign\./);
    assert.match(stepsFile, /sign on behalf of another person\./i);
    assert.match(stepsFile, /Secure signing link/);
    assert.match(stepsFile, /Copy link/);
    assert.match(stepsFile, /Regenerate link/);
    assert.match(stepsFile, /Signed co-maker details are locked/);
    assert.match(stepsFile, /Edit details and require a new signature/);
    assert.match(stepsFile, /Co-maker signatures are optional online\./);
    assert.match(
        stepsFile,
        /be required to sign the printed application form during/,
    );
    assert.match(stepsFile, /loan release\./);
    assert.match(
        stepsFile,
        /Member \/ Applicant Signature \(Required\)/,
    );
    assert.match(pageFile, /co_maker_1_signature_data/);
    assert.match(pageFile, /co_maker_2_signature_data/);
    assert.match(pageFile, /navigator\.clipboard\.writeText/);
    assert.match(
        pageFile,
        /Reminder: One or more co-maker signatures are missing\. The co-makers must sign the printed application form during loan release\./,
    );
    assert.match(
        pageFile,
        /Please review the highlighted fields before generating the signing link\./,
    );
    assert.match(pageFile, /LoanRequestWizardActions/);
});
