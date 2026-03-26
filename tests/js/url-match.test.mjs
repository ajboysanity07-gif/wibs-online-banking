import assert from 'node:assert/strict';
import test from 'node:test';
import {
    isWithinSectionPath,
    matchesExactPaths,
    matchesSectionPaths,
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
    assert.equal(
        isWithinSectionPath('/admin/requests', '/admin/requests/45/print'),
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

test('matchesExactPaths only matches exact routes', () => {
    assert.equal(
        matchesExactPaths(
            ['/admin/requests', '/admin/watchlist'],
            '/admin/requests',
        ),
        true,
    );
    assert.equal(
        matchesExactPaths(
            ['/admin/requests', '/admin/watchlist'],
            '/admin/requests/42',
        ),
        false,
    );
});

test('matchesSectionPaths matches child routes for section parents', () => {
    assert.equal(
        matchesSectionPaths(
            ['/admin/members', '/admin/watchlist'],
            '/admin/members/123/loans',
        ),
        true,
    );
    assert.equal(
        matchesSectionPaths(['/admin/requests'], '/admin/requests/99/pdf'),
        true,
    );
    assert.equal(
        matchesSectionPaths(['/client/loans'], '/client/loans/55/payments'),
        true,
    );
});

test('matchesSectionPaths avoids unrelated sections', () => {
    assert.equal(
        matchesSectionPaths(['/admin/requests'], '/admin/members/123'),
        false,
    );
    assert.equal(
        matchesSectionPaths(['/client/loans'], '/client/savings'),
        false,
    );
});
