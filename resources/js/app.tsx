import { createInertiaApp } from '@inertiajs/react';
import type { Page } from '@inertiajs/core';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import ApiNotice from '@/components/api-notice';
import { Toaster } from '@/components/ui/sonner';
import { initializeTheme } from './hooks/use-appearance';
import { mrdincTheme } from './theme/clients/mrdinc';
import { injectClientTheme } from './theme/inject-theme';
import type { Branding } from '@/types';

type SharedProps = {
    branding?: Branding;
    name?: string;
};

const resolveAppTitle = (props?: SharedProps): string => {
    return (
        props?.branding?.appTitle ??
        props?.name ??
        import.meta.env.VITE_APP_NAME ??
        ''
    );
};

let appTitle = import.meta.env.VITE_APP_NAME ?? '';

injectClientTheme(mrdincTheme);
initializeTheme();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appTitle}` : appTitle),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const updateAppTitle = (page: Page) => {
            appTitle = resolveAppTitle(page.props as SharedProps);
        };

        updateAppTitle(props.initialPage);

        document.addEventListener('inertia:navigate', (event) => {
            updateAppTitle((event as CustomEvent<{ page: Page }>).detail.page);
        });

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
