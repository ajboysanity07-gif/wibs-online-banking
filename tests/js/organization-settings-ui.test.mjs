import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const page = await readFile(
    new URL(
        '../../resources/js/pages/admin/organization-settings.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('organization settings uses the plain hero, settings rail, savebar and danger zone', () => {
    assert.doesNotMatch(page, /<PageHero/);
    assert.doesNotMatch(page, /<TabsTrigger/);
    assert.match(page, /Find a setting/);
    assert.match(page, /Discard changes/);
    assert.match(page, /Danger zone/);
    assert.match(page, /OrganizationPaletteFields/);
});

test('status badges read colors from CSS variables, not hard-coded hex', async () => {
    for (const file of [
        'resources/js/pages/superadmin/staff.tsx',
        'resources/js/components/loan-request/loan-request-queue-page.tsx',
    ]) {
        const source = await readFile(
            new URL(`../../${file}`, import.meta.url),
            'utf8',
        );
        assert.doesNotMatch(source, /bg-\[#[0-9a-f]{6}\]/i, file);
    }
});

test('organization settings exposes the new mockup fields and danger rows', () => {
    for (const text of [
        'short_name',
        'timezone',
        'statement_currency',
        'report_footer',
        'service_hours',
        'sms_send_window',
        'loan_sms_approved_enabled',
        'Clear all SMS templates',
        'CLEAR',
        'Last saved',
    ]) {
        assert.ok(page.includes(text), text);
    }
});
