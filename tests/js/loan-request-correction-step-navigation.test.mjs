import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('admin correction dialog steps are clickable for forward and backward navigation', async () => {
    const dialogFile = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'admin-loan-request-correction-dialog.tsx',
        ),
        'utf8',
    );

    const wizardShellMatch = dialogFile.match(
        /<LoanRequestWizardShell\b([\s\S]*?)>([\s\S]*?)<\/LoanRequestWizardShell>/,
    );

    assert.ok(
        wizardShellMatch,
        'expected to find the LoanRequestWizardShell usage',
    );

    assert.doesNotMatch(
        wizardShellMatch[1],
        /index\s*<=\s*currentStep/,
        'sidebar step clicks must not be restricted to backward-only navigation',
    );

    assert.match(
        wizardShellMatch[1],
        /onStepClick\s*=\s*\{?\s*moveToStep\s*\}?/,
        'expected the wizard shell to delegate step clicks to moveToStep for any step',
    );
});
