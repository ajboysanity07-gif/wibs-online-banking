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

if (typeof document !== 'undefined') {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (token) {
        client.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }
}

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
