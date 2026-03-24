export type LogoPreset = 'mark' | 'full';

export type Branding = {
    companyName: string;
    portalLabel: string;
    appTitle: string;
    logoPreset: LogoPreset;
    logoIsWordmark: boolean;
    logoPath: string | null;
    logoUrl: string;
    logoMarkUrl: string;
    logoFullUrl: string;
    logoMarkDefaultUrl: string;
    logoFullDefaultUrl: string;
    logoMarkIsDefault: boolean;
    logoFullIsDefault: boolean;
    faviconPath: string | null;
    faviconUrl: string;
    faviconDefaultUrl: string;
    brandPrimaryColor: string | null;
    brandAccentColor: string | null;
    supportEmail: string | null;
    supportPhone: string | null;
    supportContactName: string | null;
};
