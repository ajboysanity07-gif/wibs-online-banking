export type LogoPreset = 'mark' | 'full';

export type ReportHeaderAlignment = 'left' | 'center' | 'right';

export type ReportHeader = {
    title: string | null;
    tagline: string | null;
    showLogo: boolean;
    showCompanyName: boolean;
    alignment: ReportHeaderAlignment;
};

export type ReportTypographyFont = {
    family: string;
    variant: string;
    weight: number;
    size: number;
    color: string | null;
    cssFamily: string;
    cssStyle: string;
};

export type ReportTypography = {
    headerTitle: ReportTypographyFont;
    headerTagline: ReportTypographyFont;
    label: ReportTypographyFont;
    value: ReportTypographyFont;
};

export type LoanSmsTemplates = {
    approved: string;
    declined: string;
};

export type BrandingGeneral = {
    companyName: string;
    portalLabel: string;
    appTitle: string;
};

export type BrandingAssets = {
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
};

export type BrandingContact = {
    supportEmail: string | null;
    supportPhone: string | null;
    supportContactName: string | null;
};

export type BrandingReports = {
    header: ReportHeader;
    typography: ReportTypography;
};

export type BrandingCommunications = {
    loanSmsTemplates: LoanSmsTemplates;
};

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
    reportHeader: ReportHeader;
    reportTypography: ReportTypography;
    general: BrandingGeneral;
    assets: BrandingAssets;
    contact: BrandingContact;
    reports: BrandingReports;
    communications: BrandingCommunications;
};
