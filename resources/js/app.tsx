import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import ApiNotice from '@/components/api-notice';
import { Toaster } from '@/components/ui/sonner';
import { initializeTheme } from './hooks/use-appearance';
import { mrdincTheme } from './theme/clients/mrdinc';
import { injectClientTheme } from './theme/inject-theme';

const appName = import.meta.env.VITE_APP_NAME || 'MRDINC Portal';

injectClientTheme(mrdincTheme);
initializeTheme();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <ApiNotice />
                <Toaster />
                <App {...props} />
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
