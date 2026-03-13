import axios, { type AxiosError } from 'axios';
import client from '@/lib/api/client';

type ValidationErrors = Record<string, string[]>;
type FieldErrors = Record<string, string>;
type ApiErrorPayload = {
    message?: string;
};

export const mapValidationErrors = (errors?: ValidationErrors): FieldErrors => {
    if (!errors) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(errors).map(([key, messages]) => [
            key,
            Array.isArray(messages) && messages.length > 0 ? messages[0] : '',
        ]),
    );
};

export const getApiErrorMessage = (
    error: unknown,
    fallbackMessage: string,
): string => {
    if (axios.isAxiosError(error)) {
        const payload = error.response?.data as ApiErrorPayload | undefined;

        if (payload?.message) {
            return payload.message;
        }
    }

    if (error instanceof Error && error.message) {
        return error.message;
    }

    return fallbackMessage;
};

export default client;
