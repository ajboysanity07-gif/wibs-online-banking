import assert from 'node:assert/strict';
import test from 'node:test';
import {
    isWithinSectionPath,
    normalizePath,
} from '../../resources/js/lib/url-match.js';

test('normalizePath strips trailing slashes and keeps root', () => {
    assert.equal(normalizePath('/client/loans/'), '/client/loans');
    assert.equal(normalizePath('client/loans'), '/client/loans');
    assert.equal(normalizePath('/'), '/');
});

test('isWithinSectionPath matches exact and child paths', () => {
    assert.equal(
        isWithinSectionPath('/client/loans', '/client/loans'),
        true,
    );
    assert.equal(
        isWithinSectionPath('/client/loans', '/client/loans/request'),
        true,
    );
    assert.equal(
        isWithinSectionPath('/client/loans', '/client/loans/requests/123'),
        true,
    );
});

test('isWithinSectionPath avoids partial segment matches', () => {
    assert.equal(
        isWithinSectionPath('/client/loans', '/client/loan'),
        false,
    );
    assert.equal(
        isWithinSectionPath('/client/loans', '/client/loans-archive'),
        false,
    );
});
