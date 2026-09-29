import type { ClientTheme } from '../types';

// Surfaces (background, card, sidebar, borders) live in resources/css/app.css.
// Only brand-driven tokens are injected at runtime so they can't override them.
export const mrdincTheme: ClientTheme = {
    name: 'MRDINC Portal',
    hex: {
        background: '#dfe7cb',
        foreground: '#14170f',
        primary: '#176433',
        primaryForeground: '#ffffff',
        accent: '#9abd53',
        accentForeground: '#14170f',
    },
    hsl: {
        light: {
            foreground: '82.5 21.05% 7.45%',
            primary: '141.82 62.60% 24.12%',
            'primary-foreground': '0 0% 100%',
            accent: '79.81 44.54% 53.33%',
            'accent-foreground': '82.5 21.05% 7.45%',
            ring: '141.82 62.60% 24.12%',
        },
        dark: {
            foreground: '77.14 50% 94.51%',
            primary: '79.81 44.54% 53.33%',
            'primary-foreground': '82.5 21.05% 7.45%',
            accent: '79.81 44.54% 53.33%',
            'accent-foreground': '82.5 21.05% 7.45%',
            ring: '79.81 44.54% 53.33%',
            'sidebar-primary': '79.81 44.54% 53.33%',
            'sidebar-primary-foreground': '82.5 21.05% 7.45%',
            'sidebar-accent': '79.81 44.54% 53.33%',
            'sidebar-accent-foreground': '82.5 21.05% 7.45%',
            'sidebar-ring': '79.81 44.54% 53.33%',
        },
    },
};
