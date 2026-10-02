import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

const read = (...parts) =>
    readFile(resolve('resources', 'js', ...parts), 'utf8');

const componentPath = [
    'components',
    'loan-request',
    'loan-request-confirmation.tsx',
];

test('confirmation component renders the six-section reference design', async () => {
    const source = await read(...componentPath);

    // 1. Success header.
    assert.match(source, /Application submitted successfully/);
    assert.match(source, /We've received your loan application/);
    // Order-agnostic: prettier-plugin-tailwindcss reorders className tokens.
    assert.match(
        source,
        /className="(?=[^"]*\bsize-16\b)(?=[^"]*\bbg-secondary\b)[^"]*"/,
    );
    assert.match(source, /<Check[\s\S]{0,250}strokeWidth=\{3\}/);

    // 2. Application details rows with dividers.
    assert.match(source, /Application details/);
    assert.match(source, /Reference number/);
    assert.match(source, /Loan amount/);
    assert.match(source, /Payment term/);
    assert.match(source, /Submitted on/);
    assert.match(source, /formatCurrency\(requestedAmount\)/);
    assert.match(source, /\{requestedTerm\} month/);
    assert.match(
        source,
        /format\(\s*new Date\(submittedDate\),\s*'MMMM d, yyyy'/,
    );

    // 3. Status timeline: four arrow steps, Submitted active.
    assert.match(source, /Application status/);
    assert.match(
        source,
        /\[clip-path:polygon\(0_0,calc\(100%_-_12px\)_0,100%_50%/,
    );
    assert.match(source, /label: 'Submitted', active: true/);
    assert.match(source, /label: 'Processing', active: false/);
    assert.match(source, /label: 'Approval', active: false/);
    assert.match(source, /label: 'Release', active: false/);
    assert.match(
        source,
        /bg-secondary font-semibold text-secondary-foreground/,
    );
    assert.match(source, /bg-muted font-medium text-muted-foreground/);
    assert.match(source, /scrollbar-hide/);
    assert.match(source, /Expected review within 3 working days/);

    // 4. Numbered next steps.
    assert.match(source, /What happens next/);
    assert.match(source, /Initial review/);
    assert.match(source, /Verification call/);
    assert.match(source, /Document signing/);
    assert.match(source, /bg-secondary text-secondary-foreground/);
    assert.match(source, /bg-muted text-muted-foreground/);

    // 5. Reminder banner uses the secondary (tint) palette.
    assert.match(source, /Keep your phone accessible/);
    assert.match(
        source,
        /We'll send SMS and email updates about your application/,
    );
    assert.match(source, /<Alert className="border-secondary bg-secondary/);
    assert.match(source, /text-secondary-foreground/);

    // 6. Actions.
    assert.match(source, /Track my application/);
    assert.match(source, /Apply for another loan/);
    assert.match(source, /min-h-\[44px\]/);
});

test('confirmation uses only tokens defined in the WIBS theme', async () => {
    const source = await read(...componentPath);

    // tint/neutral are documented in the design prompt but never shipped in
    // app.css; secondary/muted are the real equivalents.
    assert.doesNotMatch(source, /bg-tint|text-tint/);
    assert.doesNotMatch(source, /bg-neutral|text-neutral/);
    assert.doesNotMatch(source, /scrollbar-none/);
});

test('confirmation actions wire up navigation correctly', async () => {
    const source = await read(...componentPath);

    // Track my application revisits the request page (the one-time submit
    // flash is gone, so the server renders the tracking view) and replaces the
    // history entry so Back doesn't reopen the success screen.
    assert.match(
        source,
        /router\.visit\(loanRequestShow\(loanRequestId\)\.url, \{ replace: true \}\)/,
    );
    assert.doesNotMatch(source, /router\.reload/);
    assert.doesNotMatch(source, /justSubmitted/);

    // Both actions go through Wayfinder routes, not ziggy.
    assert.match(
        source,
        /import \{\s*create as loanRequestCreate,\s*show as loanRequestShow,\s*\} from '@\/routes\/client\/loan-requests'/,
    );
    assert.match(source, /router\.visit\(loanRequestCreate\(\)\.url\)/);
    assert.doesNotMatch(source, /ziggy-js/);
});

test('loan request show page renders the confirmation after submitting', async () => {
    const page = await read('pages', 'client', 'loan-request-show.tsx');

    assert.match(
        page,
        /import \{ LoanRequestConfirmation \} from '@\/components\/loan-request\/loan-request-confirmation'/,
    );
    assert.match(
        page,
        /\{justSubmitted \? \([\s\S]{0,60}<LoanRequestConfirmation/,
    );

    // The success screen replaces the tracking view instead of stacking on
    // top of it.
    assert.match(
        page,
        /submittedDate=\{currentLoanRequest\.submitted_at\}\s*\/>\s*\) : \(\s*<>\s*<LoanRequestDetailView/,
    );
    assert.doesNotMatch(page, /\) : null\}\s*<LoanRequestDetailView/);

    // Props come from the loan request payload.
    assert.match(page, /loanRequestId=\{currentLoanRequest\.id\}/);
    assert.match(page, /reference=\{currentLoanRequest\.reference\}/);
    assert.match(
        page,
        /requestedAmount=\{Number\(\s*currentLoanRequest\.requested_amount,?\s*\)\}/,
    );
    assert.match(
        page,
        /requestedTerm=\{Number\(currentLoanRequest\.requested_term\)\}/,
    );
    assert.match(page, /submittedDate=\{currentLoanRequest\.submitted_at\}/);

    // The old inline confirmation card is gone.
    assert.doesNotMatch(page, /Application submitted\n/);
});
