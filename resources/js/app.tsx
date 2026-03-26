import type { Page } from '@inertiajs/core';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import ApiNotice from '@/components/api-notice';
import { Toaster } from '@/components/ui/sonner';
import type { Branding } from '@/types';
import { initializeTheme } from './hooks/use-appearance';
import { mrdincTheme } from './theme/clients/mrdinc';
import { resolveBrandingTheme } from './theme/branding-theme';
import { injectClientTheme } from './theme/inject-theme';

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

initializeTheme();

let appTitle = import.meta.env.VITE_APP_NAME ?? '';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appTitle}` : appTitle),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const updateClientTheme = (page: Page) => {
            const sharedProps = page.props as SharedProps;
            injectClientTheme(
                resolveBrandingTheme(mrdincTheme, sharedProps.branding),
            );
        };
        const updateAppTitle = (page: Page) => {
            appTitle = resolveAppTitle(page.props as SharedProps);
        };

        updateAppTitle(props.initialPage);
        updateClientTheme(props.initialPage);

        document.addEventListener('inertia:navigate', (event) => {
            const page = (event as CustomEvent<{ page: Page }>).detail.page;
            updateAppTitle(page);
            updateClientTheme(page);
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
