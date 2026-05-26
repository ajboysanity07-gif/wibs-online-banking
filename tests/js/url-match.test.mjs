import assert from 'node:assert/strict';
import test from 'node:test';
import {
    isRouteMatch,
    isWithinSectionPath,
    matchesExactPaths,
    matchesSectionPaths,
    normalizePath,
} from '../../resources/js/lib/url-match.js';
import {
    memberLoanRequestsBasePath,
    memberLoanRequestsNavMatchOptions,
    memberLoansBasePath,
    memberLoansNavMatchOptions,
} from '../../resources/js/lib/member-sidebar-nav-match.js';

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

const isNavItemActive = (href, options, currentPath) => {
    const targets =
        options.matchPaths && options.matchPaths.length > 0
            ? options.matchPaths
            : [href];

    return isRouteMatch({
        currentPath,
        targets,
        excludedTargets: options.excludeMatchPaths ?? [],
        match: options.match ?? 'exact',
    });
};

test('loan request routes only activate the Loan requests sidebar item', () => {
    const loanRequestRoutes = [
        '/client/loans/request',
        '/client/loans/requests',
        '/client/loans/requests/123',
        '/client/loans/requests/123/print',
    ];

    loanRequestRoutes.forEach((currentPath) => {
        const loansActive = isNavItemActive(
            memberLoansBasePath,
            memberLoansNavMatchOptions,
            currentPath,
        );
        const loanRequestsActive = isNavItemActive(
            memberLoanRequestsBasePath,
            memberLoanRequestsNavMatchOptions,
            currentPath,
        );

        assert.equal(
            loansActive,
            false,
            `Loans should be inactive for ${currentPath}`,
        );
        assert.equal(
            loanRequestsActive,
            true,
            `Loan requests should be active for ${currentPath}`,
        );
        assert.equal(
            Number(loansActive) + Number(loanRequestsActive),
            1,
            `Exactly one member sidebar item should be active for ${currentPath}`,
        );
    });
});

test('actual loan routes only activate the Loans sidebar item', () => {
    const loanRoutes = [
        '/client/loans',
        '/client/loans/LN-001/schedule',
    ];

    loanRoutes.forEach((currentPath) => {
        const loansActive = isNavItemActive(
            memberLoansBasePath,
            memberLoansNavMatchOptions,
            currentPath,
        );
        const loanRequestsActive = isNavItemActive(
            memberLoanRequestsBasePath,
            memberLoanRequestsNavMatchOptions,
            currentPath,
        );

        assert.equal(
            loansActive,
            true,
            `Loans should be active for ${currentPath}`,
        );
        assert.equal(
            loanRequestsActive,
            false,
            `Loan requests should be inactive for ${currentPath}`,
        );
        assert.equal(
            Number(loansActive) + Number(loanRequestsActive),
            1,
            `Exactly one member sidebar item should be active for ${currentPath}`,
        );
    });
});
