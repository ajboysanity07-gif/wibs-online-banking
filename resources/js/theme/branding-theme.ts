import type { Branding } from '@/types';
import type { ClientTheme } from './types';

type RgbColor = {
    r: number;
    g: number;
    b: number;
};

type HslColor = {
    h: number;
    s: number;
    l: number;
};

const FOREGROUND_LUMINANCE_THRESHOLD = 0.6;

const normalizeHexColor = (value?: string | null): string | null => {
    if (value === null || value === undefined) {
        return null;
    }

    const trimmed = value.trim().toLowerCase();

    if (trimmed === '') {
        return null;
    }

    const raw = trimmed.startsWith('#') ? trimmed.slice(1) : trimmed;

    if (!/^[0-9a-f]{3}$|^[0-9a-f]{6}$/.test(raw)) {
        return null;
    }

    const expanded =
        raw.length === 3
            ? raw
                  .split('')
                  .map((channel) => channel + channel)
                  .join('')
            : raw;

    return `#${expanded}`;
};

const hexToRgb = (hex: string): RgbColor => {
    const normalized = hex.replace('#', '');
    const value = parseInt(normalized, 16);

    return {
        r: (value >> 16) & 255,
        g: (value >> 8) & 255,
        b: value & 255,
    };
};

const rgbToHsl = ({ r, g, b }: RgbColor): HslColor => {
    const red = r / 255;
    const green = g / 255;
    const blue = b / 255;
    const max = Math.max(red, green, blue);
    const min = Math.min(red, green, blue);
    const delta = max - min;
    const lightness = (max + min) / 2;

    if (delta === 0) {
        return { h: 0, s: 0, l: lightness * 100 };
    }

    const saturation =
        lightness > 0.5
            ? delta / (2 - max - min)
            : delta / (max + min);

    let hue = 0;

    switch (max) {
        case red:
            hue = (green - blue) / delta + (green < blue ? 6 : 0);
            break;
        case green:
            hue = (blue - red) / delta + 2;
            break;
        default:
            hue = (red - green) / delta + 4;
            break;
    }

    hue *= 60;

    return {
        h: hue,
        s: saturation * 100,
        l: lightness * 100,
    };
};

const formatHsl = ({ h, s, l }: HslColor): string => {
    return `${h.toFixed(2)} ${s.toFixed(2)}% ${l.toFixed(2)}%`;
};

const rgbChannelToLinear = (value: number): number => {
    const normalized = value / 255;

    return normalized <= 0.03928
        ? normalized / 12.92
        : Math.pow((normalized + 0.055) / 1.055, 2.4);
};

const relativeLuminance = ({ r, g, b }: RgbColor): number => {
    const linearRed = rgbChannelToLinear(r);
    const linearGreen = rgbChannelToLinear(g);
    const linearBlue = rgbChannelToLinear(b);

    return 0.2126 * linearRed + 0.7152 * linearGreen + 0.0722 * linearBlue;
};

const resolveForegroundColor = (
    hex: string,
    lightText: string,
    darkText: string,
): string => {
    const luminance = relativeLuminance(hexToRgb(hex));

    return luminance > FOREGROUND_LUMINANCE_THRESHOLD ? darkText : lightText;
};

export const resolveBrandingTheme = (
    baseTheme: ClientTheme,
    branding?: Branding,
): ClientTheme => {
    const primaryHex =
        normalizeHexColor(branding?.brandPrimaryColor) ?? null;
    const accentHex = normalizeHexColor(branding?.brandAccentColor) ?? null;
    const lightPrimary =
        primaryHex !== null
            ? formatHsl(rgbToHsl(hexToRgb(primaryHex)))
            : baseTheme.hsl.light.primary;
    const lightAccent =
        accentHex !== null
            ? formatHsl(rgbToHsl(hexToRgb(accentHex)))
            : baseTheme.hsl.light.accent;
    const lightPrimaryForeground =
        primaryHex !== null
            ? resolveForegroundColor(
                  primaryHex,
                  baseTheme.hsl.light['primary-foreground'],
                  baseTheme.hsl.light.foreground,
              )
            : baseTheme.hsl.light['primary-foreground'];
    const lightAccentForeground =
        accentHex !== null
            ? resolveForegroundColor(
                  accentHex,
                  baseTheme.hsl.light['accent-foreground'],
                  baseTheme.hsl.light.foreground,
              )
            : baseTheme.hsl.light['accent-foreground'];
    const darkPrimarySource = accentHex ?? primaryHex;
    const darkAccentSource = primaryHex ?? accentHex;
    const darkPrimary =
        darkPrimarySource !== null
            ? formatHsl(rgbToHsl(hexToRgb(darkPrimarySource)))
            : baseTheme.hsl.dark.primary;
    const darkAccent =
        darkAccentSource !== null
            ? formatHsl(rgbToHsl(hexToRgb(darkAccentSource)))
            : baseTheme.hsl.dark.accent;
    const darkPrimaryForeground =
        darkPrimarySource !== null
            ? resolveForegroundColor(
                  darkPrimarySource,
                  baseTheme.hsl.dark.foreground,
                  baseTheme.hsl.dark['primary-foreground'],
              )
            : baseTheme.hsl.dark['primary-foreground'];
    const darkAccentForeground =
        darkAccentSource !== null
            ? resolveForegroundColor(
                  darkAccentSource,
                  baseTheme.hsl.dark['accent-foreground'],
                  baseTheme.hsl.dark['primary-foreground'],
              )
            : baseTheme.hsl.dark['accent-foreground'];

    return {
        ...baseTheme,
        hex: {
            ...baseTheme.hex,
            primary: primaryHex ?? baseTheme.hex.primary,
            accent: accentHex ?? baseTheme.hex.accent,
        },
        hsl: {
            light: {
                ...baseTheme.hsl.light,
                primary: lightPrimary,
                'primary-foreground': lightPrimaryForeground,
                accent: lightAccent,
                'accent-foreground': lightAccentForeground,
                ring: lightPrimary,
                'sidebar-primary': lightPrimary,
                'sidebar-primary-foreground': lightPrimaryForeground,
                'sidebar-accent': lightAccent,
                'sidebar-accent-foreground': lightAccentForeground,
                'sidebar-ring': lightPrimary,
            },
            dark: {
                ...baseTheme.hsl.dark,
                primary: darkPrimary,
                'primary-foreground': darkPrimaryForeground,
                accent: darkAccent,
                'accent-foreground': darkAccentForeground,
                ring: darkPrimary,
                'sidebar-primary': darkPrimary,
                'sidebar-primary-foreground': darkPrimaryForeground,
                'sidebar-accent': darkAccent,
                'sidebar-accent-foreground': darkAccentForeground,
                'sidebar-ring': darkPrimary,
            },
        },
    };
};
