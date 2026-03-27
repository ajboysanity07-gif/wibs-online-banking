import assert from 'node:assert/strict';
import test from 'node:test';
import {
    getPasswordConfirmationMismatchMessage,
    normalizeRegistrationErrors,
    PASSWORD_CONFIRMATION_MISMATCH_MESSAGE,
} from '../../resources/js/lib/registration-passwords.js';

test('password confirmation mismatch returns a client-side error message', () => {
    assert.equal(
        getPasswordConfirmationMismatchMessage('correct-horse', 'battery'),
        PASSWORD_CONFIRMATION_MISMATCH_MESSAGE,
    );
});

test('password confirmation match does not return a mismatch message', () => {
    assert.equal(
        getPasswordConfirmationMismatchMessage('correct-horse', 'correct-horse'),
        '',
    );
});

test('normalizeRegistrationErrors moves confirmation errors to password_confirmation', () => {
    const errors = {
        password: 'The password field confirmation does not match.',
    };

    const normalized = normalizeRegistrationErrors(errors);

    assert.equal(
        normalized.password_confirmation,
        'The password field confirmation does not match.',
    );
    assert.equal('password' in normalized, false);
});

test('normalizeRegistrationErrors keeps strength errors on password', () => {
    const errors = {
        password: 'The password field must be at least 8 characters.',
    };

    const normalized = normalizeRegistrationErrors(errors);

    assert.equal(
        normalized.password,
        'The password field must be at least 8 characters.',
    );
    assert.equal('password_confirmation' in normalized, false);
});
