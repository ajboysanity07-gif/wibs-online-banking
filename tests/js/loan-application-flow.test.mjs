import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';

const {
    deriveSectionStatus,
    guidedProgress,
    nextIncompleteSection,
    sectionErrorMessages,
    sectionForErrorKey,
} = await import(
    pathToFileURL(resolve('resources', 'js', 'lib', 'loan-application-flow.ts'))
        .href
);

test('section status: errors win, then done, then started', () => {
    assert.equal(
        deriveSectionStatus({ complete: true, started: true, hasErrors: true }),
        'needs_attention',
    );
    assert.equal(
        deriveSectionStatus({ complete: true, started: true, hasErrors: false }),
        'done',
    );
    assert.equal(
        deriveSectionStatus({
            complete: false,
            started: true,
            hasErrors: false,
        }),
        'in_progress',
    );
    assert.equal(
        deriveSectionStatus({
            complete: false,
            started: false,
            hasErrors: false,
        }),
        'not_started',
    );
});

test('next incomplete section searches forward and wraps around', () => {
    const statuses = {
        loan: 'in_progress',
        about: 'done',
        co: 'done',
        disb: 'not_started',
        decl: 'done',
    };

    assert.equal(nextIncompleteSection(statuses), 'loan');
    assert.equal(nextIncompleteSection(statuses, 'loan'), 'disb');
    assert.equal(nextIncompleteSection(statuses, 'disb'), 'loan');
    assert.equal(
        nextIncompleteSection({
            loan: 'done',
            about: 'done',
            co: 'done',
            disb: 'done',
            decl: 'done',
        }),
        null,
    );
});

test('guided progress keeps 5 segments and partially fills the current one', () => {
    const coMakerContact = guidedProgress('co', 2, 3);

    assert.equal(coMakerContact.step, 3);
    assert.equal(coMakerContact.total, 5);
    assert.equal(coMakerContact.fills.length, 5);
    assert.deepEqual(
        coMakerContact.fills.map((fill) => Math.round(fill * 100)),
        [100, 100, 67, 0, 0],
    );
    assert.deepEqual(guidedProgress('loan').fills, [1, 0, 0, 0, 0]);
    assert.equal(guidedProgress('decl').value, 5);
});

test('validation errors map back to their section', () => {
    assert.equal(sectionForErrorKey('requested_amount'), 'loan');
    assert.equal(sectionForErrorKey('applicant.cell_no'), 'about');
    assert.equal(sectionForErrorKey('co_maker_2.first_name'), 'co');
    assert.equal(sectionForErrorKey('banking.release_method'), 'disb');
    assert.equal(
        sectionForErrorKey('declarations.declaration_truth_confirmation'),
        'decl',
    );
    assert.equal(sectionForErrorKey('undertaking_accepted'), null);
});

test('section error messages are listed for read-only sections', () => {
    assert.deepEqual(
        sectionErrorMessages(
            {
                'applicant.cell_no': 'The cell no. field format is invalid.',
                loan_prerequisites: 'Please complete your profile.',
                'applicant.address1': 'The cell no. field format is invalid.',
                'co_maker_1.first_name': 'The first name field is required.',
                'applicant.payday': undefined,
            },
            'about',
        ),
        [
            'The cell no. field format is invalid.',
            'Please complete your profile.',
        ],
    );
    assert.deepEqual(sectionErrorMessages({}, 'about'), []);
});
