import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import FontPicker from 'react-fontpicker-ts';
import 'react-fontpicker-ts/dist/index.css';
import OrganizationSettingsController from '@/actions/App/Http/Controllers/Admin/OrganizationSettingsController';
import InputError from '@/components/input-error';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import { useBranding } from '@/hooks/use-branding';
import AppLayout from '@/layouts/app-layout';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import { dashboard } from '@/routes/admin';
import { organization as organizationSettings } from '@/routes/admin/settings';
import { mrdincTheme } from '@/theme/clients/mrdinc';
import type {
    BreadcrumbItem,
    LogoPreset,
    ReportHeaderAlignment,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Organization settings',
        href: organizationSettings().url,
    },
];

const FAVICON_MAX_BYTES = 1024 * 1024;
const FAVICON_ALLOWED_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/x-icon',
    'image/vnd.microsoft.icon',
]);
const LOGO_MAX_BYTES = 2 * 1024 * 1024;
const LOGO_ALLOWED_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const DEFAULT_BRAND_PRIMARY = mrdincTheme.hex.primary.toLowerCase();
const DEFAULT_BRAND_ACCENT = mrdincTheme.hex.accent.toLowerCase();
const PRIMARY_COLOR_ERROR =
    'Primary color must be a valid hex value (e.g., #1a2b3c).';
const ACCENT_COLOR_ERROR =
    'Accent color must be a valid hex value (e.g., #1a2b3c).';
const REPORT_HEADER_COLOR_ERROR =
    'Header color must be a valid hex value (e.g., #1a2b3c).';
const REPORT_TAGLINE_COLOR_ERROR =
    'Tagline color must be a valid hex value (e.g., #1a2b3c).';
const REPORT_LABEL_COLOR_ERROR =
    'Label color must be a valid hex value (e.g., #1a2b3c).';
const REPORT_VALUE_COLOR_ERROR =
    'Value color must be a valid hex value (e.g., #1a2b3c).';
const LOGO_PRESET_OPTIONS: Array<{
    value: LogoPreset;
    label: string;
    description: string;
}> = [
    {
        value: 'mark',
        label: 'Logo mark',
        description: 'Compact icon-only logo.',
    },
    {
        value: 'full',
        label: 'Logo full',
        description: 'Full wordmark logo.',
    },
];
const REPORT_HEADER_ALIGNMENT_OPTIONS: Array<{
    value: ReportHeaderAlignment;
    label: string;
}> = [
    { value: 'left', label: 'Left' },
    { value: 'center', label: 'Center' },
    { value: 'right', label: 'Right' },
];
const ICON_PREVIEW_SIZES = [16, 24, 32];
const DEFAULT_REPORT_HEADER_TITLE = 'Application Form';
const DEFAULT_REPORT_HEADER_COLOR = '#111111';
const DEFAULT_REPORT_LABEL_COLOR = '#333333';
const DEFAULT_REPORT_VALUE_COLOR = '#111111';
const REPORT_FONT_WEIGHT_OPTIONS = [
    { value: '300', label: 'Light' },
    { value: '400', label: 'Regular' },
    { value: '500', label: 'Medium' },
    { value: '600', label: 'Semibold' },
    { value: '700', label: 'Bold' },
    { value: '800', label: 'Extra bold' },
    { value: '900', label: 'Black' },
];
const REPORT_FONT_STYLE_OPTIONS = [
    { value: 'regular', label: 'Regular' },
    { value: 'italic', label: 'Italic' },
];
const DEFAULT_LOAN_SMS_APPROVED_TEMPLATE =
    '{company_name} {portal_label}: Your loan request ({loan_reference}) has been APPROVED for {approved_amount} payable over {approved_term} months. Please visit the {office_name} office to finalize your loan.';
const DEFAULT_LOAN_SMS_DECLINED_TEMPLATE =
    '{company_name} {portal_label}: Your loan request ({loan_reference}) has been DECLINED. For questions or clarification, please contact the {office_name} office.';
const LOAN_SMS_PLACEHOLDERS = [
    { token: '{company_name}', label: 'Company name' },
    { token: '{portal_label}', label: 'Portal label' },
    { token: '{message_prefix}', label: 'Smart message prefix' },
    { token: '{office_name}', label: 'Office name' },
    { token: '{loan_reference}', label: 'Loan request reference' },
    { token: '{approved_amount}', label: 'Approved amount' },
    { token: '{approved_term}', label: 'Approved term (months)' },
];

const normalizeHexValue = (value: string): string | null => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const withHash = trimmed.startsWith('#') ? trimmed : `#${trimmed}`;
    const lower = withHash.toLowerCase();
    const shortMatch = /^#([0-9a-f]{3})$/.exec(lower);

    if (shortMatch) {
        const expanded = shortMatch[1]
            .split('')
            .map((char) => char + char)
            .join('');

        return `#${expanded}`;
    }

    if (/^#[0-9a-f]{6}$/.test(lower)) {
        return lower;
    }

    return null;
};

const normalizeHexInputValue = (value: string | null | undefined): string => {
    if (!value) {
        return '';
    }

    const normalized = normalizeHexValue(value);

    return normalized ?? value.trim();
};

const resolveAppTitlePreview = (
    companyName: string,
    portalLabel: string,
): string => {
    const normalizedCompany = companyName.trim();
    const normalizedPortal = portalLabel.trim();

    if (normalizedPortal === '') {
        return normalizedCompany;
    }

    if (
        normalizedCompany !== '' &&
        normalizedPortal.toLowerCase().includes(normalizedCompany.toLowerCase())
    ) {
        return normalizedPortal;
    }

    return normalizedCompany !== ''
        ? `${normalizedPortal} - ${normalizedCompany}`
        : normalizedPortal;
};

const resolveMessagePrefix = (
    companyName: string,
    portalLabel: string,
): string => {
    const normalizedCompany = companyName.trim();
    const normalizedPortal = portalLabel.trim();

    if (normalizedPortal && normalizedCompany) {
        if (
            normalizedPortal.toLowerCase().includes(normalizedCompany.toLowerCase())
        ) {
            return normalizedPortal;
        }

        return `${normalizedCompany} ${normalizedPortal}`.trim();
    }

    return normalizedPortal || normalizedCompany;
};

const resolveOfficeName = (companyName: string, portalLabel: string): string => {
    const normalizedCompany = companyName.trim();

    if (normalizedCompany !== '') {
        return normalizedCompany;
    }

    const normalizedPortal = portalLabel.trim();

    return normalizedPortal !== '' ? normalizedPortal : 'coop';
};

const resolvePortalLabelForMessage = (
    companyName: string,
    portalLabel: string,
): string => {
    const normalizedCompany = companyName.trim();
    const normalizedPortal = portalLabel.trim();

    if (normalizedPortal === '' || normalizedCompany === '') {
        return normalizedPortal;
    }

    if (
        !normalizedPortal
            .toLowerCase()
            .includes(normalizedCompany.toLowerCase())
    ) {
        return normalizedPortal;
    }

    let stripped = normalizedPortal;
    const needle = normalizedCompany.toLowerCase();

    while (true) {
        const index = stripped.toLowerCase().indexOf(needle);

        if (index < 0) {
            break;
        }

        stripped =
            stripped.slice(0, index) +
            stripped.slice(index + normalizedCompany.length);
    }

    stripped = stripped
        .replace(/\s{2,}/g, ' ')
        .trim()
        .replace(/^[-:]+|[-:]+$/g, '')
        .trim();

    return stripped !== '' ? stripped : normalizedPortal;
};

const renderLoanSmsTemplate = (
    template: string,
    replacements: Record<string, string>,
): string => {
    const rendered = Object.entries(replacements).reduce(
        (message, [token, value]) => message.split(token).join(value),
        template,
    );

    return rendered.replace(/\s{2,}/g, ' ').trim();
};

const resolveNumberInput = (value: string, fallback: number): number => {
    const parsed = Number(value);

    if (Number.isNaN(parsed)) {
        return fallback;
    }

    return parsed > 0 ? parsed : fallback;
};

const resolveFontValue = (value: string, fallback: string): string => {
    const trimmed = value.trim();

    return trimmed === '' ? fallback : trimmed;
};

const normalizeFontFamily = (value: unknown): string => {
    if (typeof value === 'string') {
        return value;
    }

    if (!value || typeof value !== 'object') {
        return '';
    }

    const record = value as Record<string, unknown>;
    const candidate =
        typeof record.family === 'string'
            ? record.family
            : typeof record.fontFamily === 'string'
              ? record.fontFamily
              : typeof record.name === 'string'
                ? record.name
                : '';

    return candidate;
};

export default function OrganizationSettings() {
    const branding = useBranding();
    const [logoPreset, setLogoPreset] = useState<LogoPreset>(
        branding.logoPreset,
    );
    const [companyNameValue, setCompanyNameValue] = useState(
        branding.companyName,
    );
    const [portalLabelValue, setPortalLabelValue] = useState(
        branding.portalLabel,
    );
    const [reportHeaderTitleValue, setReportHeaderTitleValue] = useState(
        branding.reportHeader.title ?? '',
    );
    const [reportHeaderTaglineValue, setReportHeaderTaglineValue] = useState(
        branding.reportHeader.tagline ?? '',
    );
    const [reportHeaderShowLogo, setReportHeaderShowLogo] = useState(
        branding.reportHeader.showLogo,
    );
    const [reportHeaderShowCompanyName, setReportHeaderShowCompanyName] =
        useState(branding.reportHeader.showCompanyName);
    const [reportHeaderAlignment, setReportHeaderAlignment] =
        useState<ReportHeaderAlignment>(
            branding.reportHeader.alignment ?? 'center',
        );
    const [reportHeaderTitleFontFamily, setReportHeaderTitleFontFamily] =
        useState(branding.reportTypography.headerTitle.family);
    const [reportHeaderTitleFontVariant, setReportHeaderTitleFontVariant] =
        useState(branding.reportTypography.headerTitle.variant);
    const [reportHeaderTitleFontWeight, setReportHeaderTitleFontWeight] =
        useState(String(branding.reportTypography.headerTitle.weight));
    const [reportHeaderTitleFontSize, setReportHeaderTitleFontSize] = useState(
        String(branding.reportTypography.headerTitle.size),
    );
    const [reportHeaderTaglineFontFamily, setReportHeaderTaglineFontFamily] =
        useState(branding.reportTypography.headerTagline.family);
    const [reportHeaderTaglineFontVariant, setReportHeaderTaglineFontVariant] =
        useState(branding.reportTypography.headerTagline.variant);
    const [reportHeaderTaglineFontWeight, setReportHeaderTaglineFontWeight] =
        useState(String(branding.reportTypography.headerTagline.weight));
    const [reportHeaderTaglineFontSize, setReportHeaderTaglineFontSize] =
        useState(String(branding.reportTypography.headerTagline.size));
    const [reportLabelFontFamily, setReportLabelFontFamily] = useState(
        branding.reportTypography.label.family,
    );
    const [reportLabelFontVariant, setReportLabelFontVariant] = useState(
        branding.reportTypography.label.variant,
    );
    const [reportLabelFontWeight, setReportLabelFontWeight] = useState(
        String(branding.reportTypography.label.weight),
    );
    const [reportLabelFontSize, setReportLabelFontSize] = useState(
        String(branding.reportTypography.label.size),
    );
    const [reportValueFontFamily, setReportValueFontFamily] = useState(
        branding.reportTypography.value.family,
    );
    const [reportValueFontVariant, setReportValueFontVariant] = useState(
        branding.reportTypography.value.variant,
    );
    const [reportValueFontWeight, setReportValueFontWeight] = useState(
        String(branding.reportTypography.value.weight),
    );
    const [reportValueFontSize, setReportValueFontSize] = useState(
        String(branding.reportTypography.value.size),
    );
    const logoMarkInputRef = useRef<HTMLInputElement>(null);
    const logoFullInputRef = useRef<HTMLInputElement>(null);
    const [logoMarkPreview, setLogoMarkPreview] = useState<string | null>(null);
    const [logoFullPreview, setLogoFullPreview] = useState<string | null>(null);
    const [logoMarkReset, setLogoMarkReset] = useState(false);
    const [logoFullReset, setLogoFullReset] = useState(false);
    const faviconInputRef = useRef<HTMLInputElement>(null);
    const [faviconPreview, setFaviconPreview] = useState<string | null>(null);
    const [faviconReset, setFaviconReset] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);
    const [brandPrimaryValue, setBrandPrimaryValue] = useState(() =>
        normalizeHexInputValue(branding.brandPrimaryColor),
    );
    const [brandPrimaryTouched, setBrandPrimaryTouched] = useState(false);
    const [brandAccentValue, setBrandAccentValue] = useState(() =>
        normalizeHexInputValue(branding.brandAccentColor),
    );
    const [brandAccentTouched, setBrandAccentTouched] = useState(false);
    const [reportHeaderColorValue, setReportHeaderColorValue] = useState(() =>
        normalizeHexInputValue(branding.reportTypography.headerTitle.color),
    );
    const [reportHeaderColorTouched, setReportHeaderColorTouched] =
        useState(false);
    const [reportHeaderTaglineColorValue, setReportHeaderTaglineColorValue] =
        useState(() =>
            normalizeHexInputValue(
                branding.reportTypography.headerTagline.color,
            ),
        );
    const [reportHeaderTaglineColorTouched, setReportHeaderTaglineColorTouched] =
        useState(false);
    const [reportLabelColorValue, setReportLabelColorValue] = useState(() =>
        normalizeHexInputValue(branding.reportTypography.label.color),
    );
    const [reportLabelColorTouched, setReportLabelColorTouched] =
        useState(false);
    const [reportValueColorValue, setReportValueColorValue] = useState(() =>
        normalizeHexInputValue(branding.reportTypography.value.color),
    );
    const [reportValueColorTouched, setReportValueColorTouched] =
        useState(false);
    const [loanSmsApprovedTemplate, setLoanSmsApprovedTemplate] = useState(() =>
        branding.communications?.loanSmsTemplates?.approved
            ? branding.communications.loanSmsTemplates.approved
            : DEFAULT_LOAN_SMS_APPROVED_TEMPLATE,
    );
    const [loanSmsDeclinedTemplate, setLoanSmsDeclinedTemplate] = useState(() =>
        branding.communications?.loanSmsTemplates?.declined
            ? branding.communications.loanSmsTemplates.declined
            : DEFAULT_LOAN_SMS_DECLINED_TEMPLATE,
    );
    const primaryInputValue = brandPrimaryTouched
        ? brandPrimaryValue
        : normalizeHexInputValue(branding.brandPrimaryColor);
    const accentInputValue = brandAccentTouched
        ? brandAccentValue
        : normalizeHexInputValue(branding.brandAccentColor);
    const reportHeaderColorInputValue = reportHeaderColorTouched
        ? reportHeaderColorValue
        : normalizeHexInputValue(branding.reportTypography.headerTitle.color);
    const reportHeaderTaglineColorInputValue = reportHeaderTaglineColorTouched
        ? reportHeaderTaglineColorValue
        : normalizeHexInputValue(branding.reportTypography.headerTagline.color);
    const reportLabelColorInputValue = reportLabelColorTouched
        ? reportLabelColorValue
        : normalizeHexInputValue(branding.reportTypography.label.color);
    const reportValueColorInputValue = reportValueColorTouched
        ? reportValueColorValue
        : normalizeHexInputValue(branding.reportTypography.value.color);
    const normalizedPrimary = normalizeHexValue(primaryInputValue);
    const normalizedAccent = normalizeHexValue(accentInputValue);
    const normalizedReportHeaderColor = normalizeHexValue(
        reportHeaderColorInputValue,
    );
    const normalizedReportHeaderTaglineColor = normalizeHexValue(
        reportHeaderTaglineColorInputValue,
    );
    const normalizedReportLabelColor = normalizeHexValue(
        reportLabelColorInputValue,
    );
    const normalizedReportValueColor = normalizeHexValue(
        reportValueColorInputValue,
    );
    const primarySwatch = normalizedPrimary ?? DEFAULT_BRAND_PRIMARY;
    const accentSwatch = normalizedAccent ?? DEFAULT_BRAND_ACCENT;
    const reportHeaderColorSwatch =
        normalizedReportHeaderColor ?? DEFAULT_REPORT_HEADER_COLOR;
    const reportHeaderTaglineColorSwatch =
        normalizedReportHeaderTaglineColor ??
        normalizedReportHeaderColor ??
        DEFAULT_REPORT_HEADER_COLOR;
    const reportLabelColorSwatch =
        normalizedReportLabelColor ?? DEFAULT_REPORT_LABEL_COLOR;
    const reportValueColorSwatch =
        normalizedReportValueColor ?? DEFAULT_REPORT_VALUE_COLOR;
    const brandPrimaryHelpId = 'brand_primary_color_help';
    const brandAccentHelpId = 'brand_accent_color_help';
    const reportHeaderColorHelpId = 'report_header_font_color_help';
    const reportHeaderTaglineColorHelpId = 'report_header_tagline_color_help';
    const reportLabelColorHelpId = 'report_label_font_color_help';
    const reportValueColorHelpId = 'report_value_font_color_help';
    const logoMarkPreviewUrl =
        logoMarkPreview ??
        (logoMarkReset ? branding.logoMarkDefaultUrl : branding.logoMarkUrl);
    const logoFullPreviewUrl =
        logoFullPreview ??
        (logoFullReset ? branding.logoFullDefaultUrl : branding.logoFullUrl);
    const logoPreviewUrl =
        logoPreset === 'full' ? logoFullPreviewUrl : logoMarkPreviewUrl;
    const logoPresetLabel =
        LOGO_PRESET_OPTIONS.find((option) => option.value === logoPreset)
            ?.label ?? 'Logo preset';
    const showCompanyNamePreview = logoPreset !== 'full';
    const companyNamePreview =
        companyNameValue.trim() !== ''
            ? companyNameValue.trim()
            : branding.companyName;
    const portalLabelPreview =
        portalLabelValue.trim() !== ''
            ? portalLabelValue.trim()
            : branding.portalLabel;
    const portalLabelForMessage = resolvePortalLabelForMessage(
        companyNamePreview,
        portalLabelPreview,
    );
    const appTitlePreview = resolveAppTitlePreview(
        companyNamePreview,
        portalLabelPreview,
    );
    const messagePrefixPreview = resolveMessagePrefix(
        companyNamePreview,
        portalLabelPreview,
    );
    const officeNamePreview = resolveOfficeName(
        companyNamePreview,
        portalLabelPreview,
    );
    const loanSmsApprovedTemplateValue =
        loanSmsApprovedTemplate.trim() !== ''
            ? loanSmsApprovedTemplate
            : DEFAULT_LOAN_SMS_APPROVED_TEMPLATE;
    const loanSmsDeclinedTemplateValue =
        loanSmsDeclinedTemplate.trim() !== ''
            ? loanSmsDeclinedTemplate
            : DEFAULT_LOAN_SMS_DECLINED_TEMPLATE;
    const loanSmsPreviewReplacements = {
        '{company_name}': companyNamePreview,
        '{portal_label}': portalLabelForMessage,
        '{message_prefix}': messagePrefixPreview,
        '{office_name}': officeNamePreview,
        '{loan_reference}': 'LNREQ-000001',
        '{approved_amount}': 'Php. 100,000.00',
        '{approved_term}': '12',
    };
    const loanSmsApprovedPreview = renderLoanSmsTemplate(
        loanSmsApprovedTemplateValue,
        loanSmsPreviewReplacements,
    );
    const loanSmsDeclinedPreview = renderLoanSmsTemplate(
        loanSmsDeclinedTemplateValue,
        loanSmsPreviewReplacements,
    );
    const faviconPreviewUrl =
        faviconPreview ??
        (faviconReset ? branding.faviconDefaultUrl : branding.faviconUrl);
    const logoMarkIsDefault =
        logoMarkReset || (!logoMarkPreview && branding.logoMarkIsDefault);
    const logoFullIsDefault =
        logoFullReset || (!logoFullPreview && branding.logoFullIsDefault);
    const hasStoredFavicon = branding.faviconPath !== null;
    const reportHeaderTitlePreview =
        reportHeaderTitleValue.trim() !== ''
            ? reportHeaderTitleValue.trim()
            : DEFAULT_REPORT_HEADER_TITLE;
    const reportHeaderTaglinePreview = reportHeaderTaglineValue.trim();
    const reportShowCompanyNamePreview =
        reportHeaderShowCompanyName && logoPreset !== 'full';
    const reportHeaderAlignmentClass =
        reportHeaderAlignment === 'left'
            ? 'justify-start'
            : reportHeaderAlignment === 'right'
              ? 'justify-end'
              : 'justify-center';
    const reportHeaderTextAlignClass =
        reportHeaderAlignment === 'left'
            ? 'text-left'
            : reportHeaderAlignment === 'right'
              ? 'text-right'
              : 'text-center';
    const reportHeaderTitleSize = resolveNumberInput(
        reportHeaderTitleFontSize,
        branding.reportTypography.headerTitle.size,
    );
    const reportHeaderTaglineSize = resolveNumberInput(
        reportHeaderTaglineFontSize,
        branding.reportTypography.headerTagline.size,
    );
    const reportLabelFontSizeValue = resolveNumberInput(
        reportLabelFontSize,
        branding.reportTypography.label.size,
    );
    const reportValueFontSizeValue = resolveNumberInput(
        reportValueFontSize,
        branding.reportTypography.value.size,
    );
    const reportHeaderTitleFontFamilyResolved = resolveFontValue(
        reportHeaderTitleFontFamily,
        branding.reportTypography.headerTitle.family,
    );
    const reportHeaderTaglineFontFamilyResolved = resolveFontValue(
        reportHeaderTaglineFontFamily,
        branding.reportTypography.headerTagline.family,
    );
    const reportLabelFontFamilyResolved = resolveFontValue(
        reportLabelFontFamily,
        branding.reportTypography.label.family,
    );
    const reportValueFontFamilyResolved = resolveFontValue(
        reportValueFontFamily,
        branding.reportTypography.value.family,
    );
    const reportHeaderTitleFontWeightResolved = resolveNumberInput(
        reportHeaderTitleFontWeight,
        branding.reportTypography.headerTitle.weight,
    );
    const reportHeaderTaglineFontWeightResolved = resolveNumberInput(
        reportHeaderTaglineFontWeight,
        branding.reportTypography.headerTagline.weight,
    );
    const reportLabelFontWeightResolved = resolveNumberInput(
        reportLabelFontWeight,
        branding.reportTypography.label.weight,
    );
    const reportValueFontWeightResolved = resolveNumberInput(
        reportValueFontWeight,
        branding.reportTypography.value.weight,
    );
    const reportHeaderColorResolved =
        normalizedReportHeaderColor ?? DEFAULT_REPORT_HEADER_COLOR;
    const reportHeaderTaglineColorResolved =
        normalizedReportHeaderTaglineColor ?? reportHeaderColorResolved;
    const reportLabelColorResolved =
        normalizedReportLabelColor ?? DEFAULT_REPORT_LABEL_COLOR;
    const reportValueColorResolved =
        normalizedReportValueColor ?? DEFAULT_REPORT_VALUE_COLOR;
    const reportHeaderTitleStyle = {
        fontFamily: reportHeaderTitleFontFamilyResolved,
        fontWeight: reportHeaderTitleFontWeightResolved,
        fontStyle:
            reportHeaderTitleFontVariant === 'italic' ? 'italic' : 'normal',
        fontSize: `${reportHeaderTitleSize}px`,
        color: reportHeaderColorResolved,
    };
    const reportHeaderTaglineStyle = {
        fontFamily: reportHeaderTaglineFontFamilyResolved,
        fontWeight: reportHeaderTaglineFontWeightResolved,
        fontStyle:
            reportHeaderTaglineFontVariant === 'italic' ? 'italic' : 'normal',
        fontSize: `${reportHeaderTaglineSize}px`,
        color: reportHeaderTaglineColorResolved,
    };
    const reportLabelStyle = {
        fontFamily: reportLabelFontFamilyResolved,
        fontWeight: reportLabelFontWeightResolved,
        fontStyle: reportLabelFontVariant === 'italic' ? 'italic' : 'normal',
        fontSize: `${reportLabelFontSizeValue}px`,
        color: reportLabelColorResolved,
    };
    const reportValueStyle = {
        fontFamily: reportValueFontFamilyResolved,
        fontWeight: reportValueFontWeightResolved,
        fontStyle: reportValueFontVariant === 'italic' ? 'italic' : 'normal',
        fontSize: `${reportValueFontSizeValue}px`,
        color: reportValueColorResolved,
    };
    const reportCompanyNameStyle = {
        fontFamily: reportHeaderTitleFontFamilyResolved,
        fontWeight: reportHeaderTitleFontWeightResolved,
        fontStyle:
            reportHeaderTitleFontVariant === 'italic' ? 'italic' : 'normal',
        color: reportHeaderColorResolved,
    };

    useEffect(() => {
        if (!faviconPreview) {
            return;
        }

        return () => {
            URL.revokeObjectURL(faviconPreview);
        };
    }, [faviconPreview]);

    useEffect(() => {
        if (!logoMarkPreview) {
            return;
        }

        return () => {
            URL.revokeObjectURL(logoMarkPreview);
        };
    }, [logoMarkPreview]);

    useEffect(() => {
        if (!logoFullPreview) {
            return;
        }

        return () => {
            URL.revokeObjectURL(logoFullPreview);
        };
    }, [logoFullPreview]);

    const handleLogoMarkChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (!LOGO_ALLOWED_TYPES.has(file.type)) {
            showErrorToast(
                null,
                'Please select a JPG, PNG, or WebP image for the logo mark.',
                {
                    id: 'organization-logo-mark-type',
                },
            );
            event.target.value = '';
            return;
        }

        if (file.size > LOGO_MAX_BYTES) {
            showErrorToast(null, 'Logo mark must be 2MB or smaller.', {
                id: 'organization-logo-mark-size',
            });
            event.target.value = '';
            return;
        }

        setLogoMarkReset(false);
        setLogoMarkPreview(URL.createObjectURL(file));
        setHasChanges(true);
    };

    const handleLogoFullChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (!LOGO_ALLOWED_TYPES.has(file.type)) {
            showErrorToast(
                null,
                'Please select a JPG, PNG, or WebP image for the full logo.',
                {
                    id: 'organization-logo-full-type',
                },
            );
            event.target.value = '';
            return;
        }

        if (file.size > LOGO_MAX_BYTES) {
            showErrorToast(null, 'Logo full must be 2MB or smaller.', {
                id: 'organization-logo-full-size',
            });
            event.target.value = '';
            return;
        }

        setLogoFullReset(false);
        setLogoFullPreview(URL.createObjectURL(file));
        setHasChanges(true);
    };

    const clearLogoMarkPreview = () => {
        setLogoMarkPreview(null);
        setHasChanges(true);

        if (logoMarkInputRef.current) {
            logoMarkInputRef.current.value = '';
        }
    };

    const clearLogoFullPreview = () => {
        setLogoFullPreview(null);
        setHasChanges(true);

        if (logoFullInputRef.current) {
            logoFullInputRef.current.value = '';
        }
    };

    const resetLogoMarkToDefault = () => {
        setLogoMarkReset(true);
        setLogoMarkPreview(null);
        setHasChanges(true);

        if (logoMarkInputRef.current) {
            logoMarkInputRef.current.value = '';
        }
    };

    const resetLogoFullToDefault = () => {
        setLogoFullReset(true);
        setLogoFullPreview(null);
        setHasChanges(true);

        if (logoFullInputRef.current) {
            logoFullInputRef.current.value = '';
        }
    };

    const keepCurrentLogoMark = () => {
        setLogoMarkReset(false);
        setLogoMarkPreview(null);
        setHasChanges(true);

        if (logoMarkInputRef.current) {
            logoMarkInputRef.current.value = '';
        }
    };

    const keepCurrentLogoFull = () => {
        setLogoFullReset(false);
        setLogoFullPreview(null);
        setHasChanges(true);

        if (logoFullInputRef.current) {
            logoFullInputRef.current.value = '';
        }
    };

    const handleFaviconChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (!FAVICON_ALLOWED_TYPES.has(file.type)) {
            showErrorToast(
                null,
                'Please select a JPG, PNG, WebP, or ICO image.',
                {
                    id: 'organization-favicon-type',
                },
            );
            event.target.value = '';
            return;
        }

        if (file.size > FAVICON_MAX_BYTES) {
            showErrorToast(null, 'Favicon must be 1MB or smaller.', {
                id: 'organization-favicon-size',
            });
            event.target.value = '';
            return;
        }

        setFaviconReset(false);
        setFaviconPreview(URL.createObjectURL(file));
        setHasChanges(true);
    };

    const clearFaviconPreview = () => {
        setFaviconPreview(null);
        setHasChanges(true);

        if (faviconInputRef.current) {
            faviconInputRef.current.value = '';
        }
    };

    const resetFaviconToDefault = () => {
        setFaviconReset(true);
        setFaviconPreview(null);
        setHasChanges(true);

        if (faviconInputRef.current) {
            faviconInputRef.current.value = '';
        }
    };

    const keepCurrentFavicon = () => {
        setFaviconReset(false);
        setFaviconPreview(null);
        setHasChanges(true);

        if (faviconInputRef.current) {
            faviconInputRef.current.value = '';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization settings" />

            <PageShell size="wide" className="gap-8 pb-16">
                <PageHero
                    kicker="Settings"
                    title="Organization settings"
                    description="Manage company identity, brand assets, report layouts, and member communications."
                    badges={
                        <>
                            <Badge
                                variant="outline"
                                className="text-[10px] uppercase tracking-[0.2em]"
                            >
                                Preset: {logoPresetLabel}
                            </Badge>
                            <Badge
                                variant="outline"
                                className="text-[10px] uppercase tracking-[0.2em]"
                            >
                                Live preview
                            </Badge>
                            {hasChanges ? (
                                <Badge
                                    variant="outline"
                                    className="border-primary/40 text-[10px] uppercase tracking-[0.2em] text-primary"
                                >
                                    Unsaved changes
                                </Badge>
                            ) : (
                                <Badge
                                    variant="secondary"
                                    className="text-[10px] uppercase tracking-[0.2em]"
                                >
                                    Up to date
                                </Badge>
                            )}
                        </>
                    }
                />

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start xl:grid-cols-[minmax(0,1fr)_420px]">
                    <SurfaceCard variant="default" padding="lg" className="space-y-8">
                        <SectionHeader
                            title="Organization settings"
                            description="Update identity, visual assets, report design, and communications sent to members."
                            titleClassName="text-base font-semibold"
                        />
                        <div>
                            <Form
                                {...OrganizationSettingsController.update.form()}
                                options={{ preserveScroll: true }}
                                encType="multipart/form-data"
                                onChange={() => setHasChanges(true)}
                                onSuccess={() => {
                                    showSuccessToast(
                                        adminToastCopy.success.updated(
                                            'Branding',
                                        ),
                                        {
                                            id: 'organization-branding-update',
                                        },
                                    );
                                    setLogoMarkPreview(null);
                                    setLogoFullPreview(null);
                                    setLogoMarkReset(false);
                                    setLogoFullReset(false);
                                    if (logoMarkInputRef.current) {
                                        logoMarkInputRef.current.value = '';
                                    }
                                    if (logoFullInputRef.current) {
                                        logoFullInputRef.current.value = '';
                                    }
                                    clearFaviconPreview();
                                    setFaviconReset(false);
                                    setBrandPrimaryTouched(false);
                                    setBrandAccentTouched(false);
                                    setReportHeaderColorTouched(false);
                                    setReportHeaderTaglineColorTouched(false);
                                    setReportLabelColorTouched(false);
                                    setReportValueColorTouched(false);
                                    setHasChanges(false);
                                }}
                                onError={(formErrors) => {
                                    showErrorToast(
                                        formErrors,
                                        adminToastCopy.error.updated(
                                            'branding',
                                        ),
                                        { id: 'organization-branding-update' },
                                    );
                                }}
                                className="space-y-8"
                            >
                                {({
                                    processing,
                                    recentlySuccessful,
                                    errors: formErrors,
                                }) => {
                                    const primaryClientError =
                                        brandPrimaryTouched &&
                                        primaryInputValue.trim() !== '' &&
                                        normalizedPrimary === null
                                            ? PRIMARY_COLOR_ERROR
                                            : undefined;
                                    const accentClientError =
                                        brandAccentTouched &&
                                        accentInputValue.trim() !== '' &&
                                        normalizedAccent === null
                                            ? ACCENT_COLOR_ERROR
                                            : undefined;
                                    const reportHeaderColorClientError =
                                        reportHeaderColorTouched &&
                                        reportHeaderColorInputValue.trim() !==
                                            '' &&
                                        normalizedReportHeaderColor === null
                                            ? REPORT_HEADER_COLOR_ERROR
                                            : undefined;
                                    const reportHeaderTaglineColorClientError =
                                        reportHeaderTaglineColorTouched &&
                                        reportHeaderTaglineColorInputValue.trim() !==
                                            '' &&
                                        normalizedReportHeaderTaglineColor ===
                                            null
                                            ? REPORT_TAGLINE_COLOR_ERROR
                                            : undefined;
                                    const reportLabelColorClientError =
                                        reportLabelColorTouched &&
                                        reportLabelColorInputValue.trim() !==
                                            '' &&
                                        normalizedReportLabelColor === null
                                            ? REPORT_LABEL_COLOR_ERROR
                                            : undefined;
                                    const reportValueColorClientError =
                                        reportValueColorTouched &&
                                        reportValueColorInputValue.trim() !==
                                            '' &&
                                        normalizedReportValueColor === null
                                            ? REPORT_VALUE_COLOR_ERROR
                                            : undefined;
                                    const primaryError =
                                        formErrors.brand_primary_color ??
                                        primaryClientError;
                                    const accentError =
                                        formErrors.brand_accent_color ??
                                        accentClientError;
                                    const reportHeaderColorError =
                                        formErrors.report_header_font_color ??
                                        reportHeaderColorClientError;
                                    const reportHeaderTaglineColorError =
                                        formErrors.report_header_tagline_color ??
                                        reportHeaderTaglineColorClientError;
                                    const reportLabelColorError =
                                        formErrors.report_label_font_color ??
                                        reportLabelColorClientError;
                                    const reportValueColorError =
                                        formErrors.report_value_font_color ??
                                        reportValueColorClientError;
                                    const primaryInvalid =
                                        primaryError !== undefined;
                                    const accentInvalid =
                                        accentError !== undefined;
                                    const reportHeaderColorInvalid =
                                        reportHeaderColorError !== undefined;
                                    const reportHeaderTaglineColorInvalid =
                                        reportHeaderTaglineColorError !==
                                        undefined;
                                    const reportLabelColorInvalid =
                                        reportLabelColorError !== undefined;
                                    const reportValueColorInvalid =
                                        reportValueColorError !== undefined;

                                    return (
                                        <>
                                            <Tabs
                                                defaultValue="general"
                                                className="flex flex-col gap-6"
                                            >
                                                <TabsList className="w-full flex-wrap justify-start gap-2">
                                                    <TabsTrigger value="general">
                                                        General
                                                    </TabsTrigger>
                                                    <TabsTrigger value="brand-assets">
                                                        Brand assets
                                                    </TabsTrigger>
                                                    <TabsTrigger value="colors">
                                                        Colors
                                                    </TabsTrigger>
                                                    <TabsTrigger value="report-header">
                                                        Report header
                                                    </TabsTrigger>
                                                    <TabsTrigger value="support">
                                                        Support
                                                    </TabsTrigger>
                                                </TabsList>
                                                <TabsContent
                                                    value="general"
                                                    className="mt-0"
                                                >
                                                    <SurfaceCard
                                                variant="muted"
                                                padding="md"
                                                className="space-y-6"
                                            >
                                                <div className="space-y-1">
                                                    <h3 className="text-base font-semibold tracking-tight">
                                                        General
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        Company name, portal
                                                        label, and the app title
                                                        shown to members.
                                                    </p>
                                                </div>

                                                <div className="grid gap-6 md:grid-cols-2">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="company_name">
                                                            Company name
                                                        </Label>
                                                        <Input
                                                            id="company_name"
                                                            name="company_name"
                                                            value={
                                                                companyNameValue
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setCompanyNameValue(
                                                                    event.target
                                                                        .value,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                            }}
                                                            placeholder="Company name"
                                                        />
                                                        <InputError
                                                            message={
                                                                formErrors.company_name
                                                            }
                                                        />
                                                    </div>

                                                    <div className="grid gap-2">
                                                        <Label htmlFor="portal_label">
                                                            Portal label
                                                        </Label>
                                                        <Input
                                                            id="portal_label"
                                                            name="portal_label"
                                                            value={
                                                                portalLabelValue
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setPortalLabelValue(
                                                                    event.target
                                                                        .value,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                            }}
                                                            placeholder="Member Portal"
                                                        />
                                                        <InputError
                                                            message={
                                                                formErrors.portal_label
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2 md:col-span-2">
                                                        <Label>
                                                            App title preview
                                                        </Label>
                                                        <div className="rounded-lg border border-border/40 bg-background/70 px-3 py-2 text-sm text-muted-foreground">
                                                            {appTitlePreview ||
                                                                '--'}
                                                        </div>
                                                    </div>
                                                </div>
                                            </SurfaceCard>
                                                </TabsContent>

                                                <TabsContent
                                                    value="brand-assets"
                                                    className="mt-0"
                                                >
                                                    <SurfaceCard
                                                variant="muted"
                                                padding="md"
                                                className="space-y-6"
                                            >
                                                <div className="space-y-1">
                                                    <h3 className="text-base font-semibold tracking-tight">
                                                        Brand assets
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        Choose the primary logo
                                                        and portal icon used
                                                        throughout the member
                                                        experience.
                                                    </p>
                                                </div>

                                                <div className="grid gap-6">
                                                    <div className="space-y-4 rounded-2xl border border-border/30 bg-background/60 p-4">
                                                        <Label className="text-sm font-semibold">
                                                            Primary logo
                                                        </Label>
                                                        <div
                                                            role="radiogroup"
                                                            aria-label="Primary logo selection"
                                                            className="grid gap-4 md:grid-cols-2"
                                                        >
                                                            {LOGO_PRESET_OPTIONS.map(
                                                                (option) => {
                                                                    const isSelected =
                                                                        logoPreset ===
                                                                        option.value;
                                                                    const isMark =
                                                                        option.value ===
                                                                        'mark';
                                                                    const previewUrl =
                                                                        isMark
                                                                            ? logoMarkPreviewUrl
                                                                            : logoFullPreviewUrl;
                                                                    const isDefault =
                                                                        isMark
                                                                            ? logoMarkIsDefault
                                                                            : logoFullIsDefault;
                                                                    const isReset =
                                                                        isMark
                                                                            ? logoMarkReset
                                                                            : logoFullReset;
                                                                    const hasPreview =
                                                                        isMark
                                                                            ? Boolean(
                                                                                  logoMarkPreview,
                                                                              )
                                                                            : Boolean(
                                                                                  logoFullPreview,
                                                                              );
                                                                    const optionId = `logo-preset-${option.value}`;

                                                                return (
                                                                    <div
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        className={`group flex flex-col gap-4 rounded-2xl border p-5 transition-colors focus-within:ring-2 focus-within:ring-primary/40 focus-within:outline-none ${
                                                                            isSelected
                                                                                ? 'border-primary/60 bg-primary/5 shadow-sm shadow-primary/10'
                                                                                : 'border-border/40 bg-card/50 hover:border-primary/40 hover:bg-muted/30'
                                                                        }`}
                                                                    >
                                                                        <input
                                                                            id={
                                                                                optionId
                                                                            }
                                                                            type="radio"
                                                                            name="logo_preset"
                                                                            value={
                                                                                option.value
                                                                            }
                                                                            checked={
                                                                                isSelected
                                                                            }
                                                                            onChange={() => {
                                                                                setLogoPreset(
                                                                                    option.value,
                                                                                );
                                                                                setHasChanges(
                                                                                    true,
                                                                                );
                                                                            }}
                                                                            className="sr-only"
                                                                        />
                                                                        <label
                                                                            htmlFor={
                                                                                optionId
                                                                            }
                                                                            className="grid cursor-pointer gap-4"
                                                                        >
                                                                            <div className="flex items-start justify-between gap-3">
                                                                                <div className="space-y-1">
                                                                                    <p className="text-sm font-semibold">
                                                                                        {
                                                                                            option.label
                                                                                        }
                                                                                    </p>
                                                                                    <p className="text-xs text-muted-foreground">
                                                                                        {
                                                                                            option.description
                                                                                        }
                                                                                    </p>
                                                                                </div>
                                                                                {isSelected ? (
                                                                                    <Badge
                                                                                        variant="secondary"
                                                                                        className="text-[10px] uppercase tracking-[0.2em]"
                                                                                    >
                                                                                        Selected
                                                                                    </Badge>
                                                                                ) : (
                                                                                    <Badge
                                                                                        variant="outline"
                                                                                        className="text-[10px] uppercase tracking-[0.2em]"
                                                                                    >
                                                                                        Select
                                                                                    </Badge>
                                                                                )}
                                                                            </div>
                                                                            <div className="flex h-20 items-center justify-center rounded-xl border border-border/40 bg-muted/30">
                                                                                <img
                                                                                    src={
                                                                                        previewUrl
                                                                                    }
                                                                                    alt={`${branding.appTitle} ${option.label}`}
                                                                                    className={`w-auto object-contain ${
                                                                                        option.value ===
                                                                                        'full'
                                                                                            ? 'h-14'
                                                                                            : 'h-12'
                                                                                    }`}
                                                                                />
                                                                            </div>
                                                                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                                                <Badge
                                                                                    variant={
                                                                                        isDefault
                                                                                            ? 'secondary'
                                                                                            : 'outline'
                                                                                    }
                                                                                    className="text-[10px] uppercase tracking-[0.2em]"
                                                                                >
                                                                                    {isDefault
                                                                                        ? 'Default asset'
                                                                                        : 'Custom asset'}
                                                                                </Badge>
                                                                                {isReset ? (
                                                                                    <Badge
                                                                                        variant="outline"
                                                                                        className="border-primary/40 text-[10px] uppercase tracking-[0.2em] text-primary"
                                                                                    >
                                                                                        Reset after save
                                                                                    </Badge>
                                                                                ) : null}
                                                                            </div>
                                                                        </label>
                                                                        <div className="flex flex-wrap gap-2">
                                                                            <Button
                                                                                type="button"
                                                                                    variant="outline"
                                                                                    size="sm"
                                                                                    onClick={(
                                                                                        event,
                                                                                    ) => {
                                                                                        event.preventDefault();
                                                                                        event.stopPropagation();
                                                                                        if (
                                                                                            isMark
                                                                                        ) {
                                                                                            logoMarkInputRef.current?.click();
                                                                                        } else {
                                                                                            logoFullInputRef.current?.click();
                                                                                        }
                                                                                    }}
                                                                                >
                                                                                    {isMark
                                                                                        ? 'Change logo mark'
                                                                                        : 'Change logo full'}
                                                                                </Button>
                                                                                {hasPreview ? (
                                                                                    <Button
                                                                                        type="button"
                                                                                        variant="ghost"
                                                                                        size="sm"
                                                                                        onClick={(
                                                                                            event,
                                                                                        ) => {
                                                                                            event.preventDefault();
                                                                                            event.stopPropagation();
                                                                                            if (
                                                                                                isMark
                                                                                            ) {
                                                                                                clearLogoMarkPreview();
                                                                                            } else {
                                                                                                clearLogoFullPreview();
                                                                                            }
                                                                                        }}
                                                                                    >
                                                                                        Remove
                                                                                        selection
                                                                                    </Button>
                                                                                ) : null}
                                                                                {!isDefault ||
                                                                                isReset ? (
                                                                                    <Button
                                                                                        type="button"
                                                                                        variant="ghost"
                                                                                        size="sm"
                                                                                        onClick={(
                                                                                            event,
                                                                                        ) => {
                                                                                            event.preventDefault();
                                                                                            event.stopPropagation();
                                                                                            if (
                                                                                                isMark
                                                                                            ) {
                                                                                                if (
                                                                                                    isReset
                                                                                                ) {
                                                                                                    keepCurrentLogoMark();
                                                                                                } else {
                                                                                                    resetLogoMarkToDefault();
                                                                                                }
                                                                                            } else if (
                                                                                                isReset
                                                                                            ) {
                                                                                                keepCurrentLogoFull();
                                                                                            } else {
                                                                                                resetLogoFullToDefault();
                                                                                            }
                                                                                        }}
                                                                                    >
                                                                                        {isReset
                                                                                            ? 'Keep current asset'
                                                                                            : 'Reset to default'}
                                                                                    </Button>
                                                                                ) : null}
                                                                            </div>
                                                                            <input
                                                                                ref={
                                                                                    isMark
                                                                                        ? logoMarkInputRef
                                                                                        : logoFullInputRef
                                                                                }
                                                                                type="file"
                                                                                name={
                                                                                    isMark
                                                                                        ? 'logo_mark'
                                                                                        : 'logo_full'
                                                                                }
                                                                                accept="image/png,image/jpeg,image/webp"
                                                                                aria-label={
                                                                                    isMark
                                                                                        ? 'Upload logo mark'
                                                                                        : 'Upload full logo'
                                                                                }
                                                                                className="sr-only"
                                                                                onChange={
                                                                                    isMark
                                                                                        ? handleLogoMarkChange
                                                                                        : handleLogoFullChange
                                                                                }
                                                                            />
                                                                            {isMark &&
                                                                            logoMarkReset ? (
                                                                                <input
                                                                                    type="hidden"
                                                                                    name="logo_mark_reset"
                                                                                    value="1"
                                                                                />
                                                                            ) : null}
                                                                            {!isMark &&
                                                                            logoFullReset ? (
                                                                                <input
                                                                                    type="hidden"
                                                                                    name="logo_full_reset"
                                                                                    value="1"
                                                                                />
                                                                            ) : null}
                                                                            <InputError
                                                                                message={
                                                                                    isMark
                                                                                        ? formErrors.logo_mark
                                                                                        : formErrors.logo_full
                                                                                }
                                                                            />
                                                                        </div>
                                                                    );
                                                                },
                                                            )}
                                                        </div>
                                                        <p className="text-sm text-muted-foreground">
                                                            The selected logo
                                                            appears on member
                                                            forms, navigation,
                                                            and reports.
                                                        </p>
                                                        <InputError
                                                            message={
                                                                formErrors.logo_preset
                                                            }
                                                        />
                                                    </div>

                                                    <div className="space-y-4 rounded-2xl border border-border/30 bg-background/60 p-4">
                                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                                            <div className="space-y-1">
                                                                <Label htmlFor="favicon">
                                                                    Portal icon
                                                                </Label>
                                                                <p className="text-sm text-muted-foreground">
                                                                    Used in
                                                                    browser tabs
                                                                    and compact
                                                                    app
                                                                    surfaces.
                                                                </p>
                                                            </div>
                                                            {faviconReset ? (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="text-[10px] uppercase tracking-[0.2em]"
                                                                >
                                                                    Default
                                                                </Badge>
                                                            ) : hasStoredFavicon ||
                                                              faviconPreview ? (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="text-[10px] uppercase tracking-[0.2em]"
                                                                >
                                                                    Custom
                                                                </Badge>
                                                            ) : null}
                                                        </div>
                                                        <div className="rounded-2xl border border-border/30 bg-muted/20 p-4">
                                                            <div className="flex flex-wrap items-center justify-between gap-4">
                                                                <div className="flex flex-wrap items-center gap-4">
                                                                    <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-border/60 bg-background">
                                                                        <img
                                                                            src={
                                                                                faviconPreviewUrl
                                                                            }
                                                                            alt={`${branding.appTitle} icon`}
                                                                            className="h-7 w-7 object-contain"
                                                                        />
                                                                    </div>
                                                                    <div className="flex items-center gap-2">
                                                                        {ICON_PREVIEW_SIZES.map(
                                                                            (
                                                                                size,
                                                                            ) => (
                                                                                <div
                                                                                    key={
                                                                                        size
                                                                                    }
                                                                                    className="flex h-9 w-9 items-center justify-center rounded-lg border border-border/60 bg-background"
                                                                                >
                                                                                    <img
                                                                                        src={
                                                                                            faviconPreviewUrl
                                                                                        }
                                                                                        alt={`${branding.appTitle} ${size}px`}
                                                                                        className="object-contain"
                                                                                        style={{
                                                                                            width: size,
                                                                                            height: size,
                                                                                        }}
                                                                                    />
                                                                                </div>
                                                                            ),
                                                                        )}
                                                                    </div>
                                                                </div>
                                                                <div className="flex flex-wrap gap-2">
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            faviconInputRef.current?.click()
                                                                        }
                                                                    >
                                                                        Upload
                                                                        icon
                                                                    </Button>
                                                                    {faviconPreview ? (
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            onClick={
                                                                                clearFaviconPreview
                                                                            }
                                                                        >
                                                                            Remove
                                                                            selection
                                                                        </Button>
                                                                    ) : null}
                                                                    {hasStoredFavicon ||
                                                                    faviconReset ? (
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            onClick={
                                                                                faviconReset
                                                                                    ? keepCurrentFavicon
                                                                                    : resetFaviconToDefault
                                                                            }
                                                                        >
                                                                            {faviconReset
                                                                                ? 'Keep current icon'
                                                                                : 'Reset to default'}
                                                                        </Button>
                                                                    ) : null}
                                                                </div>
                                                            </div>
                                                            <p className="mt-3 text-xs text-muted-foreground">
                                                                Upload a JPG,
                                                                PNG, WebP, or
                                                                ICO image (max
                                                                1MB).
                                                            </p>
                                                        </div>
                                                        {faviconReset ? (
                                                            <p className="text-xs text-primary">
                                                                Default icon
                                                                will be used
                                                                after saving.
                                                            </p>
                                                        ) : null}
                                                        <input
                                                            id="favicon"
                                                            ref={
                                                                faviconInputRef
                                                            }
                                                            name="favicon"
                                                            type="file"
                                                            accept="image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon"
                                                            aria-label="Upload portal icon"
                                                            className="sr-only"
                                                            onChange={
                                                                handleFaviconChange
                                                            }
                                                        />
                                                        {faviconReset ? (
                                                            <input
                                                                type="hidden"
                                                                name="favicon_reset"
                                                                value="1"
                                                            />
                                                        ) : null}
                                                        <InputError
                                                            message={
                                                                formErrors.favicon
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            </SurfaceCard>
                                                </TabsContent>

                                                <TabsContent
                                                    value="report-header"
                                                    className="mt-0"
                                                >
                                                    <SurfaceCard
                                                variant="muted"
                                                padding="md"
                                                className="space-y-6"
                                            >
                                                <div className="space-y-1">
                                                    <h3 className="text-base font-semibold tracking-tight">
                                                        Reports &amp; documents
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        Control report header
                                                        content, layout, and the
                                                        typography used in PDF
                                                        documents.
                                                    </p>
                                                </div>

                                                <div className="space-y-1">
                                                    <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                        Header content
                                                    </p>
                                                </div>

                                                <div className="grid gap-6">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="report_header_title">
                                                            Header title
                                                        </Label>
                                                        <Input
                                                            id="report_header_title"
                                                            name="report_header_title"
                                                            value={
                                                                reportHeaderTitleValue
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportHeaderTitleValue(
                                                                    event.target
                                                                        .value,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                            }}
                                                            placeholder={
                                                                DEFAULT_REPORT_HEADER_TITLE
                                                            }
                                                        />
                                                        <InputError
                                                            message={
                                                                formErrors.report_header_title
                                                            }
                                                        />
                                                    </div>

                                                    <div className="grid gap-2">
                                                        <Label htmlFor="report_header_tagline">
                                                            Header tagline
                                                        </Label>
                                                        <Input
                                                            id="report_header_tagline"
                                                            name="report_header_tagline"
                                                            value={
                                                                reportHeaderTaglineValue
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportHeaderTaglineValue(
                                                                    event.target
                                                                        .value,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                            }}
                                                            placeholder="Optional tagline"
                                                        />
                                                        <InputError
                                                            message={
                                                                formErrors.report_header_tagline
                                                            }
                                                        />
                                                    </div>

                                                    <div className="rounded-2xl border border-border/30 bg-background/60 p-4">
                                                        <div className="flex flex-wrap items-center gap-6">
                                                            <div className="flex items-center gap-2">
                                                                <Checkbox
                                                                    id="report_header_show_logo_toggle"
                                                                    checked={
                                                                        reportHeaderShowLogo
                                                                    }
                                                                    onCheckedChange={(
                                                                        checked,
                                                                    ) => {
                                                                        setReportHeaderShowLogo(
                                                                            Boolean(
                                                                                checked,
                                                                            ),
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                />
                                                                <Label htmlFor="report_header_show_logo_toggle">
                                                                    Show logo
                                                                </Label>
                                                                <input
                                                                    type="hidden"
                                                                    name="report_header_show_logo"
                                                                    value={
                                                                        reportHeaderShowLogo
                                                                            ? '1'
                                                                            : '0'
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="flex items-center gap-2">
                                                                <Checkbox
                                                                    id="report_header_show_company_name_toggle"
                                                                    checked={
                                                                        reportHeaderShowCompanyName
                                                                    }
                                                                    onCheckedChange={(
                                                                        checked,
                                                                    ) => {
                                                                        setReportHeaderShowCompanyName(
                                                                            Boolean(
                                                                                checked,
                                                                            ),
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                />
                                                                <Label htmlFor="report_header_show_company_name_toggle">
                                                                    Show company
                                                                    name
                                                                </Label>
                                                                <input
                                                                    type="hidden"
                                                                    name="report_header_show_company_name"
                                                                    value={
                                                                        reportHeaderShowCompanyName
                                                                            ? '1'
                                                                            : '0'
                                                                    }
                                                                />
                                                            </div>
                                                        </div>

                                                        <div className="mt-4 grid gap-2 max-w-55">
                                                            <Label htmlFor="report_header_alignment">
                                                                Header alignment
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    reportHeaderAlignment
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) => {
                                                                    setReportHeaderAlignment(
                                                                        value as ReportHeaderAlignment,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                }}
                                                            >
                                                                <SelectTrigger id="report_header_alignment">
                                                                    <SelectValue placeholder="Select alignment" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {REPORT_HEADER_ALIGNMENT_OPTIONS.map(
                                                                        (
                                                                            option,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    option.value
                                                                                }
                                                                                value={
                                                                                    option.value
                                                                                }
                                                                            >
                                                                                {
                                                                                    option.label
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                            <input
                                                                type="hidden"
                                                                name="report_header_alignment"
                                                                value={
                                                                    reportHeaderAlignment
                                                                }
                                                            />
                                                            <InputError
                                                                message={
                                                                    formErrors.report_header_alignment
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                  </div>

                                                <div className="space-y-1">
                                                    <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                        Header typography
                                                    </p>
                                                </div>

                                                <div className="grid gap-6 lg:grid-cols-2">
                                                    <div className="space-y-4 rounded-xl border border-border/60 bg-muted/30 p-4">
                                                        <div className="space-y-1">
                                                            <p className="text-sm font-semibold">
                                                                Title font
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                Applies to the
                                                                main report
                                                                title.
                                                            </p>
                                                        </div>
                                                        <FontPicker
                                                            defaultValue={
                                                                reportHeaderTitleFontFamilyResolved
                                                            }
                                                            inputId="report-title-font"
                                                            loadFonts={
                                                                reportHeaderTitleFontFamilyResolved
                                                            }
                                                            autoLoad
                                                            mode="combo"
                                                            value={(
                                                                nextFont,
                                                            ) => {
                                                                setReportHeaderTitleFontFamily(
                                                                    normalizeFontFamily(
                                                                        nextFont,
                                                                    ),
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                            }}
                                                        />
                                                        <div className="grid gap-3 sm:grid-cols-3">
                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Weight
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        reportHeaderTitleFontWeight ||
                                                                        undefined
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) => {
                                                                        setReportHeaderTitleFontWeight(
                                                                            value,
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue placeholder="Weight" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {REPORT_FONT_WEIGHT_OPTIONS.map(
                                                                            (
                                                                                option,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        option.value
                                                                                    }
                                                                                    value={
                                                                                        option.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        option.label
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Style
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        reportHeaderTitleFontVariant ||
                                                                        undefined
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) => {
                                                                        setReportHeaderTitleFontVariant(
                                                                            value,
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue placeholder="Style" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {REPORT_FONT_STYLE_OPTIONS.map(
                                                                            (
                                                                                option,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        option.value
                                                                                    }
                                                                                    value={
                                                                                        option.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        option.label
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="report_header_title_font_size">
                                                                    Size
                                                                </Label>
                                                                <Input
                                                                    id="report_header_title_font_size"
                                                                    name="report_header_title_font_size"
                                                                    type="number"
                                                                    min={6}
                                                                    max={24}
                                                                    value={
                                                                        reportHeaderTitleFontSize
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) => {
                                                                        setReportHeaderTitleFontSize(
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                />
                                                            </div>
                                                        </div>
                                                        <input
                                                            type="hidden"
                                                            name="report_header_title_font_family"
                                                            value={
                                                                reportHeaderTitleFontFamily
                                                            }
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="report_header_title_font_variant"
                                                            value={
                                                                reportHeaderTitleFontVariant
                                                            }
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="report_header_title_font_weight"
                                                            value={
                                                                reportHeaderTitleFontWeight
                                                            }
                                                        />
                                                        <InputError
                                                            message={
                                                                formErrors.report_header_title_font_family ??
                                                                formErrors.report_header_title_font_variant ??
                                                                formErrors.report_header_title_font_weight ??
                                                                formErrors.report_header_title_font_size
                                                            }
                                                        />
                                                    </div>

                                                    <div className="space-y-4 rounded-xl border border-border/60 bg-muted/30 p-4">
                                                        <div className="space-y-1">
                                                            <p className="text-sm font-semibold">
                                                                Tagline font
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                Applies to the
                                                                optional header
                                                                tagline.
                                                            </p>
                                                        </div>
                                                        <FontPicker
                                                            defaultValue={
                                                                reportHeaderTaglineFontFamilyResolved
                                                            }
                                                            inputId="report-tagline-font"
                                                            loadFonts={
                                                                reportHeaderTaglineFontFamilyResolved
                                                            }
                                                            autoLoad
                                                            mode="combo"
                                                            value={(
                                                                nextFont,
                                                            ) => {
                                                                setReportHeaderTaglineFontFamily(
                                                                    normalizeFontFamily(
                                                                        nextFont,
                                                                    ),
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                            }}
                                                        />
                                                        <div className="grid gap-3 sm:grid-cols-3">
                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Weight
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        reportHeaderTaglineFontWeight ||
                                                                        undefined
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) => {
                                                                        setReportHeaderTaglineFontWeight(
                                                                            value,
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue placeholder="Weight" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {REPORT_FONT_WEIGHT_OPTIONS.map(
                                                                            (
                                                                                option,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        option.value
                                                                                    }
                                                                                    value={
                                                                                        option.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        option.label
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Style
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        reportHeaderTaglineFontVariant ||
                                                                        undefined
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) => {
                                                                        setReportHeaderTaglineFontVariant(
                                                                            value,
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue placeholder="Style" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {REPORT_FONT_STYLE_OPTIONS.map(
                                                                            (
                                                                                option,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        option.value
                                                                                    }
                                                                                    value={
                                                                                        option.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        option.label
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="report_header_tagline_font_size">
                                                                    Size
                                                                </Label>
                                                                <Input
                                                                    id="report_header_tagline_font_size"
                                                                    name="report_header_tagline_font_size"
                                                                    type="number"
                                                                    min={6}
                                                                    max={24}
                                                                    value={
                                                                        reportHeaderTaglineFontSize
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) => {
                                                                        setReportHeaderTaglineFontSize(
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                />
                                                            </div>
                                                        </div>
                                                        <input
                                                            type="hidden"
                                                            name="report_header_tagline_font_family"
                                                            value={
                                                                reportHeaderTaglineFontFamily
                                                            }
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="report_header_tagline_font_variant"
                                                            value={
                                                                reportHeaderTaglineFontVariant
                                                            }
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="report_header_tagline_font_weight"
                                                            value={
                                                                reportHeaderTaglineFontWeight
                                                            }
                                                        />
                                                        <InputError
                                                            message={
                                                                formErrors.report_header_tagline_font_family ??
                                                                formErrors.report_header_tagline_font_variant ??
                                                                formErrors.report_header_tagline_font_weight ??
                                                                formErrors.report_header_tagline_font_size
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                            <div className="space-y-1">
                                                <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                    Report body typography
                                                </p>
                                            </div>

                                            <div className="grid gap-6 lg:grid-cols-2">
                                                <div className="space-y-4 rounded-xl border border-border/60 bg-muted/30 p-4">
                                                    <div className="space-y-1">
                                                        <p className="text-sm font-semibold">
                                                            Label font
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Applies to report
                                                            field labels.
                                                        </p>
                                                    </div>
                                                    <FontPicker
                                                        defaultValue={
                                                            reportLabelFontFamilyResolved
                                                        }
                                                        inputId="report-label-font"
                                                        loadFonts={
                                                            reportLabelFontFamilyResolved
                                                        }
                                                        autoLoad
                                                        mode="combo"
                                                        value={(nextFont) => {
                                                            setReportLabelFontFamily(
                                                                normalizeFontFamily(
                                                                    nextFont,
                                                                ),
                                                            );
                                                            setHasChanges(true);
                                                        }}
                                                    />
                                                    <div className="grid gap-3 sm:grid-cols-3">
                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Weight
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    reportLabelFontWeight ||
                                                                    undefined
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) => {
                                                                    setReportLabelFontWeight(
                                                                        value,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                }}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Weight" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {REPORT_FONT_WEIGHT_OPTIONS.map(
                                                                        (
                                                                            option,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    option.value
                                                                                }
                                                                                value={
                                                                                    option.value
                                                                                }
                                                                            >
                                                                                {
                                                                                    option.label
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label>Style</Label>
                                                            <Select
                                                                value={
                                                                    reportLabelFontVariant ||
                                                                    undefined
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) => {
                                                                    setReportLabelFontVariant(
                                                                        value,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                }}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Style" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {REPORT_FONT_STYLE_OPTIONS.map(
                                                                        (
                                                                            option,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    option.value
                                                                                }
                                                                                value={
                                                                                    option.value
                                                                                }
                                                                            >
                                                                                {
                                                                                    option.label
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label htmlFor="report_label_font_size">
                                                                Size
                                                            </Label>
                                                            <Input
                                                                id="report_label_font_size"
                                                                name="report_label_font_size"
                                                                type="number"
                                                                min={6}
                                                                max={24}
                                                                value={
                                                                    reportLabelFontSize
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    setReportLabelFontSize(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                }}
                                                            />
                                                        </div>
                                                    </div>
                                                    <input
                                                        type="hidden"
                                                        name="report_label_font_family"
                                                        value={
                                                            reportLabelFontFamily
                                                        }
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="report_label_font_variant"
                                                        value={
                                                            reportLabelFontVariant
                                                        }
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="report_label_font_weight"
                                                        value={
                                                            reportLabelFontWeight
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            formErrors.report_label_font_family ??
                                                            formErrors.report_label_font_variant ??
                                                            formErrors.report_label_font_weight ??
                                                            formErrors.report_label_font_size
                                                        }
                                                    />
                                                </div>

                                                <div className="space-y-4 rounded-xl border border-border/60 bg-muted/30 p-4">
                                                    <div className="space-y-1">
                                                        <p className="text-sm font-semibold">
                                                            Value font
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Applies to report
                                                            field values.
                                                        </p>
                                                    </div>
                                                    <FontPicker
                                                        defaultValue={
                                                            reportValueFontFamilyResolved
                                                        }
                                                        inputId="report-value-font"
                                                        loadFonts={
                                                            reportValueFontFamilyResolved
                                                        }
                                                        autoLoad
                                                        mode="combo"
                                                        value={(nextFont) => {
                                                            setReportValueFontFamily(
                                                                normalizeFontFamily(
                                                                    nextFont,
                                                                ),
                                                            );
                                                            setHasChanges(true);
                                                        }}
                                                    />
                                                    <div className="grid gap-3 sm:grid-cols-3">
                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Weight
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    reportValueFontWeight ||
                                                                    undefined
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) => {
                                                                    setReportValueFontWeight(
                                                                        value,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                }}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Weight" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {REPORT_FONT_WEIGHT_OPTIONS.map(
                                                                        (
                                                                            option,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    option.value
                                                                                }
                                                                                value={
                                                                                    option.value
                                                                                }
                                                                            >
                                                                                {
                                                                                    option.label
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label>Style</Label>
                                                            <Select
                                                                value={
                                                                    reportValueFontVariant ||
                                                                    undefined
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) => {
                                                                    setReportValueFontVariant(
                                                                        value,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                }}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Style" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {REPORT_FONT_STYLE_OPTIONS.map(
                                                                        (
                                                                            option,
                                                                        ) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    option.value
                                                                                }
                                                                                value={
                                                                                    option.value
                                                                                }
                                                                            >
                                                                                {
                                                                                    option.label
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label htmlFor="report_value_font_size">
                                                                Size
                                                            </Label>
                                                            <Input
                                                                id="report_value_font_size"
                                                                name="report_value_font_size"
                                                                type="number"
                                                                min={6}
                                                                max={24}
                                                                value={
                                                                    reportValueFontSize
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    setReportValueFontSize(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                }}
                                                            />
                                                        </div>
                                                    </div>
                                                    <input
                                                        type="hidden"
                                                        name="report_value_font_family"
                                                        value={
                                                            reportValueFontFamily
                                                        }
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="report_value_font_variant"
                                                        value={
                                                            reportValueFontVariant
                                                        }
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="report_value_font_weight"
                                                        value={
                                                            reportValueFontWeight
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            formErrors.report_value_font_family ??
                                                            formErrors.report_value_font_variant ??
                                                            formErrors.report_value_font_weight ??
                                                            formErrors.report_value_font_size
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="space-y-1">
                                                <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                    Colors
                                                </p>
                                            </div>

                                            <div className="grid gap-6 lg:grid-cols-3">
                                                <div className="grid gap-3">
                                                    <Label htmlFor="report_header_font_color">
                                                        Header font color
                                                    </Label>
                                                    <p
                                                        id={
                                                            reportHeaderColorHelpId
                                                        }
                                                        className="text-sm text-muted-foreground"
                                                    >
                                                        Applies to report header
                                                        title and company name.
                                                        Default:{' '}
                                                        {
                                                            DEFAULT_REPORT_HEADER_COLOR
                                                        }
                                                        .
                                                    </p>
                                                    <div className="flex flex-wrap items-center gap-3">
                                                        <input
                                                            id="report_header_font_color_picker"
                                                            type="color"
                                                            value={
                                                                reportHeaderColorSwatch
                                                            }
                                                            aria-label="Report header font color picker"
                                                            aria-describedby={
                                                                reportHeaderColorHelpId
                                                            }
                                                            className="h-9 w-9 cursor-pointer rounded-md border border-border bg-transparent p-0"
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportHeaderColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportHeaderColorValue(
                                                                    event.target.value.toLowerCase(),
                                                                );
                                                            }}
                                                        />
                                                        <Input
                                                            id="report_header_font_color"
                                                            name="report_header_font_color"
                                                            value={
                                                                reportHeaderColorInputValue
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportHeaderColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportHeaderColorValue(
                                                                    event.target
                                                                        .value,
                                                                );
                                                            }}
                                                            onBlur={(event) => {
                                                                setReportHeaderColorTouched(
                                                                    true,
                                                                );
                                                                setReportHeaderColorValue(
                                                                    normalizeHexInputValue(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                );
                                                            }}
                                                            placeholder={
                                                                DEFAULT_REPORT_HEADER_COLOR
                                                            }
                                                            inputMode="text"
                                                            autoCapitalize="none"
                                                            autoCorrect="off"
                                                            spellCheck={false}
                                                            maxLength={7}
                                                            className="w-32 font-mono"
                                                            aria-invalid={
                                                                reportHeaderColorInvalid
                                                            }
                                                            aria-describedby={
                                                                reportHeaderColorHelpId
                                                            }
                                                        />
                                                        <div
                                                            className="h-9 w-9 rounded-md border border-border bg-muted"
                                                            style={{
                                                                backgroundColor:
                                                                    reportHeaderColorSwatch,
                                                            }}
                                                            aria-hidden="true"
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setReportHeaderColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportHeaderColorValue(
                                                                    DEFAULT_REPORT_HEADER_COLOR,
                                                                );
                                                            }}
                                                        >
                                                            Reset
                                                        </Button>
                                                    </div>
                                                    <InputError
                                                        message={
                                                            reportHeaderColorError
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-3">
                                                    <Label htmlFor="report_header_tagline_color">
                                                        Tagline font color
                                                    </Label>
                                                    <p
                                                        id={
                                                            reportHeaderTaglineColorHelpId
                                                        }
                                                        className="text-sm text-muted-foreground"
                                                    >
                                                        Applies to report header
                                                        tagline. Defaults to the
                                                        header color.
                                                    </p>
                                                    <div className="flex flex-wrap items-center gap-3">
                                                        <input
                                                            id="report_header_tagline_color_picker"
                                                            type="color"
                                                            value={
                                                                reportHeaderTaglineColorSwatch
                                                            }
                                                            aria-label="Report header tagline font color picker"
                                                            aria-describedby={
                                                                reportHeaderTaglineColorHelpId
                                                            }
                                                            className="h-9 w-9 cursor-pointer rounded-md border border-border bg-transparent p-0"
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportHeaderTaglineColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportHeaderTaglineColorValue(
                                                                    event.target.value.toLowerCase(),
                                                                );
                                                            }}
                                                        />
                                                        <Input
                                                            id="report_header_tagline_color"
                                                            name="report_header_tagline_color"
                                                            value={
                                                                reportHeaderTaglineColorInputValue
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportHeaderTaglineColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportHeaderTaglineColorValue(
                                                                    event.target
                                                                        .value,
                                                                );
                                                            }}
                                                            onBlur={(
                                                                event,
                                                            ) => {
                                                                setReportHeaderTaglineColorTouched(
                                                                    true,
                                                                );
                                                                setReportHeaderTaglineColorValue(
                                                                    normalizeHexInputValue(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                );
                                                            }}
                                                            placeholder={
                                                                reportHeaderColorResolved
                                                            }
                                                            inputMode="text"
                                                            autoCapitalize="none"
                                                            autoCorrect="off"
                                                            spellCheck={false}
                                                            maxLength={7}
                                                            className="w-32 font-mono"
                                                            aria-invalid={
                                                                reportHeaderTaglineColorInvalid
                                                            }
                                                            aria-describedby={
                                                                reportHeaderTaglineColorHelpId
                                                            }
                                                        />
                                                        <div
                                                            className="h-9 w-9 rounded-md border border-border bg-muted"
                                                            style={{
                                                                backgroundColor:
                                                                    reportHeaderTaglineColorSwatch,
                                                            }}
                                                            aria-hidden="true"
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setReportHeaderTaglineColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportHeaderTaglineColorValue(
                                                                    reportHeaderColorResolved,
                                                                );
                                                            }}
                                                        >
                                                            Reset
                                                        </Button>
                                                    </div>
                                                    <InputError
                                                        message={
                                                            reportHeaderTaglineColorError
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-3">
                                                    <Label htmlFor="report_label_font_color">
                                                        Label font color
                                                    </Label>
                                                    <p
                                                        id={
                                                            reportLabelColorHelpId
                                                        }
                                                        className="text-sm text-muted-foreground"
                                                    >
                                                        Applies to report field
                                                        labels. Default:{' '}
                                                        {
                                                            DEFAULT_REPORT_LABEL_COLOR
                                                        }
                                                        .
                                                    </p>
                                                    <div className="flex flex-wrap items-center gap-3">
                                                        <input
                                                            id="report_label_font_color_picker"
                                                            type="color"
                                                            value={
                                                                reportLabelColorSwatch
                                                            }
                                                            aria-label="Report label font color picker"
                                                            aria-describedby={
                                                                reportLabelColorHelpId
                                                            }
                                                            className="h-9 w-9 cursor-pointer rounded-md border border-border bg-transparent p-0"
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportLabelColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportLabelColorValue(
                                                                    event.target.value.toLowerCase(),
                                                                );
                                                            }}
                                                        />
                                                        <Input
                                                            id="report_label_font_color"
                                                            name="report_label_font_color"
                                                            value={
                                                                reportLabelColorInputValue
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportLabelColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportLabelColorValue(
                                                                    event.target
                                                                        .value,
                                                                );
                                                            }}
                                                            onBlur={(event) => {
                                                                setReportLabelColorTouched(
                                                                    true,
                                                                );
                                                                setReportLabelColorValue(
                                                                    normalizeHexInputValue(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                );
                                                            }}
                                                            placeholder={
                                                                DEFAULT_REPORT_LABEL_COLOR
                                                            }
                                                            inputMode="text"
                                                            autoCapitalize="none"
                                                            autoCorrect="off"
                                                            spellCheck={false}
                                                            maxLength={7}
                                                            className="w-32 font-mono"
                                                            aria-invalid={
                                                                reportLabelColorInvalid
                                                            }
                                                            aria-describedby={
                                                                reportLabelColorHelpId
                                                            }
                                                        />
                                                        <div
                                                            className="h-9 w-9 rounded-md border border-border bg-muted"
                                                            style={{
                                                                backgroundColor:
                                                                    reportLabelColorSwatch,
                                                            }}
                                                            aria-hidden="true"
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setReportLabelColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportLabelColorValue(
                                                                    DEFAULT_REPORT_LABEL_COLOR,
                                                                );
                                                            }}
                                                        >
                                                            Reset
                                                        </Button>
                                                    </div>
                                                    <InputError
                                                        message={
                                                            reportLabelColorError
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-3">
                                                    <Label htmlFor="report_value_font_color">
                                                        Value font color
                                                    </Label>
                                                    <p
                                                        id={
                                                            reportValueColorHelpId
                                                        }
                                                        className="text-sm text-muted-foreground"
                                                    >
                                                        Applies to report field
                                                        values. Default:{' '}
                                                        {
                                                            DEFAULT_REPORT_VALUE_COLOR
                                                        }
                                                        .
                                                    </p>
                                                    <div className="flex flex-wrap items-center gap-3">
                                                        <input
                                                            id="report_value_font_color_picker"
                                                            type="color"
                                                            value={
                                                                reportValueColorSwatch
                                                            }
                                                            aria-label="Report value font color picker"
                                                            aria-describedby={
                                                                reportValueColorHelpId
                                                            }
                                                            className="h-9 w-9 cursor-pointer rounded-md border border-border bg-transparent p-0"
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportValueColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportValueColorValue(
                                                                    event.target.value.toLowerCase(),
                                                                );
                                                            }}
                                                        />
                                                        <Input
                                                            id="report_value_font_color"
                                                            name="report_value_font_color"
                                                            value={
                                                                reportValueColorInputValue
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setReportValueColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportValueColorValue(
                                                                    event.target
                                                                        .value,
                                                                );
                                                            }}
                                                            onBlur={(event) => {
                                                                setReportValueColorTouched(
                                                                    true,
                                                                );
                                                                setReportValueColorValue(
                                                                    normalizeHexInputValue(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                );
                                                            }}
                                                            placeholder={
                                                                DEFAULT_REPORT_VALUE_COLOR
                                                            }
                                                            inputMode="text"
                                                            autoCapitalize="none"
                                                            autoCorrect="off"
                                                            spellCheck={false}
                                                            maxLength={7}
                                                            className="w-32 font-mono"
                                                            aria-invalid={
                                                                reportValueColorInvalid
                                                            }
                                                            aria-describedby={
                                                                reportValueColorHelpId
                                                            }
                                                        />
                                                        <div
                                                            className="h-9 w-9 rounded-md border border-border bg-muted"
                                                            style={{
                                                                backgroundColor:
                                                                    reportValueColorSwatch,
                                                            }}
                                                            aria-hidden="true"
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setReportValueColorTouched(
                                                                    true,
                                                                );
                                                                setHasChanges(
                                                                    true,
                                                                );
                                                                setReportValueColorValue(
                                                                    DEFAULT_REPORT_VALUE_COLOR,
                                                                );
                                                            }}
                                                        >
                                                            Reset
                                                        </Button>
                                                    </div>
                                                    <InputError
                                                        message={
                                                            reportValueColorError
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            </SurfaceCard>
                                                </TabsContent>

                                                <TabsContent
                                                    value="colors"
                                                    className="mt-0"
                                                >
                                                    <SurfaceCard
                                                variant="muted"
                                                padding="md"
                                                className="space-y-6"
                                            >
                                                <div className="space-y-1">
                                                    <h3 className="text-base font-semibold tracking-tight">
                                                        Brand colors
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        Applied to primary and
                                                        accent UI colors across
                                                        the portal after save.
                                                    </p>
                                                </div>

                                                <div className="grid gap-6 md:grid-cols-2">
                                                    <div className="grid gap-3">
                                                        <Label htmlFor="brand_primary_color">
                                                            Brand primary color
                                                        </Label>
                                                        <p
                                                            id={
                                                                brandPrimaryHelpId
                                                            }
                                                            className="text-sm text-muted-foreground"
                                                        >
                                                            Used for primary
                                                            actions. Default:{' '}
                                                            {
                                                                DEFAULT_BRAND_PRIMARY
                                                            }
                                                            .
                                                        </p>
                                                        <div className="flex flex-wrap items-center gap-3">
                                                            <input
                                                                id="brand_primary_color_picker"
                                                                type="color"
                                                                value={
                                                                    primarySwatch
                                                                }
                                                                aria-label="Brand primary color picker"
                                                                aria-describedby={
                                                                    brandPrimaryHelpId
                                                                }
                                                                className="h-9 w-9 cursor-pointer rounded-md border border-border bg-transparent p-0"
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    setBrandPrimaryTouched(
                                                                        true,
                                                                    );
                                                                    setBrandPrimaryValue(
                                                                        event.target.value.toLowerCase(),
                                                                    );
                                                                }}
                                                            />
                                                            <Input
                                                                id="brand_primary_color"
                                                                name="brand_primary_color"
                                                                value={
                                                                    primaryInputValue
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    setBrandPrimaryTouched(
                                                                        true,
                                                                    );
                                                                    setBrandPrimaryValue(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    );
                                                                }}
                                                                onBlur={(
                                                                    event,
                                                                ) => {
                                                                    setBrandPrimaryTouched(
                                                                        true,
                                                                    );
                                                                    setBrandPrimaryValue(
                                                                        normalizeHexInputValue(
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    );
                                                                }}
                                                                placeholder={
                                                                    DEFAULT_BRAND_PRIMARY
                                                                }
                                                                inputMode="text"
                                                                autoCapitalize="none"
                                                                autoCorrect="off"
                                                                spellCheck={
                                                                    false
                                                                }
                                                                maxLength={7}
                                                                className="w-32 font-mono"
                                                                aria-invalid={
                                                                    primaryInvalid
                                                                }
                                                                aria-describedby={
                                                                    brandPrimaryHelpId
                                                                }
                                                            />
                                                            <div
                                                                className="h-9 w-9 rounded-md border border-border bg-muted"
                                                                style={{
                                                                    backgroundColor:
                                                                        primarySwatch,
                                                                }}
                                                                aria-hidden="true"
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => {
                                                                    setBrandPrimaryTouched(
                                                                        true,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                    setBrandPrimaryValue(
                                                                        DEFAULT_BRAND_PRIMARY,
                                                                    );
                                                                }}
                                                            >
                                                                Reset
                                                            </Button>
                                                        </div>
                                                        <InputError
                                                            message={
                                                                primaryError
                                                            }
                                                        />
                                                    </div>

                                                    <div className="grid gap-3">
                                                        <Label htmlFor="brand_accent_color">
                                                            Brand accent color
                                                        </Label>
                                                        <p
                                                            id={
                                                                brandAccentHelpId
                                                            }
                                                            className="text-sm text-muted-foreground"
                                                        >
                                                            Used for accents and
                                                            highlights. Default:{' '}
                                                            {
                                                                DEFAULT_BRAND_ACCENT
                                                            }
                                                            .
                                                        </p>
                                                        <div className="flex flex-wrap items-center gap-3">
                                                            <input
                                                                id="brand_accent_color_picker"
                                                                type="color"
                                                                value={
                                                                    accentSwatch
                                                                }
                                                                aria-label="Brand accent color picker"
                                                                aria-describedby={
                                                                    brandAccentHelpId
                                                                }
                                                                className="h-9 w-9 cursor-pointer rounded-md border border-border bg-transparent p-0"
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    setBrandAccentTouched(
                                                                        true,
                                                                    );
                                                                    setBrandAccentValue(
                                                                        event.target.value.toLowerCase(),
                                                                    );
                                                                }}
                                                            />
                                                            <Input
                                                                id="brand_accent_color"
                                                                name="brand_accent_color"
                                                                value={
                                                                    accentInputValue
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    setBrandAccentTouched(
                                                                        true,
                                                                    );
                                                                    setBrandAccentValue(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    );
                                                                }}
                                                                onBlur={(
                                                                    event,
                                                                ) => {
                                                                    setBrandAccentTouched(
                                                                        true,
                                                                    );
                                                                    setBrandAccentValue(
                                                                        normalizeHexInputValue(
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    );
                                                                }}
                                                                placeholder={
                                                                    DEFAULT_BRAND_ACCENT
                                                                }
                                                                inputMode="text"
                                                                autoCapitalize="none"
                                                                autoCorrect="off"
                                                                spellCheck={
                                                                    false
                                                                }
                                                                maxLength={7}
                                                                className="w-32 font-mono"
                                                                aria-invalid={
                                                                    accentInvalid
                                                                }
                                                                aria-describedby={
                                                                    brandAccentHelpId
                                                                }
                                                            />
                                                            <div
                                                                className="h-9 w-9 rounded-md border border-border bg-muted"
                                                                style={{
                                                                    backgroundColor:
                                                                        accentSwatch,
                                                                }}
                                                                aria-hidden="true"
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => {
                                                                    setBrandAccentTouched(
                                                                        true,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                    setBrandAccentValue(
                                                                        DEFAULT_BRAND_ACCENT,
                                                                    );
                                                                }}
                                                            >
                                                                Reset
                                                            </Button>
                                                        </div>
                                                        <InputError
                                                            message={
                                                                accentError
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            </SurfaceCard>
                                                </TabsContent>

                                                <TabsContent
                                                    value="support"
                                                    className="mt-0 space-y-6"
                                                >
                                                    <SurfaceCard
                                                variant="muted"
                                                padding="md"
                                                className="space-y-6"
                                            >
                                                <div className="space-y-1">
                                                    <h3 className="text-base font-semibold tracking-tight">
                                                        Contact &amp; communications
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        Support contact details
                                                        shown on the welcome and
                                                        sign-in screens.
                                                    </p>
                                                </div>

                                                <div className="grid gap-6 md:grid-cols-2">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="support_contact_name">
                                                            Support contact name
                                                        </Label>
                                                        <Input
                                                            id="support_contact_name"
                                                            name="support_contact_name"
                                                            defaultValue={
                                                                branding.supportContactName ??
                                                                ''
                                                            }
                                                            placeholder="Support Team"
                                                        />
                                                        <InputError
                                                            message={
                                                                formErrors.support_contact_name
                                                            }
                                                        />
                                                    </div>

                                                    <div className="grid gap-2">
                                                        <Label htmlFor="support_email">
                                                            Support email
                                                        </Label>
                                                        <Input
                                                            id="support_email"
                                                            name="support_email"
                                                            type="email"
                                                            defaultValue={
                                                                branding.supportEmail ??
                                                                ''
                                                            }
                                                            placeholder="support@company.com"
                                                        />
                                                        <InputError
                                                            message={
                                                                formErrors.support_email
                                                            }
                                                        />
                                                    </div>

                                                    <div className="grid gap-2 md:col-span-2">
                                                        <Label htmlFor="support_phone">
                                                            Support phone
                                                        </Label>
                                                        <Input
                                                            id="support_phone"
                                                            name="support_phone"
                                                            type="tel"
                                                            defaultValue={
                                                                branding.supportPhone ??
                                                                ''
                                                            }
                                                            placeholder="+63 900 000 0000"
                                                        />
                                                        <InputError
                                                            message={
                                                                formErrors.support_phone
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            </SurfaceCard>

                                            <SurfaceCard
                                                variant="muted"
                                                padding="md"
                                                className="space-y-6"
                                            >
                                                <div className="space-y-1">
                                                    <h3 className="text-base font-semibold tracking-tight">
                                                        Loan SMS templates
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        Customize the approval
                                                        and decline SMS messages
                                                        sent to members after
                                                        decisions.
                                                    </p>
                                                </div>

                                                <div className="grid gap-6">
                                                    <div className="rounded-2xl border border-border/30 bg-background/60 p-4">
                                                        <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                                            Available
                                                            placeholders
                                                        </p>
                                                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                                            {LOAN_SMS_PLACEHOLDERS.map(
                                                                (item) => (
                                                                    <div
                                                                        key={
                                                                            item.token
                                                                        }
                                                                        className="flex items-center justify-between gap-3 rounded-lg border border-border/40 bg-background px-3 py-2 text-xs"
                                                                    >
                                                                        <span className="font-mono text-foreground">
                                                                            {
                                                                                item.token
                                                                            }
                                                                        </span>
                                                                        <span className="text-muted-foreground">
                                                                            {
                                                                                item.label
                                                                            }
                                                                        </span>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>

                                                    <div className="grid gap-6 lg:grid-cols-2">
                                                        <div className="space-y-2">
                                                            <Label htmlFor="loan_sms_approved_template">
                                                                Approved SMS
                                                                template
                                                            </Label>
                                                            <textarea
                                                                id="loan_sms_approved_template"
                                                                name="loan_sms_approved_template"
                                                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[120px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                                                                placeholder="Leave blank to use the default template."
                                                                value={
                                                                    loanSmsApprovedTemplate
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    setLoanSmsApprovedTemplate(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                }}
                                                            />
                                                            <p className="text-xs text-muted-foreground">
                                                                Leave blank to
                                                                use the default
                                                                template.
                                                            </p>
                                                            <InputError
                                                                message={
                                                                    formErrors.loan_sms_approved_template
                                                                }
                                                            />
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label htmlFor="loan_sms_declined_template">
                                                                Declined SMS
                                                                template
                                                            </Label>
                                                            <textarea
                                                                id="loan_sms_declined_template"
                                                                name="loan_sms_declined_template"
                                                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[120px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                                                                placeholder="Leave blank to use the default template."
                                                                value={
                                                                    loanSmsDeclinedTemplate
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    setLoanSmsDeclinedTemplate(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    );
                                                                    setHasChanges(
                                                                        true,
                                                                    );
                                                                }}
                                                            />
                                                            <p className="text-xs text-muted-foreground">
                                                                Leave blank to
                                                                use the default
                                                                template.
                                                            </p>
                                                            <InputError
                                                                message={
                                                                    formErrors.loan_sms_declined_template
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="rounded-2xl border border-border/30 bg-background/60 p-4">
                                                        <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                                            Preview
                                                        </p>
                                                        <div className="mt-3 space-y-4 text-sm">
                                                            <div className="space-y-1">
                                                                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                                                    Approved
                                                                </p>
                                                                <p className="text-sm text-foreground">
                                                                    {
                                                                        loanSmsApprovedPreview
                                                                    }
                                                                </p>
                                                            </div>
                                                            <div className="space-y-1">
                                                                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                                                    Declined
                                                                </p>
                                                                <p className="text-sm text-foreground">
                                                                    {
                                                                        loanSmsDeclinedPreview
                                                                    }
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </SurfaceCard>
                                                </TabsContent>
                                            </Tabs>

                                            <div className="sticky bottom-4 z-10 rounded-2xl border border-border/40 bg-background/95 p-4 shadow-[0_12px_24px_-24px_rgba(0,0,0,0.45)] backdrop-blur">
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                        {hasChanges ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-primary/40 text-[10px] uppercase tracking-[0.2em] text-primary"
                                                            >
                                                                Unsaved changes
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="secondary"
                                                                className="text-[10px] uppercase tracking-[0.2em]"
                                                            >
                                                                Up to date
                                                            </Badge>
                                                        )}
                                                        <Transition
                                                            show={
                                                                recentlySuccessful
                                                            }
                                                            enter="transition ease-in-out"
                                                            enterFrom="opacity-0"
                                                            leave="transition ease-in-out"
                                                            leaveTo="opacity-0"
                                                        >
                                                            <span>Saved</span>
                                                        </Transition>
                                                    </div>
                                                    <Button
                                                        disabled={processing}
                                                    >
                                                        Save changes
                                                    </Button>
                                                </div>
                                            </div>
                                        </>
                                    );
                                }}
                            </Form>
                        </div>
                    </SurfaceCard>

                    <div className="space-y-6">
                        <div className="space-y-6 lg:sticky lg:top-24">
                            <SurfaceCard
                                variant="default"
                                padding="lg"
                                className="space-y-6 lg:max-h-[calc(100vh-7rem)] lg:overflow-hidden"
                            >
                                <SectionHeader
                                    title="Live preview"
                                    description="Review how the portal and reports will look before saving changes."
                                    titleClassName="text-base font-semibold"
                                />
                                <div className="space-y-6 lg:max-h-[calc(100vh-12rem)] lg:overflow-y-auto lg:pr-2">
                                    <div className="space-y-2">
                                        <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                            Portal header
                                        </p>
                                        <div className="rounded-2xl border border-border/30 bg-muted/20 p-4">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-border/40 bg-background">
                                                    <img
                                                        src={logoPreviewUrl}
                                                        alt={`${companyNamePreview} logo`}
                                                        className={`w-auto object-contain ${
                                                            logoPreset ===
                                                            'full'
                                                                ? 'h-8'
                                                                : 'h-7'
                                                        }`}
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    {showCompanyNamePreview ? (
                                                        <p className="text-sm font-semibold">
                                                            {companyNamePreview}
                                                        </p>
                                                    ) : null}
                                                    <p className="text-xs text-muted-foreground">
                                                        {portalLabelPreview}
                                                    </p>
                                                </div>
                                            </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                            Portal icon
                                        </p>
                                        <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-border/30 bg-muted/20 p-4">
                                            {ICON_PREVIEW_SIZES.map((size) => (
                                                <div
                                                    key={size}
                                                    className="flex h-10 w-10 items-center justify-center rounded-lg border border-border/60 bg-background"
                                                >
                                                    <img
                                                        src={faviconPreviewUrl}
                                                        alt={`${branding.appTitle} ${size}px icon`}
                                                        className="object-contain"
                                                        style={{
                                                            width: size,
                                                            height: size,
                                                        }}
                                                    />
                                                </div>
                                            ))}
                                            <span className="text-xs text-muted-foreground">
                                                Browser tab + app sizes
                                            </span>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                            Report header
                                        </p>
                                        <div className="rounded-2xl border border-border/30 bg-muted/20 p-4">
                                            <div className="rounded-xl border border-slate-200 bg-white p-4 text-slate-900 shadow-sm">
                                                <div
                                                    className={`flex ${reportHeaderAlignmentClass}`}
                                                >
                                                    <div
                                                        className={`inline-flex items-center gap-3 ${reportHeaderTextAlignClass}`}
                                                    >
                                                        {reportHeaderShowLogo ||
                                                        reportShowCompanyNamePreview ? (
                                                            <div className="flex items-center gap-2">
                                                                {reportHeaderShowLogo ? (
                                                                    <img
                                                                        src={
                                                                            logoPreviewUrl
                                                                        }
                                                                        alt={`${companyNamePreview} report logo`}
                                                                        className={`w-auto object-contain ${
                                                                            logoPreset ===
                                                                            'full'
                                                                                ? 'h-10'
                                                                                : 'h-8'
                                                                        }`}
                                                                    />
                                                                ) : null}
                                                                {reportShowCompanyNamePreview ? (
                                                                    <p
                                                                        className="apply-font-report-title text-xs font-semibold"
                                                                        style={{
                                                                            ...reportCompanyNameStyle,
                                                                            fontSize:
                                                                                '12px',
                                                                        }}
                                                                    >
                                                                        {
                                                                            companyNamePreview
                                                                        }
                                                                    </p>
                                                                ) : null}
                                                            </div>
                                                        ) : null}
                                                    <div>
                                                        <p
                                                            className="apply-font-report-title text-sm font-semibold"
                                                            style={
                                                                reportHeaderTitleStyle
                                                            }
                                                        >
                                                            {
                                                                reportHeaderTitlePreview
                                                            }
                                                        </p>
                                                        {reportHeaderTaglinePreview ? (
                                                            <p
                                                                className="apply-font-report-tagline text-xs"
                                                                style={
                                                                    reportHeaderTaglineStyle
                                                                }
                                                            >
                                                                {
                                                                    reportHeaderTaglinePreview
                                                                }
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="mt-3 h-px bg-slate-200" />
                                            <div className="mt-3 space-y-2">
                                                <p className="text-xs text-slate-500">
                                                    Report body preview
                                                </p>
                                                <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span
                                                            className="apply-font-report-label"
                                                            style={
                                                                reportLabelStyle
                                                            }
                                                        >
                                                            Member name
                                                        </span>
                                                        <span
                                                            className="apply-font-report-value font-semibold"
                                                            style={
                                                                reportValueStyle
                                                            }
                                                        >
                                                            Jane Doe
                                                        </span>
                                                    </div>
                                                    <div className="mt-2 flex items-center justify-between text-xs">
                                                        <span
                                                            className="apply-font-report-label"
                                                            style={
                                                                reportLabelStyle
                                                            }
                                                        >
                                                            Amount approved
                                                        </span>
                                                        <span
                                                            className="apply-font-report-value font-semibold"
                                                            style={
                                                                reportValueStyle
                                                            }
                                                        >
                                                            PHP 50,000.00
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </SurfaceCard>
                        </div>
                    </div>
                </div>
            </PageShell>
        </AppLayout>
    );
}
