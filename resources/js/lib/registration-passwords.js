const confirmationMatcher = /confirm/i;

/**
 * @typedef {Record<string, string>} FieldErrors
 */

export const PASSWORD_CONFIRMATION_MATCH_MESSAGE = 'Passwords match';
export const PASSWORD_CONFIRMATION_MISMATCH_MESSAGE = 'Passwords do not match';

/**
 * @param {string} password
 * @param {string} confirmation
 * @returns {string}
 */
export const getPasswordConfirmationMismatchMessage = (
    password,
    confirmation,
) => {
    if (!confirmation) {
        return '';
    }

    return password === confirmation
        ? ''
        : PASSWORD_CONFIRMATION_MISMATCH_MESSAGE;
};

/**
 * @param {string | undefined} message
 * @returns {boolean}
 */
export const isPasswordConfirmationError = (message) => {
    if (!message) {
        return false;
    }

    return confirmationMatcher.test(message);
};

/**
 * @param {FieldErrors | undefined} errors
 * @returns {FieldErrors}
 */
export const normalizeRegistrationErrors = (errors) => {
    if (!errors) {
        return {};
    }

    if (!errors.password) {
        return errors;
    }

    if (!isPasswordConfirmationError(errors.password)) {
        return errors;
    }

    const next = { ...errors };

    if (!next.password_confirmation) {
        next.password_confirmation = next.password;
    }

    delete next.password;

    return next;
};
