import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const read = (...parts) =>
    readFile(resolve('resources', 'js', ...parts), 'utf8');

// The submitted payload is whatever named inputs are mounted in the form.
// These are the names each tab submitted before the inline Edit/Save/Cancel
// rows; wrapping a field in a row must never add or drop one.
const expectedNames = {
    'account-tab.tsx': [
        'email',
        'fullname',
        'phoneno',
        'profile_photo',
        'username',
    ],
    'personal-tab.tsx': [
        'birthplace_barangay',
        'birthplace_city',
        'birthplace_province',
        'civil_status',
        'educational_attainment',
        'height_cm',
        'home_address_barangay',
        'home_address_zip',
        'home_address1',
        'home_address2',
        'home_address3',
        'housing_status',
        'length_of_stay',
        'nickname',
        'number_of_children',
        'sex',
        'spouse_birthdate',
        'spouse_cell_no',
        'spouse_name',
        'weight_kg',
    ],
    'work-tab.tsx': [
        'current_position',
        'employer_business_address_barangay',
        'employer_business_address_zip',
        'employer_business_address1',
        'employer_business_address2',
        'employer_business_address3',
        'employer_business_name',
        'employer_date_employed',
        'employment_type',
        'gross_monthly_income',
        'id_number',
        'id_type',
        'id_type_other',
        'nature_of_business',
        'nature_of_business_other',
        'payday',
        'source_of_fund_wealth',
        'source_of_fund_wealth_other',
        'telephone_no',
        'years_in_work_business',
    ],
    'bank-tab.tsx': [
        'payment_option',
        'payment_saved_account_id',
        'release_method',
        'release_saved_account_id',
    ],
};

for (const [file, names] of Object.entries(expectedNames)) {
    test(`${file} still submits exactly the same named fields`, async () => {
        const source = await read('pages', 'settings', 'profile-tabs', file);
        const found = [
            ...new Set(
                [...source.matchAll(/\bname="([a-z_0-9]+)"/g)].map(
                    (match) => match[1],
                ),
            ),
        ].sort();

        assert.deepEqual(found, [...names].sort());
        assert.match(source, /<InlineEditRow/);
    });
}

test('inline rows keep every editor mounted so the payload is unchanged', async () => {
    const source = await read('components', 'settings', 'inline-edit-row.tsx');

    // Closed rows hide the editor; they never unmount it.
    assert.match(source, /hidden=\{!isOpen\}/);
    assert.doesNotMatch(source, /\{isOpen\s*&&\s*children/);
    assert.doesNotMatch(source, /\{editing\s*&&\s*children/);
    // Save goes through the existing form submit (validation, route, props).
    assert.match(source, /closest\('form'\)\s*\?\.requestSubmit\(\)/);
});

test('profile form wires rows into the existing save path', async () => {
    const profile = await read('pages', 'settings', 'profile.tsx');
    const shared = await read('pages', 'settings', 'profile-shared.tsx');

    assert.match(profile, /\{\.\.\.ProfileController\.update\.form\(\)\}/);
    assert.match(profile, /closeInlineRows\(\);/);
    // Onboarding keeps the always-open form and its footer Save.
    assert.match(profile, /const rowMode = !onboarding;/);
    assert.match(profile, /\{!rowMode && \(/);
    assert.match(profile, /data-test="update-profile-button"/);
    // Cancel re-mounts the form, restoring the last saved values.
    assert.match(profile, /key=\{resetKey\}/);
    // Server errors and "missing field" jumps open the row that owns the field.
    assert.match(shared, /openInlineRowFor\(element\)/);
});
