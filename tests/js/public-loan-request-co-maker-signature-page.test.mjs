import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('public co-maker signing page emphasizes identity and guards submission', async () => {
    const source = await readFile(
        resolve(
            'resources',
            'js',
            'pages',
            'public',
            'loan-request-co-maker-signature.tsx',
        ),
        'utf8',
    );

    assert.match(source, /You are signing as/);
    assert.match(source, /Only continue if this is you\./);
    assert.match(source, /This secure signing link expires on/);
    assert.match(source, /I confirm that I am the person/);
    assert.match(source, /information shown is correct/);
    assert.match(source, /I voluntarily agree to/);
    assert.match(source, /act as co-maker for this loan/);
    assert.match(source, /Only the co-maker named above should/);
    assert.match(source, /Do not sign on behalf of/);
    assert.match(source, /Report incorrect information/);
    assert.match(source, /Please contact the borrower or cooperative/);
    assert.match(source, /office before signing\./);
    assert.match(source, /This request will/);
    assert.match(source, /remain unsigned until/);
    assert.match(source, /corrected\./);
    assert.match(
        source,
        /const canSubmitSignature =\s*form\.data\.consent && form\.data\.signature_data\.trim\(\) !== '';/s,
    );
    assert.match(
        source,
        /disabled=\{\s*!canSubmitSignature\s*\|\|\s*form\.processing\s*\}/s,
    );
    assert.match(source, /setShowIncorrectInfoNotice\(true\)/);
    assert.match(source, /Signature submitted successfully/);
    assert.match(
        source,
        /You are now confirmed as a co-maker for/,
    );
    assert.match(source, /You may close this page\./);
});
