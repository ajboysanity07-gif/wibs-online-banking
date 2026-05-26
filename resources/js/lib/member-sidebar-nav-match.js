export const memberLoansBasePath = '/client/loans';

export const memberLoanRequestsBasePath = '/client/loans/requests';

export const memberLoanRequestSectionPaths = [
    '/client/loans/request',
    '/client/loans/requests',
];

/** @type {{ match: 'section', excludeMatchPaths: string[] }} */
export const memberLoansNavMatchOptions = {
    match: 'section',
    excludeMatchPaths: [...memberLoanRequestSectionPaths],
};

/** @type {{ match: 'section', matchPaths: string[] }} */
export const memberLoanRequestsNavMatchOptions = {
    match: 'section',
    matchPaths: [...memberLoanRequestSectionPaths],
};
