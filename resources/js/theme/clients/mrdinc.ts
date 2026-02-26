import type { ClientTheme } from '../types';

export const mrdincTheme: ClientTheme = {
    name: 'MRDINC Portal',
    hex: {
        background: '#ffffff',
        foreground: '#181818',
        primary: '#176433',
        primaryForeground: '#ffffff',
        accent: '#9abd53',
        accentForeground: '#181818',
    },
    hsl: {
        light: {
            background: '0 0% 100%',
            foreground: '0 0% 9.41%',
            primary: '141.82 62.60% 24.12%',
            'primary-foreground': '0 0% 100%',
            accent: '79.81 44.54% 53.33%',
            'accent-foreground': '0 0% 9.41%',
            ring: '141.82 62.60% 24.12%',
        },
        dark: {
            background: '0 0% 9.41%',
            foreground: '0 0% 100%',
            primary: '79.81 44.54% 53.33%',
            'primary-foreground': '0 0% 9.41%',
            accent: '141.82 62.60% 24.12%',
            'accent-foreground': '0 0% 100%',
            ring: '79.81 44.54% 53.33%',
        },
    },
};
