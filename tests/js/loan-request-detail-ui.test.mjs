import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';

const { estimateLoanRequest } = await import(
    pathToFileURL(resolve('resources', 'js', 'lib', 'loan-request-estimate.ts'))
        .href
);

test('estimate matches the reference design figures', () => {
    const estimate = estimateLoanRequest(50000, 1);

    assert.equal(estimate.serviceCharge, 1000);
    assert.equal(estimate.loanSecurity, 1000);
    assert.equal(estimate.documentaryStamp, 375);
    assert.equal(estimate.netProceeds, 47625);
    assert.equal(estimate.interest, 750);
    assert.equal(estimate.totalRepayable, 50750);
    assert.equal(estimate.perPayment, 50750);
});

test('multi-month estimate charges interest per month and rounds to centavos', () => {
    const estimate = estimateLoanRequest(10000, 3);

    assert.equal(estimate.interest, 450);
    assert.equal(estimate.totalRepayable, 10450);
    assert.equal(estimate.perPayment, 3483.33);
});

test('member show page renders the new detail view and keeps the real payment-method flow', async () => {
    const page = await readFile(
        resolve('resources', 'js', 'pages', 'client', 'loan-request-show.tsx'),
        'utf8',
    );

    assert.match(page, /<LoanRequestDetailView/);
    assert.match(page, /<LoanRequestConfirmation/);
    assert.match(page, /PaymentAccountPickerSheet/);
    assert.match(page, /appendLocalActivity\(`Release method changed to/);
    assert.match(page, /appendLocalActivity\(`Repayment method changed to/);
    assert.match(page, /hideGuidance/);
});

test('right rail has parties, a single actions card with the cancel zone, and a keep-application dialog', async () => {
    const view = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-detail.tsx',
        ),
        'utf8',
    );
    const page = await readFile(
        resolve('resources', 'js', 'pages', 'client', 'loan-request-show.tsx'),
        'utf8',
    );

    assert.match(view, /Parties to the request/);
    assert.match(view, /Co-maker 1/);
    assert.match(view, /setOpenParty\(party\.key\)/);
    assert.match(
        view,
        /Download PDF[\s\S]*Edit Application[\s\S]*Application action[\s\S]*Cancel Application[\s\S]*Back to loan requests/,
    );
    assert.match(view, /variant="destructive"/);
    assert.match(view, /<AlertDialogCancel[\s\S]*?Keep application/);
    assert.doesNotMatch(view, /Quick actions/);

    // Duplicates in the shared detail page are hidden for members.
    assert.match(page, /hideStatusCard/);
    assert.match(page, /hideRoutineActions/);
    assert.match(page, /show: canCancelApplication/);
    assert.equal(page.match(/cancellation=\{\{/g)?.length, 1);
});

test('party profile dialog is a flat grouped summary, not a nested accordion card', async () => {
    const view = await readFile(
        resolve(
            'resources',
            'js',
            'components',
            'loan-request',
            'loan-request-detail.tsx',
        ),
        'utf8',
    );

    assert.match(view, /<PartyProfileSummary/);
    assert.match(
        view,
        /'Personal'[\s\S]*'Contact & address'[\s\S]*'Family'[\s\S]*'Work & income'/,
    );
    assert.match(view, /field\.value !== '--'/);
    assert.doesNotMatch(view, /<PersonAccordionRow|<LoanRequestApplicantCard/);
});
