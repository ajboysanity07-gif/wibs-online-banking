export type LogoPreset = 'mark' | 'full';

export type ReportHeader = {
    designPath: string | null;
    designUrl: string | null;
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
    googleFontUrl?: string | null;
    label: ReportTypographyFont;
    value: ReportTypographyFont;
};

export type LoanSmsTemplates = {
    approved: string;
    declined: string;
};

export type BrandingGeneral = {
    companyName: string;
    businessAddress: string | null;
    businessAddress1: string | null;
    businessAddress2: string | null;
    businessAddress3: string | null;
    businessAddressBarangay: string | null;
    businessAddressZip: string | null;
    businessTin: string | null;
    registrationNo: string | null;
    paymentInstructions: string | null;
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
    brandPalette: BrandPalette;
};

export type BrandPaletteKey =
    | 'ink'
    | 'background'
    | 'card'
    | 'secondary'
    | 'muted'
    | 'border'
    | 'sidebar'
    | 'success'
    | 'warning'
    | 'info'
    | 'danger';

export type BrandPalette = Record<BrandPaletteKey, string | null>;

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
    businessAddress: string | null;
    businessAddress1: string | null;
    businessAddress2: string | null;
    businessAddress3: string | null;
    businessAddressBarangay: string | null;
    businessAddressZip: string | null;
    businessTin: string | null;
    registrationNo: string | null;
    paymentInstructions: string | null;
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
    brandPalette: BrandPalette;
    shortName: string | null;
    timezone: string;
    statementCurrency: string;
    reportFooter: string | null;
    reportFooterText: string | null;
    reportFooterEnabled: boolean;
    serviceHours: string | null;
    smsSendWindow: string;
    loanSmsEnabled: { approved: boolean; declined: boolean };
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
