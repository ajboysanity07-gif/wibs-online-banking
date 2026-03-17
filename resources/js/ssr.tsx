import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
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

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => {
            const appTitle = resolveAppTitle(page.props as SharedProps);
            return title ? `${title} - ${appTitle}` : appTitle;
        },
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.tsx`,
                import.meta.glob('./pages/**/*.tsx'),
            ),
        setup: ({ App, props }) => {
            return <App {...props} />;
        },
    }),
);
