import type { AxiosError } from 'axios';
import axios from 'axios';
import { CheckCircle2, XCircle } from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';

type ToastOptions = {
    id?: string | number;
    description?: string;
    duration?: number;
};

type LaravelErrors = Record<string, string[] | string>;

type LaravelErrorPayload = {
    message?: unknown;
    errors?: LaravelErrors;
    error?: unknown;
    title?: unknown;
};

const DEFAULT_DURATION = 4000;

const formatEntity = (entity: string, lowerCase = false): string => {
    const trimmed = entity.trim();

    if (!trimmed) {
        return entity;
    }

    if (lowerCase) {
        return `${trimmed.charAt(0).toLowerCase()}${trimmed.slice(1)}`;
    }

    return `${trimmed.charAt(0).toUpperCase()}${trimmed.slice(1)}`;
};

export const adminToastCopy = {
    success: {
        created: (entity: string) =>
            `${formatEntity(entity)} created successfully.`,
        updated: (entity: string) =>
            `${formatEntity(entity)} updated successfully.`,
        saved: (entity: string) =>
            `${formatEntity(entity)} saved successfully.`,
        enabled: (entity: string) =>
            `${formatEntity(entity)} enabled successfully.`,
        disabled: (entity: string) =>
            `${formatEntity(entity)} disabled successfully.`,
        approved: (entity: string) =>
            `${formatEntity(entity)} approved successfully.`,
        suspended: (entity: string) =>
            `${formatEntity(entity)} suspended successfully.`,
        reactivated: (entity: string) =>
            `${formatEntity(entity)} reactivated successfully.`,
    },
    error: {
        created: (entity: string) =>
            `Failed to create ${formatEntity(entity, true)}.`,
        updated: (entity: string) =>
            `Failed to update ${formatEntity(entity, true)}.`,
        saved: () => 'Failed to save changes.',
        enabled: (entity: string) =>
            `Failed to enable ${formatEntity(entity, true)}.`,
        disabled: (entity: string) =>
            `Failed to disable ${formatEntity(entity, true)}.`,
        approved: (entity: string) =>
            `Failed to approve ${formatEntity(entity, true)}.`,
        updatedStatus: () => 'Failed to update account status.',
    },
};

const getFirstErrorMessage = (errors: unknown): string | null => {
    if (!errors || typeof errors !== 'object') {
        return null;
    }

    for (const value of Object.values(errors)) {
        if (Array.isArray(value) && typeof value[0] === 'string') {
            return value[0];
        }

        if (typeof value === 'string') {
            return value;
        }
    }

    return null;
};

const extractErrorMessage = (
    error: AxiosError | Error | unknown,
): string | null => {
    if (typeof error === 'string' && error.trim() !== '') {
        return error.trim();
    }

    if (axios.isAxiosError(error)) {
        const data = error.response?.data;

        if (typeof data === 'string' && data.trim() !== '') {
            return data.trim();
        }

        if (data && typeof data === 'object') {
            const payload = data as LaravelErrorPayload;
            const message =
                typeof payload.message === 'string'
                    ? payload.message.trim()
                    : null;

            if (message) {
                return message;
            }

            const title =
                typeof payload.title === 'string' ? payload.title.trim() : null;

            if (title) {
                return title;
            }

            const errorMessage =
                typeof payload.error === 'string' ? payload.error.trim() : null;

            if (errorMessage) {
                return errorMessage;
            }

            const validationMessage = getFirstErrorMessage(payload.errors);

            if (validationMessage) {
                return validationMessage;
            }
        }
    }

    if (error && typeof error === 'object') {
        const payload = error as LaravelErrorPayload;
        const message =
            typeof payload.message === 'string' ? payload.message.trim() : null;

        if (message) {
            return message;
        }

        const title =
            typeof payload.title === 'string' ? payload.title.trim() : null;

        if (title) {
            return title;
        }

        const errorMessage =
            typeof payload.error === 'string' ? payload.error.trim() : null;

        if (errorMessage) {
            return errorMessage;
        }

        const validationMessage = getFirstErrorMessage(
            payload.errors ?? payload,
        );

        if (validationMessage) {
            return validationMessage;
        }
    }

    if (error instanceof Error && error.message.trim() !== '') {
        return error.message.trim();
    }

    return null;
};

export const showSuccessToast = (
    message: string,
    options?: ToastOptions,
): string | number => {
    return toast.success(message, {
        id: options?.id,
        duration: options?.duration ?? DEFAULT_DURATION,
        description: options?.description,
        icon: (
            <CheckCircle2 className="h-4 w-4 text-primary" aria-hidden="true" />
        ),
        className: cn('border-l-4 border-l-primary'),
    });
};

export const showErrorToast = (
    error: unknown,
    fallbackMessage: string,
    options?: ToastOptions,
): string | number => {
    const message = extractErrorMessage(error) ?? fallbackMessage;

    return toast.error(message, {
        id: options?.id,
        duration: options?.duration ?? DEFAULT_DURATION,
        description: options?.description,
        icon: (
            <XCircle className="h-4 w-4 text-destructive" aria-hidden="true" />
        ),
        className: cn('border-l-4 border-l-destructive'),
    });
};
