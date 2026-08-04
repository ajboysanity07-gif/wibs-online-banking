import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('admin correction dialog does not require employer_date_employed for existing requests', async () => {
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

    const requiredFieldsMatch = dialogFile.match(
        /const applicantRequiredFields: Array<keyof LoanRequestPersonFormData> = \[([\s\S]*?)\];/,
    );

    assert.ok(
        requiredFieldsMatch,
        'expected to find the applicantRequiredFields declaration',
    );
    assert.doesNotMatch(
        requiredFieldsMatch[1],
        /'employer_date_employed'/,
        'employer_date_employed must not be required in the correction dialog — many existing requests predate this field and processors cannot know the value',
    );
    assert.match(
        dialogFile,
        /Date employed is optional/,
        'expected a hint explaining the field is optional when unknown',
    );
});
