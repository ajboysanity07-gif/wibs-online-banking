import type { ClientTheme, ThemeTokens } from './types';

const STYLE_ID = 'client-theme-vars';

const serializeTokens = (tokens: ThemeTokens): string => {
    return Object.entries(tokens)
        .map(([key, value]) => `--${key}: hsl(${value});`)
        .join('');
};

export function injectClientTheme(theme: ClientTheme): void {
    if (typeof document === 'undefined') {
        return;
    }

    const style =
        document.getElementById(STYLE_ID) ??
        document.createElement('style');
    style.id = STYLE_ID;

    style.textContent = `:root{${serializeTokens(theme.hsl.light)}}.dark{${serializeTokens(theme.hsl.dark)}}`;

    if (!style.parentNode) {
        document.head.appendChild(style);
    }
}
