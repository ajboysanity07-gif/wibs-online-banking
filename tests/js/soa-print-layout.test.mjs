import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(
    new URL(
        '../../resources/js/components/loans/soa-dialog.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('printed statement uses the branded layout with an injected logo band', () => {
    assert.match(source, /class="brand" data-org-logo/);
    assert.match(source, /aspect-ratio:1137\/309/);
    assert.match(source, /Balance brought forward/);
    assert.match(source, /<h1>Statement of Account<\/h1>/);
    assert.match(source, /@page\{size:A4 portrait/);
    assert.match(source, /branding\.assets\.logoFullUrl/);
    assert.doesNotMatch(source, /class="sign"|SignatureBox/);
    assert.ok(source.includes('<th>OR No.</th>'));
    assert.ok(source.includes('<th>Particulars</th>'));
    assert.ok(source.includes('Total amount due'));
    assert.ok(source.includes('system-generated statement'));
    assert.ok(source.includes('TIN: ${orBlank(brand.tin)}'));
    assert.ok(source.includes('branding.general.businessTin'));
});
