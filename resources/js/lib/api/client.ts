import { router } from '@inertiajs/react';
import axios, { type AxiosError } from 'axios';

const client = axios.create({
    baseURL: '/',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

const mutatingMethods = new Set(['post', 'put', 'patch', 'delete']);

const getCookieValue = (name: string): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const entry = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith(`${name}=`));

    if (!entry) {
        return null;
    }

    const [, value] = entry.split('=');

    return value ? decodeURIComponent(value) : null;
};

const getCsrfToken = (): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const cookieToken = getCookieValue('XSRF-TOKEN');

    if (cookieToken) {
        return cookieToken;
    }

    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    );
};

client.interceptors.request.use((config) => {
    const method = config.method?.toLowerCase() ?? '';

    if (!mutatingMethods.has(method)) {
        return config;
    }

    const token = getCsrfToken();

    if (token) {
        config.headers = {
            ...config.headers,
            'X-CSRF-TOKEN': token,
        };
    }

    return config;
});

client.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
        const status = error.response?.status;

        if (status === 401 && typeof window !== 'undefined') {
            router.visit('/login');
        }

        if (status === 403 && typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('api:forbidden'));
        }

        if (status === 419 && typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('api:session-expired'));
        }

        return Promise.reject(error);
    },
);

export default client;
