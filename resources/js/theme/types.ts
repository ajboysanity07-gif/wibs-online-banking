export type ThemeMode = 'light' | 'dark';
export type ThemeTokens = Record<string, string>;

export interface ClientTheme {
    name: string;
    hex: {
        background: string;
        foreground: string;
        primary: string;
        primaryForeground: string;
        accent: string;
        accentForeground: string;
    };
    hsl: {
        light: ThemeTokens;
        dark: ThemeTokens;
    };
}
