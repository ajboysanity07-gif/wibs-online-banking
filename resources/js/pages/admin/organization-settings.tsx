import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    Building2,
    FileText,
    Image as ImageIcon,
    Info,
    Mail,
    MessageSquare,
    Palette,
    Search,
} from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import FontPicker from 'react-fontpicker-ts';
import 'react-fontpicker-ts/dist/index.css';
import OrganizationSettingsController from '@/actions/App/Http/Controllers/Admin/OrganizationSettingsController';
import InputError from '@/components/input-error';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { LocationCombobox } from '@/components/location-combobox';
import {
    OrganizationPaletteFields,
    PALETTE_GROUPS,
} from '@/components/organization-palette-fields';
import type { PaletteValues } from '@/components/organization-palette-fields';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useBranding } from '@/hooks/use-branding';
import { useLocationSearch } from '@/hooks/use-location-search';
import AppLayout from '@/layouts/app-layout';
import { composeAddress } from '@/lib/formatters';
import { statusTones } from '@/lib/status-tones';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { organization as organizationSettings } from '@/routes/admin/settings';
import api from '@/lib/api';
import { barangays, cities, provinces, zip } from '@/routes/api/locations';
import { mrdincTheme } from '@/theme/clients/mrdinc';
import type { BrandPaletteKey, BreadcrumbItem, LogoPreset } from '@/types';

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
const REPORT_HEADER_DESIGN_MAX_BYTES = 4 * 1024 * 1024;
const REPORT_HEADER_DESIGN_ALLOWED_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
]);
const DEFAULT_BRAND_PRIMARY = mrdincTheme.hex.primary.toLowerCase();
const DEFAULT_BRAND_ACCENT = mrdincTheme.hex.accent.toLowerCase();
const PRIMARY_COLOR_ERROR =
    'Primary color must be a valid hex value (e.g., #1a2b3c).';
const ACCENT_COLOR_ERROR =
    'Accent color must be a valid hex value (e.g., #1a2b3c).';
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
const ICON_PREVIEW_SIZES = [16, 24, 32];
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
    '{company_name} {portal_label}: Your loan request ({loan_reference}) has been APPROVED for {approved_amount} payable over {approved_term} months and is awaiting processing in WIBS.';
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
            normalizedPortal
                .toLowerCase()
                .includes(normalizedCompany.toLowerCase())
        ) {
            return normalizedPortal;
        }

        return `${normalizedCompany} ${normalizedPortal}`.trim();
    }

    return normalizedPortal || normalizedCompany;
};

const resolveOfficeName = (
    companyName: string,
    portalLabel: string,
): string => {
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

type TabKey = 'general' | 'branding' | 'documents' | 'contact' | 'messaging';

type SearchResult = { label: string; tab: TabKey; target: string | null };

const SETTINGS_TABS: Array<{
    key: TabKey;
    label: string;
    icon: typeof Building2;
}> = [
    { key: 'general', label: 'General', icon: Building2 },
    { key: 'branding', label: 'Branding', icon: Palette },
    { key: 'documents', label: 'Documents', icon: FileText },
    { key: 'contact', label: 'Contact', icon: Mail },
    { key: 'messaging', label: 'Messaging', icon: MessageSquare },
];

const PALETTE_KEYS = PALETTE_GROUPS.flatMap((group) =>
    group.fields.map((field) => field.key),
);

const tabLabel = (tab: TabKey): string =>
    SETTINGS_TABS.find((entry) => entry.key === tab)?.label ?? tab;

const readableInk = (hex: string): string => {
    const channel = (offset: number): number => {
        const value = parseInt(hex.slice(offset, offset + 2), 16) / 255;

        return value <= 0.03928
            ? value / 12.92
            : Math.pow((value + 0.055) / 1.055, 2.4);
    };
    const luminance =
        0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);

    return luminance > 0.19 ? '#14170f' : '#ffffff';
};

const TIMEZONE_OPTIONS = [
    { value: 'Asia/Manila', label: 'Asia/Manila (UTC+8)' },
    { value: 'Asia/Singapore', label: 'Asia/Singapore (UTC+8)' },
    { value: 'UTC', label: 'UTC' },
];
const CURRENCY_OPTIONS = [
    { value: 'PHP', label: 'PHP ₱' },
    { value: 'USD', label: 'USD $' },
];
const SEND_WINDOW_OPTIONS = [
    { value: '7am-8pm-daily', label: '7:00 AM – 8:00 PM daily' },
    { value: '8am-6pm-mon-sat', label: '8:00 AM – 6:00 PM, Mon–Sat' },
    { value: 'any', label: 'Any time' },
];

function NativeSelect({
    options,
    className,
    ...props
}: React.ComponentProps<'select'> & {
    options: Array<{ value: string; label: string }>;
}) {
    return (
        <select
            {...props}
            className={cn(
                'h-9 w-full rounded-md border border-input bg-card px-3 text-sm',
                className,
            )}
        >
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

function ToggleRow({
    id,
    label,
    hint,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    hint: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg border border-border bg-muted px-3.5 py-3">
            <div className="min-w-0 flex-1">
                <Label htmlFor={id}>{label}</Label>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>
            <input type="hidden" name={id} value={checked ? '1' : '0'} />
            <Switch
                id={id}
                checked={checked}
                onCheckedChange={onChange}
                aria-label={label}
            />
        </div>
    );
}

function PreviewRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-3 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-semibold break-words">
                {value}
            </span>
        </div>
    );
}

export default function OrganizationSettings() {
    const branding = useBranding();
    const { lastSaved } = usePage<{
        lastSaved: { at: string | null; by: string | null } | null;
    }>().props;
    const [logoPreset, setLogoPreset] = useState<LogoPreset>(
        branding.logoPreset,
    );
    const [companyNameValue, setCompanyNameValue] = useState(
        branding.companyName,
    );
    const [portalLabelValue, setPortalLabelValue] = useState(
        branding.portalLabel,
    );
    const [businessAddress1Value, setBusinessAddress1Value] = useState(
        branding.businessAddress1 ?? '',
    );
    const [businessAddressZip, setBusinessAddressZip] = useState(
        branding.businessAddressZip ?? '',
    );
    const businessProvinceSearch = useLocationSearch({
        initialQuery: branding.businessAddress3 ?? '',
        searchUrl: provinces.url(),
    });
    const businessCitySearch = useLocationSearch({
        initialQuery: branding.businessAddress2 ?? '',
        searchUrl: cities.url(),
        params: {
            province: businessProvinceSearch.query.trim() || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
    const businessBarangaySearch = useLocationSearch({
        initialQuery: branding.businessAddressBarangay ?? '',
        searchUrl: barangays.url(),
        params: {
            municipality: businessCitySearch.selectedValue || undefined,
            province: businessProvinceSearch.query.trim() || undefined,
        },
        clientFilter: true,
        limit: 500,
    });
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
    const reportHeaderDesignInputRef = useRef<HTMLInputElement>(null);
    const [reportHeaderDesignPreview, setReportHeaderDesignPreview] = useState<
        string | null
    >(null);
    const [reportHeaderDesignReset, setReportHeaderDesignReset] =
        useState(false);
    const faviconInputRef = useRef<HTMLInputElement>(null);
    const [faviconPreview, setFaviconPreview] = useState<string | null>(null);
    const [faviconReset, setFaviconReset] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);
    const [activeTab, setActiveTab] = useState<TabKey>(() => {
        const hash =
            typeof window === 'undefined'
                ? ''
                : window.location.hash.replace('#panel-', '');

        return SETTINGS_TABS.some((tab) => tab.key === hash)
            ? (hash as TabKey)
            : 'general';
    });
    const [dirtyTabs, setDirtyTabs] = useState<Set<TabKey>>(new Set());
    const [live, setLive] = useState<Record<string, string>>({});
    const [palette, setPalette] = useState<PaletteValues>(
        () =>
            Object.fromEntries(
                PALETTE_KEYS.map((key) => [
                    key,
                    branding.brandPalette?.[key] ?? '',
                ]),
            ) as PaletteValues,
    );
    const [findQuery, setFindQuery] = useState('');
    const [findResults, setFindResults] = useState<SearchResult[]>([]);
    const [footerOn, setFooterOn] = useState(branding.reportFooterEnabled);
    const [approvedOn, setApprovedOn] = useState(
        branding.loanSmsEnabled.approved,
    );
    const [declinedOn, setDeclinedOn] = useState(
        branding.loanSmsEnabled.declined,
    );
    const [dangerKind, setDangerKind] = useState<'brand' | 'sms' | null>(null);
    const [resetWord, setResetWord] = useState('');
    const [brandPrimaryValue, setBrandPrimaryValue] = useState(() =>
        normalizeHexInputValue(branding.brandPrimaryColor),
    );
    const [brandPrimaryTouched, setBrandPrimaryTouched] = useState(false);
    const [brandAccentValue, setBrandAccentValue] = useState(() =>
        normalizeHexInputValue(branding.brandAccentColor),
    );
    const [brandAccentTouched, setBrandAccentTouched] = useState(false);
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
    const [loanSmsApprovedTemplate, setLoanSmsApprovedTemplate] = useState(
        () =>
            branding.communications?.loanSmsTemplates?.approved
                ? branding.communications.loanSmsTemplates.approved
                : DEFAULT_LOAN_SMS_APPROVED_TEMPLATE,
    );
    const [loanSmsDeclinedTemplate, setLoanSmsDeclinedTemplate] = useState(
        () =>
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
    const reportLabelColorInputValue = reportLabelColorTouched
        ? reportLabelColorValue
        : normalizeHexInputValue(branding.reportTypography.label.color);
    const reportValueColorInputValue = reportValueColorTouched
        ? reportValueColorValue
        : normalizeHexInputValue(branding.reportTypography.value.color);
    const normalizedPrimary = normalizeHexValue(primaryInputValue);
    const normalizedAccent = normalizeHexValue(accentInputValue);
    const normalizedReportLabelColor = normalizeHexValue(
        reportLabelColorInputValue,
    );
    const normalizedReportValueColor = normalizeHexValue(
        reportValueColorInputValue,
    );
    const primarySwatch = normalizedPrimary ?? DEFAULT_BRAND_PRIMARY;
    const accentSwatch = normalizedAccent ?? DEFAULT_BRAND_ACCENT;
    const reportLabelColorSwatch =
        normalizedReportLabelColor ?? DEFAULT_REPORT_LABEL_COLOR;
    const reportValueColorSwatch =
        normalizedReportValueColor ?? DEFAULT_REPORT_VALUE_COLOR;
    const brandPrimaryHelpId = 'brand_primary_color_help';
    const brandAccentHelpId = 'brand_accent_color_help';
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
    const businessAddressPreview = composeAddress(
        businessAddress1Value,
        businessCitySearch.query,
        businessProvinceSearch.query,
        businessBarangaySearch.query,
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
    const hasStoredReportHeaderDesign =
        branding.reportHeader.designPath !== null;
    const reportHeaderDesignCurrentUrl = reportHeaderDesignReset
        ? null
        : branding.reportHeader.designUrl;
    const reportHeaderDesignPreviewUrl =
        reportHeaderDesignPreview ?? reportHeaderDesignCurrentUrl;
    const reportLabelFontSizeValue = resolveNumberInput(
        reportLabelFontSize,
        branding.reportTypography.label.size,
    );
    const reportValueFontSizeValue = resolveNumberInput(
        reportValueFontSize,
        branding.reportTypography.value.size,
    );
    const reportLabelFontFamilyResolved = resolveFontValue(
        reportLabelFontFamily,
        branding.reportTypography.label.family,
    );
    const reportValueFontFamilyResolved = resolveFontValue(
        reportValueFontFamily,
        branding.reportTypography.value.family,
    );
    const reportLabelFontWeightResolved = resolveNumberInput(
        reportLabelFontWeight,
        branding.reportTypography.label.weight,
    );
    const reportValueFontWeightResolved = resolveNumberInput(
        reportValueFontWeight,
        branding.reportTypography.value.weight,
    );
    const reportLabelColorResolved =
        normalizedReportLabelColor ?? DEFAULT_REPORT_LABEL_COLOR;
    const reportValueColorResolved =
        normalizedReportValueColor ?? DEFAULT_REPORT_VALUE_COLOR;
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

    useEffect(() => {
        if (!reportHeaderDesignPreview) {
            return;
        }

        return () => {
            URL.revokeObjectURL(reportHeaderDesignPreview);
        };
    }, [reportHeaderDesignPreview]);

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

    const handleReportHeaderDesignChange = (
        event: ChangeEvent<HTMLInputElement>,
    ) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (!REPORT_HEADER_DESIGN_ALLOWED_TYPES.has(file.type)) {
            showErrorToast(
                null,
                'Please select a JPG, PNG, or WebP image for the report header design.',
                {
                    id: 'organization-report-header-design-type',
                },
            );
            event.target.value = '';
            return;
        }

        if (file.size > REPORT_HEADER_DESIGN_MAX_BYTES) {
            showErrorToast(
                null,
                'Report header design must be 4MB or smaller.',
                {
                    id: 'organization-report-header-design-size',
                },
            );
            event.target.value = '';
            return;
        }

        setReportHeaderDesignReset(false);
        setReportHeaderDesignPreview(URL.createObjectURL(file));
        setHasChanges(true);
    };

    const clearReportHeaderDesignPreview = () => {
        setReportHeaderDesignPreview(null);
        setHasChanges(true);

        if (reportHeaderDesignInputRef.current) {
            reportHeaderDesignInputRef.current.value = '';
        }
    };

    const resetReportHeaderDesignToDefault = () => {
        setReportHeaderDesignReset(true);
        setReportHeaderDesignPreview(null);
        setHasChanges(true);

        if (reportHeaderDesignInputRef.current) {
            reportHeaderDesignInputRef.current.value = '';
        }
    };

    const keepCurrentReportHeaderDesign = () => {
        setReportHeaderDesignReset(false);
        setReportHeaderDesignPreview(null);
        setHasChanges(true);

        if (reportHeaderDesignInputRef.current) {
            reportHeaderDesignInputRef.current.value = '';
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

    const handleBusinessCitySelect = async (code: string) => {
        if (!code) {
            return;
        }

        try {
            const response = await api.get(zip.url(), {
                params: { locality_code: code },
            });
            const resolvedZip = (
                response.data as { zip?: string | null }
            ).zip?.trim();

            if (resolvedZip) {
                setBusinessAddressZip(resolvedZip);
            }
        } catch {
            // Intentionally left empty: ZIP lookup is best-effort.
        }
    };

    const liveValue = (name: string, saved: string | null): string =>
        (live[name] ?? saved ?? '').trim() || '--';

    const selectTab = (tab: TabKey) => {
        setActiveTab(tab);
        window.history.replaceState(null, '', `#panel-${tab}`);
    };

    const markDirty = (tab: TabKey) => {
        setHasChanges(true);
        setDirtyTabs((previous) =>
            previous.has(tab) ? previous : new Set(previous).add(tab),
        );
    };

    const panelProps = (tab: TabKey) => ({
        id: `panel-${tab}`,
        hidden: activeTab !== tab,
        'data-panel': tab,
        'aria-label': tabLabel(tab),
        onChange: (event: FormEvent<HTMLDivElement>) => {
            const target = event.target as HTMLInputElement;

            markDirty(tab);

            if (target.name && target.type !== 'file') {
                setLive((previous) => ({
                    ...previous,
                    [target.name]: target.value,
                }));
            }
        },
    });

    const setPaletteValue = (key: BrandPaletteKey, value: string) => {
        setPalette((previous) => ({ ...previous, [key]: value }));
        markDirty('branding');
    };

    const resetBranding = () => {
        setPalette(
            Object.fromEntries(
                PALETTE_KEYS.map((key) => [key, '']),
            ) as PaletteValues,
        );
        setBrandPrimaryTouched(true);
        setBrandPrimaryValue('');
        setBrandAccentTouched(true);
        setBrandAccentValue('');
        markDirty('branding');
    };

    const dangerWord = dangerKind === 'sms' ? 'CLEAR' : 'RESET';

    const clearSmsTemplates = () => {
        setLoanSmsApprovedTemplate('');
        setLoanSmsDeclinedTemplate('');
        setApprovedOn(false);
        setDeclinedOn(false);
        markDirty('messaging');
    };

    const runDanger = () => {
        if (dangerKind === 'sms') {
            clearSmsTemplates();
        } else {
            resetBranding();
        }

        setDangerKind(null);
    };

    const discardChanges = () => {
        router.visit(window.location.href, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const runSearch = (query: string) => {
        setFindQuery(query);

        const needle = query.trim().toLowerCase();

        if (needle === '') {
            setFindResults([]);

            return;
        }

        const hits: SearchResult[] = [];

        document
            .querySelectorAll<HTMLElement>('[data-panel]')
            .forEach((panel) => {
                const tab = panel.dataset.panel as TabKey;

                panel.querySelectorAll('label').forEach((label) => {
                    const text = (label.textContent ?? '')
                        .replace(/\s+/g, ' ')
                        .trim();

                    if (
                        text !== '' &&
                        `${text} ${tabLabel(tab)}`
                            .toLowerCase()
                            .includes(needle)
                    ) {
                        hits.push({
                            label: text,
                            tab,
                            target: label.htmlFor || null,
                        });
                    }
                });
            });

        setFindResults(hits.slice(0, 7));
    };

    const jumpTo = (result: SearchResult) => {
        selectTab(result.tab);
        setFindQuery('');
        setFindResults([]);

        window.setTimeout(() => {
            const node =
                result.target !== null
                    ? document.getElementById(result.target)
                    : null;

            if (node === null) {
                return;
            }

            node.scrollIntoView({ block: 'center', behavior: 'smooth' });
            node.focus({ preventScroll: true });
            node.classList.add('ring-2', 'ring-ring');
            window.setTimeout(
                () => node.classList.remove('ring-2', 'ring-ring'),
                1800,
            );
        }, 50);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization settings" />

            <PageShell size="wide" className="gap-8 pb-16">
                <section className="flex flex-wrap items-start gap-5">
                    <div>
                        <p className="text-[11px] font-bold tracking-[0.14em] text-primary uppercase">
                            Configuration
                        </p>
                        <h1 className="mt-1 text-[22px] leading-tight font-bold sm:text-[26px]">
                            Organization settings
                        </h1>
                        <p className="mt-1.5 max-w-prose text-sm text-muted-foreground">
                            Branding, report headers, contact details, and
                            member messaging shared by every WIBS portal.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Badge variant="outline" className={statusTones.ok}>
                                Live on portal
                            </Badge>
                            <Badge
                                variant="outline"
                                className={statusTones.neutral}
                            >
                                {hasChanges
                                    ? 'Unsaved changes'
                                    : lastSaved?.at
                                      ? `Last saved ${new Date(lastSaved.at).toLocaleDateString('en-PH', { day: 'numeric', month: 'short', year: 'numeric' })}${lastSaved.by ? ` by ${lastSaved.by}` : ''}`
                                      : 'Up to date'}
                            </Badge>
                            <Badge
                                variant="outline"
                                className={statusTones.info}
                            >
                                Superadmin only
                            </Badge>
                        </div>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-[216px_minmax(0,1fr)] lg:items-start">
                    <nav
                        aria-label="Settings sections"
                        className="grid content-start gap-3 lg:sticky lg:top-4"
                    >
                        <div className="relative">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                type="search"
                                value={findQuery}
                                onChange={(event) =>
                                    runSearch(event.target.value)
                                }
                                placeholder="Find a setting"
                                aria-label="Find a setting"
                                autoComplete="off"
                                className="pl-9"
                            />
                            {findQuery.trim() !== '' ? (
                                <div className="absolute top-[calc(100%+8px)] right-0 left-0 z-30 grid gap-1 rounded-lg border border-border bg-popover p-2 shadow-lg">
                                    {findResults.length === 0 ? (
                                        <p className="px-3 py-2 text-[13px] text-muted-foreground">
                                            No settings match "
                                            {findQuery.trim()}". Try logo,
                                            footer, hotline, or SMS.
                                        </p>
                                    ) : (
                                        findResults.map((result) => (
                                            <button
                                                key={`${result.tab}-${result.target ?? result.label}`}
                                                type="button"
                                                onClick={() => jumpTo(result)}
                                                className="grid min-h-11 gap-0.5 rounded-md border border-transparent px-3 py-2 text-left text-sm font-semibold hover:border-border hover:bg-muted"
                                            >
                                                <span>{result.label}</span>
                                                <span className="text-[11px] font-bold tracking-[0.06em] text-muted-foreground uppercase">
                                                    {tabLabel(result.tab)}
                                                </span>
                                            </button>
                                        ))
                                    )}
                                </div>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap gap-1 lg:grid">
                            {SETTINGS_TABS.map((tab) => {
                                const Icon = tab.icon;
                                const on = activeTab === tab.key;

                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        aria-current={on ? 'page' : undefined}
                                        onClick={() => selectTab(tab.key)}
                                        className={cn(
                                            'flex min-h-11 w-auto items-center gap-2.5 rounded-md border border-transparent px-3 py-2 text-left text-sm font-semibold lg:w-full',
                                            on
                                                ? 'border-primary bg-primary font-bold text-primary-foreground'
                                                : 'hover:bg-muted',
                                        )}
                                    >
                                        <Icon
                                            className="size-[18px] shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0 flex-1">
                                            {tab.label}
                                        </span>
                                        {dirtyTabs.has(tab.key) ? (
                                            <>
                                                <span
                                                    className="size-[7px] shrink-0 rounded-full bg-[var(--warn-ink)]"
                                                    aria-hidden="true"
                                                />
                                                <span className="sr-only">
                                                    — unsaved changes
                                                </span>
                                            </>
                                        ) : null}
                                    </button>
                                );
                            })}
                        </div>
                    </nav>

                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start">
                        <div className="grid gap-4">
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
                                    setReportHeaderDesignPreview(null);
                                    setReportHeaderDesignReset(false);
                                    if (reportHeaderDesignInputRef.current) {
                                        reportHeaderDesignInputRef.current.value =
                                            '';
                                    }
                                    clearFaviconPreview();
                                    setFaviconReset(false);
                                    setBrandPrimaryTouched(false);
                                    setBrandAccentTouched(false);
                                    setReportLabelColorTouched(false);
                                    setReportValueColorTouched(false);
                                    setDirtyTabs(new Set());
                                    setLive({});
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
                                    const reportLabelColorInvalid =
                                        reportLabelColorError !== undefined;
                                    const reportValueColorInvalid =
                                        reportValueColorError !== undefined;

                                    return (
                                        <>
                                            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                                                <div {...panelProps('general')}>
                                                    <LoanRequestSectionCard
                                                        flat
                                                        workspace
                                                        className="first:border-t-0"
                                                        title="General"
                                                        description="Company name, portal label, and the app title shown to members."
                                                        icon={Building2}
                                                        contentClassName="space-y-6"
                                                    >
                                                        <div className="grid gap-6 md:grid-cols-3">
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="short_name">
                                                                    Short name
                                                                </Label>
                                                                <Input
                                                                    id="short_name"
                                                                    name="short_name"
                                                                    defaultValue={
                                                                        branding.shortName ??
                                                                        ''
                                                                    }
                                                                    maxLength={
                                                                        32
                                                                    }
                                                                />
                                                                <InputError
                                                                    message={
                                                                        formErrors.short_name
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="timezone">
                                                                    Timezone
                                                                </Label>
                                                                <NativeSelect
                                                                    id="timezone"
                                                                    name="timezone"
                                                                    defaultValue={
                                                                        branding.timezone
                                                                    }
                                                                    options={
                                                                        TIMEZONE_OPTIONS
                                                                    }
                                                                />
                                                                <InputError
                                                                    message={
                                                                        formErrors.timezone
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="statement_currency">
                                                                    Statement
                                                                    currency
                                                                </Label>
                                                                <NativeSelect
                                                                    id="statement_currency"
                                                                    name="statement_currency"
                                                                    defaultValue={
                                                                        branding.statementCurrency
                                                                    }
                                                                    options={
                                                                        CURRENCY_OPTIONS
                                                                    }
                                                                />
                                                                <InputError
                                                                    message={
                                                                        formErrors.statement_currency
                                                                    }
                                                                />
                                                            </div>
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
                                                                            event
                                                                                .target
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
                                                                            event
                                                                                .target
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
                                                            <div className="grid gap-3 md:col-span-2">
                                                                <div className="space-y-1">
                                                                    <Label>
                                                                        Business
                                                                        address
                                                                    </Label>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        This
                                                                        address
                                                                        is used
                                                                        as the
                                                                        official
                                                                        place of
                                                                        signing
                                                                        in
                                                                        generated
                                                                        loan
                                                                        documents.
                                                                    </p>
                                                                </div>
                                                                <div className="grid gap-4 sm:grid-cols-2">
                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="business_address3">
                                                                            Province
                                                                        </Label>
                                                                        <LocationCombobox
                                                                            id="business_address3"
                                                                            name="business_address3"
                                                                            search={
                                                                                businessProvinceSearch
                                                                            }
                                                                            placeholder="Select province"
                                                                            inputClassName="w-full"
                                                                            loadingMessage="Searching province suggestions..."
                                                                            errorMessage="Province suggestions are temporarily unavailable."
                                                                            promptMessage="Type at least 2 characters to search provinces."
                                                                            onSelect={() => {
                                                                                businessCitySearch.setSelectedValue(
                                                                                    '',
                                                                                );
                                                                                businessBarangaySearch.setSelectedValue(
                                                                                    '',
                                                                                );
                                                                                setBusinessAddressZip(
                                                                                    '',
                                                                                );
                                                                                setHasChanges(
                                                                                    true,
                                                                                );
                                                                            }}
                                                                            onClear={() => {
                                                                                businessCitySearch.setSelectedValue(
                                                                                    '',
                                                                                );
                                                                                businessBarangaySearch.setSelectedValue(
                                                                                    '',
                                                                                );
                                                                                setBusinessAddressZip(
                                                                                    '',
                                                                                );
                                                                                setHasChanges(
                                                                                    true,
                                                                                );
                                                                            }}
                                                                        />
                                                                        <InputError
                                                                            message={
                                                                                formErrors.business_address3
                                                                            }
                                                                        />
                                                                    </div>
                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="business_address2">
                                                                            City
                                                                            /
                                                                            Municipality
                                                                        </Label>
                                                                        <LocationCombobox
                                                                            id="business_address2"
                                                                            name="business_address2"
                                                                            search={
                                                                                businessCitySearch
                                                                            }
                                                                            placeholder="Select city or municipality"
                                                                            inputClassName="w-full"
                                                                            disabled={
                                                                                !businessProvinceSearch.selectedValue
                                                                            }
                                                                            loadingMessage="Searching city suggestions..."
                                                                            errorMessage="City suggestions are temporarily unavailable."
                                                                            promptMessage="Select a province first."
                                                                            onSelect={(
                                                                                suggestion,
                                                                            ) => {
                                                                                if (
                                                                                    suggestion.province
                                                                                ) {
                                                                                    businessProvinceSearch.setSelectedValue(
                                                                                        suggestion.province,
                                                                                    );
                                                                                }
                                                                                businessBarangaySearch.setSelectedValue(
                                                                                    '',
                                                                                );
                                                                                void handleBusinessCitySelect(
                                                                                    suggestion.code,
                                                                                );
                                                                                setHasChanges(
                                                                                    true,
                                                                                );
                                                                            }}
                                                                            onClear={() => {
                                                                                businessBarangaySearch.setSelectedValue(
                                                                                    '',
                                                                                );
                                                                                setBusinessAddressZip(
                                                                                    '',
                                                                                );
                                                                                setHasChanges(
                                                                                    true,
                                                                                );
                                                                            }}
                                                                        />
                                                                        <InputError
                                                                            message={
                                                                                formErrors.business_address2
                                                                            }
                                                                        />
                                                                    </div>
                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="business_address_zip">
                                                                            ZIP
                                                                            code
                                                                        </Label>
                                                                        <Input
                                                                            id="business_address_zip"
                                                                            name="business_address_zip"
                                                                            value={
                                                                                businessAddressZip
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) => {
                                                                                setBusinessAddressZip(
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                );
                                                                                setHasChanges(
                                                                                    true,
                                                                                );
                                                                            }}
                                                                            inputMode="numeric"
                                                                            autoComplete="postal-code"
                                                                            placeholder="Auto-filled from city"
                                                                        />
                                                                        <InputError
                                                                            message={
                                                                                formErrors.business_address_zip
                                                                            }
                                                                        />
                                                                    </div>
                                                                    <div className="grid gap-2">
                                                                        <Label htmlFor="business_address_barangay">
                                                                            Barangay
                                                                        </Label>
                                                                        <LocationCombobox
                                                                            id="business_address_barangay"
                                                                            name="business_address_barangay"
                                                                            search={
                                                                                businessBarangaySearch
                                                                            }
                                                                            placeholder="Select barangay"
                                                                            inputClassName="w-full"
                                                                            disabled={
                                                                                !businessCitySearch.selectedValue
                                                                            }
                                                                            loadingMessage="Loading barangays..."
                                                                            errorMessage="Barangay suggestions are temporarily unavailable."
                                                                            promptMessage="Select a city or municipality first."
                                                                            onSelect={() =>
                                                                                setHasChanges(
                                                                                    true,
                                                                                )
                                                                            }
                                                                        />
                                                                        <InputError
                                                                            message={
                                                                                formErrors.business_address_barangay
                                                                            }
                                                                        />
                                                                    </div>
                                                                    <div className="grid gap-2 sm:col-span-2">
                                                                        <Label htmlFor="business_address1">
                                                                            Street
                                                                            /
                                                                            Barangay
                                                                            /
                                                                            Office
                                                                            address
                                                                        </Label>
                                                                        <Input
                                                                            id="business_address1"
                                                                            name="business_address1"
                                                                            value={
                                                                                businessAddress1Value
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) => {
                                                                                setBusinessAddress1Value(
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                );
                                                                                setHasChanges(
                                                                                    true,
                                                                                );
                                                                            }}
                                                                            placeholder="Street / Barangay / Office address"
                                                                        />
                                                                        <InputError
                                                                            message={
                                                                                formErrors.business_address1
                                                                            }
                                                                        />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div className="grid gap-2 md:col-span-2">
                                                                <Label>
                                                                    Business
                                                                    address
                                                                    preview
                                                                </Label>
                                                                <div className="rounded-lg border border-border bg-background px-3 py-2 text-sm text-muted-foreground">
                                                                    {businessAddressPreview ||
                                                                        '--'}
                                                                </div>
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="business_tin">
                                                                    TIN
                                                                </Label>
                                                                <Input
                                                                    id="business_tin"
                                                                    name="business_tin"
                                                                    defaultValue={
                                                                        branding.businessTin ??
                                                                        ''
                                                                    }
                                                                    placeholder="000-000-000-000"
                                                                />
                                                                <InputError
                                                                    message={
                                                                        formErrors.business_tin
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="registration_no">
                                                                    CDA / SEC
                                                                    registration
                                                                    no.
                                                                </Label>
                                                                <Input
                                                                    id="registration_no"
                                                                    name="registration_no"
                                                                    defaultValue={
                                                                        branding.registrationNo ??
                                                                        ''
                                                                    }
                                                                    placeholder="CDA-REG-0000"
                                                                />
                                                                <InputError
                                                                    message={
                                                                        formErrors.registration_no
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="grid gap-2 md:col-span-2">
                                                                <Label htmlFor="payment_instructions">
                                                                    Payment
                                                                    instructions
                                                                    (shown on
                                                                    statements)
                                                                </Label>
                                                                <Textarea
                                                                    id="payment_instructions"
                                                                    name="payment_instructions"
                                                                    className="min-h-[90px]"
                                                                    defaultValue={
                                                                        branding.paymentInstructions ??
                                                                        ''
                                                                    }
                                                                    placeholder="Pay at any MRDINC branch or through ..."
                                                                />
                                                                <InputError
                                                                    message={
                                                                        formErrors.payment_instructions
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="grid gap-2 md:col-span-2">
                                                                <Label>
                                                                    App title
                                                                    preview
                                                                </Label>
                                                                <div className="rounded-lg border border-border bg-background px-3 py-2 text-sm text-muted-foreground">
                                                                    {appTitlePreview ||
                                                                        '--'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </LoanRequestSectionCard>
                                                </div>

                                                <div
                                                    {...panelProps('branding')}
                                                >
                                                    <LoanRequestSectionCard
                                                        flat
                                                        workspace
                                                        className="first:border-t-0"
                                                        title="Brand assets"
                                                        description="Choose the primary logo and portal icon used throughout the member experience."
                                                        icon={ImageIcon}
                                                        contentClassName="space-y-6"
                                                    >
                                                        <div className="grid gap-6">
                                                            <div className="space-y-4 rounded-xl border border-border bg-background p-4">
                                                                <Label className="text-sm font-semibold">
                                                                    Primary logo
                                                                </Label>
                                                                <div
                                                                    role="radiogroup"
                                                                    aria-label="Primary logo selection"
                                                                    className="grid gap-4 md:grid-cols-2"
                                                                >
                                                                    {LOGO_PRESET_OPTIONS.map(
                                                                        (
                                                                            option,
                                                                        ) => {
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
                                                                                    className={`group flex flex-col gap-4 rounded-xl border p-5 transition-colors focus-within:ring-2 focus-within:ring-primary/40 focus-within:outline-none ${
                                                                                        isSelected
                                                                                            ? 'border-primary/60 bg-primary/5 shadow-sm shadow-primary/10'
                                                                                            : 'border-border bg-card hover:border-primary/40 hover:bg-muted'
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
                                                                                    <Label
                                                                                        htmlFor={
                                                                                            optionId
                                                                                        }
                                                                                        className="grid cursor-pointer gap-4 text-base leading-normal font-normal"
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
                                                                                                    className="text-[10px] tracking-[0.2em] uppercase"
                                                                                                >
                                                                                                    Selected
                                                                                                </Badge>
                                                                                            ) : (
                                                                                                <Badge
                                                                                                    variant="outline"
                                                                                                    className="text-[10px] tracking-[0.2em] uppercase"
                                                                                                >
                                                                                                    Select
                                                                                                </Badge>
                                                                                            )}
                                                                                        </div>
                                                                                        <div className="flex h-20 items-center justify-center rounded-xl border border-border bg-muted">
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
                                                                                                className="text-[10px] tracking-[0.2em] uppercase"
                                                                                            >
                                                                                                {isDefault
                                                                                                    ? 'Default asset'
                                                                                                    : 'Custom asset'}
                                                                                            </Badge>
                                                                                            {isReset ? (
                                                                                                <Badge
                                                                                                    variant="outline"
                                                                                                    className="border-primary/40 text-[10px] tracking-[0.2em] text-primary uppercase"
                                                                                                >
                                                                                                    Reset
                                                                                                    after
                                                                                                    save
                                                                                                </Badge>
                                                                                            ) : null}
                                                                                        </div>
                                                                                    </Label>
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
                                                                    The selected
                                                                    logo appears
                                                                    on member
                                                                    forms,
                                                                    navigation,
                                                                    and reports.
                                                                </p>
                                                                <InputError
                                                                    message={
                                                                        formErrors.logo_preset
                                                                    }
                                                                />
                                                            </div>

                                                            <div className="space-y-4 rounded-xl border border-border bg-background p-4">
                                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                                    <div className="space-y-1">
                                                                        <Label htmlFor="favicon">
                                                                            Portal
                                                                            icon
                                                                        </Label>
                                                                        <p className="text-sm text-muted-foreground">
                                                                            Used
                                                                            in
                                                                            browser
                                                                            tabs
                                                                            and
                                                                            compact
                                                                            app
                                                                            surfaces.
                                                                        </p>
                                                                    </div>
                                                                    {faviconReset ? (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="text-[10px] tracking-[0.2em] uppercase"
                                                                        >
                                                                            Default
                                                                        </Badge>
                                                                    ) : hasStoredFavicon ||
                                                                      faviconPreview ? (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="text-[10px] tracking-[0.2em] uppercase"
                                                                        >
                                                                            Custom
                                                                        </Badge>
                                                                    ) : null}
                                                                </div>
                                                                <div className="rounded-xl border border-border bg-muted p-4">
                                                                    <div className="flex flex-wrap items-center justify-between gap-4">
                                                                        <div className="flex flex-wrap items-center gap-4">
                                                                            <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-border bg-background">
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
                                                                                            className="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-background"
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
                                                                        Upload a
                                                                        JPG,
                                                                        PNG,
                                                                        WebP, or
                                                                        ICO
                                                                        image
                                                                        (max
                                                                        1MB).
                                                                    </p>
                                                                </div>
                                                                {faviconReset ? (
                                                                    <p className="text-xs text-primary">
                                                                        Default
                                                                        icon
                                                                        will be
                                                                        used
                                                                        after
                                                                        saving.
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
                                                    </LoanRequestSectionCard>
                                                </div>

                                                <div
                                                    {...panelProps('documents')}
                                                >
                                                    <LoanRequestSectionCard
                                                        flat
                                                        workspace
                                                        className="first:border-t-0"
                                                        title="Reports & documents"
                                                        description="Upload a single report header design and manage report body typography for generated documents."
                                                        icon={FileText}
                                                        contentClassName="space-y-6"
                                                    >
                                                        <div className="grid gap-4">
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="report_footer">
                                                                    Report
                                                                    footer
                                                                </Label>
                                                                <Input
                                                                    id="report_footer"
                                                                    name="report_footer"
                                                                    defaultValue={
                                                                        branding.reportFooterText ??
                                                                        ''
                                                                    }
                                                                    maxLength={
                                                                        255
                                                                    }
                                                                    placeholder="Generated by WIBS Online Banking"
                                                                />
                                                                <InputError
                                                                    message={
                                                                        formErrors.report_footer
                                                                    }
                                                                />
                                                            </div>
                                                            <ToggleRow
                                                                id="report_footer_enabled"
                                                                label="Include footer on printed reports"
                                                                hint="Turn off to print the letterhead alone with no footer line."
                                                                checked={
                                                                    footerOn
                                                                }
                                                                onChange={(
                                                                    value,
                                                                ) => {
                                                                    setFooterOn(
                                                                        value,
                                                                    );
                                                                    markDirty(
                                                                        'documents',
                                                                    );
                                                                }}
                                                            />
                                                        </div>

                                                        <div className="space-y-1">
                                                            <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                                Report header
                                                                design
                                                            </p>
                                                        </div>

                                                        <div className="space-y-6 rounded-xl border border-border bg-background p-4">
                                                            <div className="space-y-1">
                                                                <p className="text-sm font-semibold">
                                                                    Uploaded
                                                                    report
                                                                    header
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    Upload one
                                                                    JPG, PNG, or
                                                                    WebP image
                                                                    used at the
                                                                    top of
                                                                    generated
                                                                    documents.
                                                                </p>
                                                            </div>

                                                            <div className="flex h-28 items-center justify-center rounded-xl border border-border bg-muted p-3">
                                                                {reportHeaderDesignPreviewUrl ? (
                                                                    <img
                                                                        src={
                                                                            reportHeaderDesignPreviewUrl
                                                                        }
                                                                        alt="Report header design preview"
                                                                        className="h-full w-full object-contain"
                                                                    />
                                                                ) : (
                                                                    <p className="text-center text-xs text-muted-foreground">
                                                                        No
                                                                        report
                                                                        header
                                                                        design
                                                                        uploaded
                                                                        yet.
                                                                    </p>
                                                                )}
                                                            </div>

                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        reportHeaderDesignInputRef.current?.click()
                                                                    }
                                                                >
                                                                    Upload
                                                                    header
                                                                    design
                                                                </Button>
                                                                {reportHeaderDesignPreview ? (
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={
                                                                            clearReportHeaderDesignPreview
                                                                        }
                                                                    >
                                                                        Remove
                                                                        selection
                                                                    </Button>
                                                                ) : null}
                                                                {hasStoredReportHeaderDesign ||
                                                                reportHeaderDesignReset ? (
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => {
                                                                            if (
                                                                                reportHeaderDesignReset
                                                                            ) {
                                                                                keepCurrentReportHeaderDesign();
                                                                                return;
                                                                            }

                                                                            resetReportHeaderDesignToDefault();
                                                                        }}
                                                                    >
                                                                        {reportHeaderDesignReset
                                                                            ? 'Keep current design'
                                                                            : 'Reset to default'}
                                                                    </Button>
                                                                ) : null}
                                                            </div>

                                                            <p className="text-xs text-muted-foreground">
                                                                Upload a JPG,
                                                                PNG, or WebP
                                                                image (max 4MB).
                                                            </p>

                                                            <input
                                                                ref={
                                                                    reportHeaderDesignInputRef
                                                                }
                                                                type="file"
                                                                name="report_header_design"
                                                                accept="image/png,image/jpeg,image/webp"
                                                                aria-label="Upload report header design"
                                                                className="sr-only"
                                                                onChange={
                                                                    handleReportHeaderDesignChange
                                                                }
                                                            />
                                                            {reportHeaderDesignReset ? (
                                                                <input
                                                                    type="hidden"
                                                                    name="report_header_design_reset"
                                                                    value="1"
                                                                />
                                                            ) : null}
                                                            <InputError
                                                                message={
                                                                    formErrors.report_header_design
                                                                }
                                                            />
                                                        </div>

                                                        <div className="space-y-1">
                                                            <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                                Report body
                                                                typography
                                                            </p>
                                                        </div>

                                                        <div className="grid gap-6">
                                                            <div className="space-y-4 rounded-xl border border-border bg-muted p-4">
                                                                <div className="space-y-1">
                                                                    <p className="text-sm font-semibold">
                                                                        Label
                                                                        font
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Applies
                                                                        to
                                                                        report
                                                                        field
                                                                        labels.
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
                                                                    value={(
                                                                        nextFont,
                                                                    ) => {
                                                                        setReportLabelFontFamily(
                                                                            normalizeFontFamily(
                                                                                nextFont,
                                                                            ),
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                />
                                                                <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_4.5rem] gap-3">
                                                                    <div className="grid min-w-0 gap-2">
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
                                                                    <div className="grid min-w-0 gap-2">
                                                                        <Label>
                                                                            Style
                                                                        </Label>
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
                                                                    <div className="grid min-w-0 gap-2">
                                                                        <Label htmlFor="report_label_font_size">
                                                                            Size
                                                                        </Label>
                                                                        <Input
                                                                            id="report_label_font_size"
                                                                            name="report_label_font_size"
                                                                            type="number"
                                                                            min={
                                                                                6
                                                                            }
                                                                            max={
                                                                                24
                                                                            }
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

                                                            <div className="space-y-4 rounded-xl border border-border bg-muted p-4">
                                                                <div className="space-y-1">
                                                                    <p className="text-sm font-semibold">
                                                                        Value
                                                                        font
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Applies
                                                                        to
                                                                        report
                                                                        field
                                                                        values.
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
                                                                    value={(
                                                                        nextFont,
                                                                    ) => {
                                                                        setReportValueFontFamily(
                                                                            normalizeFontFamily(
                                                                                nextFont,
                                                                            ),
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                />
                                                                <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_4.5rem] gap-3">
                                                                    <div className="grid min-w-0 gap-2">
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
                                                                    <div className="grid min-w-0 gap-2">
                                                                        <Label>
                                                                            Style
                                                                        </Label>
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
                                                                    <div className="grid min-w-0 gap-2">
                                                                        <Label htmlFor="report_value_font_size">
                                                                            Size
                                                                        </Label>
                                                                        <Input
                                                                            id="report_value_font_size"
                                                                            name="report_value_font_size"
                                                                            type="number"
                                                                            min={
                                                                                6
                                                                            }
                                                                            max={
                                                                                24
                                                                            }
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
                                                                <Label htmlFor="report_label_font_color">
                                                                    Label font
                                                                    color
                                                                </Label>
                                                                <p
                                                                    id={
                                                                        reportLabelColorHelpId
                                                                    }
                                                                    className="text-sm text-muted-foreground"
                                                                >
                                                                    Applies to
                                                                    report field
                                                                    labels.
                                                                    Default:{' '}
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
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            );
                                                                        }}
                                                                        onBlur={(
                                                                            event,
                                                                        ) => {
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
                                                                        spellCheck={
                                                                            false
                                                                        }
                                                                        maxLength={
                                                                            7
                                                                        }
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
                                                                    Value font
                                                                    color
                                                                </Label>
                                                                <p
                                                                    id={
                                                                        reportValueColorHelpId
                                                                    }
                                                                    className="text-sm text-muted-foreground"
                                                                >
                                                                    Applies to
                                                                    report field
                                                                    values.
                                                                    Default:{' '}
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
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            );
                                                                        }}
                                                                        onBlur={(
                                                                            event,
                                                                        ) => {
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
                                                                        spellCheck={
                                                                            false
                                                                        }
                                                                        maxLength={
                                                                            7
                                                                        }
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
                                                    </LoanRequestSectionCard>
                                                </div>

                                                <div
                                                    {...panelProps('branding')}
                                                >
                                                    <LoanRequestSectionCard
                                                        flat
                                                        workspace
                                                        className="first:border-t-0"
                                                        title="Brand colors"
                                                        description="Applied to primary and accent UI colors across the portal after save."
                                                        icon={Palette}
                                                        contentClassName="space-y-6"
                                                    >
                                                        <div className="grid gap-6 md:grid-cols-2">
                                                            <div className="grid gap-3">
                                                                <Label htmlFor="brand_primary_color">
                                                                    Brand
                                                                    primary
                                                                    color
                                                                </Label>
                                                                <p
                                                                    id={
                                                                        brandPrimaryHelpId
                                                                    }
                                                                    className="text-sm text-muted-foreground"
                                                                >
                                                                    Used for
                                                                    primary
                                                                    actions.
                                                                    Default:{' '}
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
                                                                        maxLength={
                                                                            7
                                                                        }
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
                                                                    Brand accent
                                                                    color
                                                                </Label>
                                                                <p
                                                                    id={
                                                                        brandAccentHelpId
                                                                    }
                                                                    className="text-sm text-muted-foreground"
                                                                >
                                                                    Used for
                                                                    accents and
                                                                    highlights.
                                                                    Default:{' '}
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
                                                                        maxLength={
                                                                            7
                                                                        }
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
                                                    </LoanRequestSectionCard>
                                                    <LoanRequestSectionCard
                                                        flat
                                                        workspace
                                                        className="first:border-t-0"
                                                        title="Palette"
                                                        description="Surfaces and status colors used across every portal. Leave a field blank to keep the default."
                                                        icon={Palette}
                                                        contentClassName="space-y-6"
                                                    >
                                                        <OrganizationPaletteFields
                                                            values={palette}
                                                            errors={formErrors}
                                                            onChange={
                                                                setPaletteValue
                                                            }
                                                        />
                                                    </LoanRequestSectionCard>
                                                </div>

                                                <div {...panelProps('contact')}>
                                                    <LoanRequestSectionCard
                                                        flat
                                                        workspace
                                                        className="first:border-t-0"
                                                        title="Contact & communications"
                                                        description="Support contact details shown on the welcome and sign-in screens."
                                                        icon={Mail}
                                                        contentClassName="space-y-6"
                                                    >
                                                        <div className="grid gap-2 md:col-span-2">
                                                            <Label htmlFor="service_hours">
                                                                Service hours
                                                            </Label>
                                                            <Input
                                                                id="service_hours"
                                                                name="service_hours"
                                                                defaultValue={
                                                                    branding.serviceHours ??
                                                                    ''
                                                                }
                                                                maxLength={255}
                                                                placeholder="Mon–Fri 8:00 AM–5:00 PM"
                                                            />
                                                            <InputError
                                                                message={
                                                                    formErrors.service_hours
                                                                }
                                                            />
                                                        </div>

                                                        <div className="grid gap-6 md:grid-cols-2">
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="support_contact_name">
                                                                    Support
                                                                    contact name
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
                                                                    Support
                                                                    email
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
                                                                    Support
                                                                    phone
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
                                                    </LoanRequestSectionCard>
                                                </div>
                                                <div
                                                    {...panelProps('messaging')}
                                                >
                                                    <LoanRequestSectionCard
                                                        flat
                                                        workspace
                                                        className="first:border-t-0"
                                                        title="Loan SMS templates"
                                                        description="Customize the approval and decline SMS messages sent to members after decisions."
                                                        icon={MessageSquare}
                                                        contentClassName="space-y-6"
                                                    >
                                                        <div className="grid gap-4">
                                                            <ToggleRow
                                                                id="loan_sms_approved_enabled"
                                                                label="Send the loan approved message"
                                                                hint="Sent to the member when a loan request is approved."
                                                                checked={
                                                                    approvedOn
                                                                }
                                                                onChange={(
                                                                    value,
                                                                ) => {
                                                                    setApprovedOn(
                                                                        value,
                                                                    );
                                                                    markDirty(
                                                                        'messaging',
                                                                    );
                                                                }}
                                                            />
                                                            <ToggleRow
                                                                id="loan_sms_declined_enabled"
                                                                label="Send the loan declined message"
                                                                hint="Sent to the member when a loan request is declined."
                                                                checked={
                                                                    declinedOn
                                                                }
                                                                onChange={(
                                                                    value,
                                                                ) => {
                                                                    setDeclinedOn(
                                                                        value,
                                                                    );
                                                                    markDirty(
                                                                        'messaging',
                                                                    );
                                                                }}
                                                            />
                                                            <div className="grid gap-2 rounded-lg border border-border bg-muted px-3.5 py-3 sm:grid-cols-[1fr_auto] sm:items-center">
                                                                <div>
                                                                    <Label htmlFor="sms_send_window">
                                                                        Send
                                                                        window
                                                                    </Label>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Messages
                                                                        outside
                                                                        this
                                                                        window
                                                                        wait
                                                                        until
                                                                        the next
                                                                        window
                                                                        opens.
                                                                    </p>
                                                                </div>
                                                                <NativeSelect
                                                                    id="sms_send_window"
                                                                    name="sms_send_window"
                                                                    defaultValue={
                                                                        branding.smsSendWindow
                                                                    }
                                                                    options={
                                                                        SEND_WINDOW_OPTIONS
                                                                    }
                                                                    className="sm:min-w-48"
                                                                />
                                                            </div>
                                                        </div>

                                                        <div className="grid gap-6">
                                                            <div className="rounded-xl border border-border bg-background p-4">
                                                                <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                                    Available
                                                                    placeholders
                                                                </p>
                                                                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                                                    {LOAN_SMS_PLACEHOLDERS.map(
                                                                        (
                                                                            item,
                                                                        ) => (
                                                                            <div
                                                                                key={
                                                                                    item.token
                                                                                }
                                                                                className="flex items-center justify-between gap-3 rounded-lg border border-border bg-background px-3 py-2 text-xs"
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
                                                                        Approved
                                                                        SMS
                                                                        template
                                                                    </Label>
                                                                    <Textarea
                                                                        id="loan_sms_approved_template"
                                                                        name="loan_sms_approved_template"
                                                                        className="min-h-[120px]"
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
                                                                        Leave
                                                                        blank to
                                                                        use the
                                                                        default
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
                                                                        Declined
                                                                        SMS
                                                                        template
                                                                    </Label>
                                                                    <Textarea
                                                                        id="loan_sms_declined_template"
                                                                        name="loan_sms_declined_template"
                                                                        className="min-h-[120px]"
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
                                                                        Leave
                                                                        blank to
                                                                        use the
                                                                        default
                                                                        template.
                                                                    </p>
                                                                    <InputError
                                                                        message={
                                                                            formErrors.loan_sms_declined_template
                                                                        }
                                                                    />
                                                                </div>
                                                            </div>

                                                            <div className="rounded-xl border border-border bg-background p-4">
                                                                <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                                    Preview
                                                                </p>
                                                                <div className="mt-3 space-y-4 text-sm">
                                                                    <div className="space-y-1">
                                                                        <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                                            Approved
                                                                        </p>
                                                                        <p className="text-sm text-foreground">
                                                                            {
                                                                                loanSmsApprovedPreview
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                    <div className="space-y-1">
                                                                        <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
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
                                                    </LoanRequestSectionCard>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2.5 border-t border-border bg-muted px-5 py-3 text-xs text-muted-foreground">
                                                    <Info
                                                        className="size-4 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    <span>
                                                        These settings apply to
                                                        every WIBS portal and
                                                        can only be changed by a
                                                        superadmin.
                                                    </span>
                                                </div>
                                            </div>

                                            <section
                                                aria-labelledby="org-danger-title"
                                                className="rounded-xl border border-[var(--bad-bd)] bg-card p-5 shadow-card"
                                            >
                                                <div className="flex flex-wrap items-baseline gap-2.5">
                                                    <h2
                                                        id="org-danger-title"
                                                        className="text-base font-bold"
                                                    >
                                                        Danger zone
                                                    </h2>
                                                    <span className="text-[13px] text-muted-foreground">
                                                        Organization-wide
                                                        actions that cannot be
                                                        undone here
                                                    </span>
                                                </div>
                                                <div className="mt-4 grid gap-3.5">
                                                    <div className="flex flex-wrap items-center gap-3.5">
                                                        <div className="min-w-0 flex-1">
                                                            <p className="text-sm font-bold">
                                                                Reset branding
                                                                to defaults
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                Restores the
                                                                WIBS palette and
                                                                default portal
                                                                mark. Applies
                                                                after you save.
                                                            </p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            className={
                                                                statusTones.bad
                                                            }
                                                            onClick={() => {
                                                                setResetWord(
                                                                    '',
                                                                );
                                                                setDangerKind(
                                                                    'brand',
                                                                );
                                                            }}
                                                        >
                                                            Reset branding
                                                        </Button>
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-3.5">
                                                        <div className="min-w-0 flex-1">
                                                            <p className="text-sm font-bold">
                                                                Clear all SMS
                                                                templates
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                Removes both
                                                                loan templates.
                                                                Members stop
                                                                receiving loan
                                                                status texts
                                                                until a template
                                                                is saved again.
                                                            </p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            className={
                                                                statusTones.bad
                                                            }
                                                            onClick={() => {
                                                                setResetWord(
                                                                    '',
                                                                );
                                                                setDangerKind(
                                                                    'sms',
                                                                );
                                                            }}
                                                        >
                                                            Clear templates
                                                        </Button>
                                                    </div>
                                                </div>
                                            </section>

                                            <div className="sticky bottom-4 z-10 flex flex-wrap items-center gap-2.5 rounded-xl border border-border bg-card px-4 py-3 shadow-lg">
                                                <span
                                                    className={cn(
                                                        'text-[13px] font-semibold',
                                                        hasChanges &&
                                                            'text-[var(--warn-ink)]',
                                                    )}
                                                >
                                                    {hasChanges
                                                        ? 'Unsaved changes'
                                                        : recentlySuccessful
                                                          ? 'Saved'
                                                          : 'All changes saved'}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    Changes apply to all portals
                                                    after you save.
                                                </span>
                                                <span className="flex-1" />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={
                                                        !hasChanges ||
                                                        processing
                                                    }
                                                    onClick={discardChanges}
                                                >
                                                    Discard changes
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        !hasChanges ||
                                                        processing
                                                    }
                                                >
                                                    Save changes
                                                </Button>
                                            </div>
                                        </>
                                    );
                                }}
                            </Form>
                        </div>

                        <aside
                            aria-labelledby="org-preview-title"
                            className="overflow-hidden rounded-xl border border-border bg-card shadow-card xl:sticky xl:top-4"
                        >
                            <div
                                className="flex items-center gap-2.5 p-4"
                                style={{
                                    backgroundColor: primarySwatch,
                                    color: readableInk(primarySwatch),
                                }}
                            >
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white">
                                    <img
                                        src={logoPreviewUrl}
                                        alt=""
                                        className="h-6 w-auto object-contain"
                                    />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-[17px] font-bold break-words">
                                        {companyNamePreview}
                                    </p>
                                    <p className="text-[13px] opacity-90">
                                        {portalLabelPreview}
                                    </p>
                                </div>
                            </div>
                            <div className="grid gap-3 p-4 text-sm">
                                <div className="flex items-center justify-between gap-2">
                                    <p
                                        id="org-preview-title"
                                        className="text-[13px] font-bold"
                                    >
                                        Live preview
                                    </p>
                                    <Badge
                                        variant="outline"
                                        className={statusTones.neutral}
                                    >
                                        {tabLabel(activeTab)}
                                    </Badge>
                                </div>

                                {activeTab === 'general' ? (
                                    <div className="grid gap-2.5">
                                        <PreviewRow
                                            label="Registration"
                                            value={liveValue(
                                                'registration_no',
                                                branding.registrationNo,
                                            )}
                                        />
                                        <PreviewRow
                                            label="TIN"
                                            value={liveValue(
                                                'business_tin',
                                                branding.businessTin,
                                            )}
                                        />
                                        <PreviewRow
                                            label="Place of signing"
                                            value={
                                                businessAddressPreview || '--'
                                            }
                                        />
                                        <PreviewRow
                                            label="App title"
                                            value={appTitlePreview || '--'}
                                        />
                                        <PreviewRow
                                            label="Short name"
                                            value={liveValue(
                                                'short_name',
                                                branding.shortName,
                                            )}
                                        />
                                        <PreviewRow
                                            label="Timezone"
                                            value={liveValue(
                                                'timezone',
                                                branding.timezone,
                                            )}
                                        />
                                        <PreviewRow
                                            label="Currency"
                                            value={liveValue(
                                                'statement_currency',
                                                branding.statementCurrency,
                                            )}
                                        />
                                    </div>
                                ) : null}

                                {activeTab === 'branding' ? (
                                    <div className="grid gap-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {ICON_PREVIEW_SIZES.map((size) => (
                                                <div
                                                    key={size}
                                                    className="flex size-10 items-center justify-center rounded-lg border border-border bg-background"
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
                                        <div className="flex flex-wrap gap-2">
                                            {[
                                                {
                                                    label: 'Primary',
                                                    color: primarySwatch,
                                                },
                                                {
                                                    label: 'Accent',
                                                    color: accentSwatch,
                                                },
                                                ...PALETTE_GROUPS.flatMap(
                                                    (group) => group.fields,
                                                ).map((field) => ({
                                                    label: field.label,
                                                    color: /^#[0-9a-f]{6}$/i.test(
                                                        palette[field.key],
                                                    )
                                                        ? palette[field.key]
                                                        : field.fallback,
                                                })),
                                            ].map((chip) => (
                                                <span
                                                    key={chip.label}
                                                    className="flex items-center gap-1.5 rounded-full border border-border bg-card py-1 pr-2.5 pl-1.5 text-xs font-semibold"
                                                >
                                                    <i
                                                        className="block size-3.5 rounded-full border border-black/20"
                                                        style={{
                                                            backgroundColor:
                                                                chip.color,
                                                        }}
                                                    />
                                                    {chip.label}
                                                </span>
                                            ))}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Applied to the portal, buttons,
                                            sidebar, and status badges after
                                            saving.
                                        </p>
                                    </div>
                                ) : null}

                                {activeTab === 'documents' ? (
                                    <div className="rounded-lg border border-border bg-muted p-3">
                                        <div className="rounded-md border border-slate-200 bg-white p-3 text-slate-900 shadow-sm">
                                            {reportHeaderDesignPreviewUrl ? (
                                                <img
                                                    src={
                                                        reportHeaderDesignPreviewUrl
                                                    }
                                                    alt="Report header design preview"
                                                    className="h-24 w-full object-contain"
                                                />
                                            ) : (
                                                <div className="flex h-24 items-center justify-center rounded-md border border-dashed border-slate-300 text-[11px] font-semibold tracking-[0.2em] text-slate-500 uppercase">
                                                    Application form
                                                </div>
                                            )}
                                            <div className="mt-3 grid gap-2 border-t border-slate-200 pt-3 text-xs">
                                                <div className="flex items-center justify-between">
                                                    <span
                                                        className="apply-font-report-label"
                                                        style={reportLabelStyle}
                                                    >
                                                        Member name
                                                    </span>
                                                    <span
                                                        className="apply-font-report-value font-semibold"
                                                        style={reportValueStyle}
                                                    >
                                                        Jane Doe
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span
                                                        className="apply-font-report-label"
                                                        style={reportLabelStyle}
                                                    >
                                                        Amount approved
                                                    </span>
                                                    <span
                                                        className="apply-font-report-value font-semibold"
                                                        style={reportValueStyle}
                                                    >
                                                        PHP 50,000.00
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ) : null}

                                {activeTab === 'contact' ? (
                                    <div className="grid gap-2.5">
                                        <PreviewRow
                                            label="Contact"
                                            value={liveValue(
                                                'support_contact_name',
                                                branding.supportContactName,
                                            )}
                                        />
                                        <PreviewRow
                                            label="Support"
                                            value={liveValue(
                                                'support_email',
                                                branding.supportEmail,
                                            )}
                                        />
                                        <PreviewRow
                                            label="Hotline"
                                            value={liveValue(
                                                'support_phone',
                                                branding.supportPhone,
                                            )}
                                        />
                                        <PreviewRow
                                            label="Hours"
                                            value={liveValue(
                                                'service_hours',
                                                branding.serviceHours,
                                            )}
                                        />
                                    </div>
                                ) : null}

                                {activeTab === 'messaging' ? (
                                    <div className="grid gap-3">
                                        <div>
                                            <p className="mb-1 text-xs text-muted-foreground">
                                                Approved text a member receives
                                            </p>
                                            <div className="rounded-xl rounded-bl-sm border border-border bg-muted px-3 py-2.5 text-[13px] leading-normal break-words">
                                                {loanSmsApprovedPreview}
                                            </div>
                                        </div>
                                        <div>
                                            <p className="mb-1 text-xs text-muted-foreground">
                                                Declined text a member receives
                                            </p>
                                            <div className="rounded-xl rounded-bl-sm border border-border bg-muted px-3 py-2.5 text-[13px] leading-normal break-words">
                                                {loanSmsDeclinedPreview}
                                            </div>
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        </aside>
                    </div>
                </div>

                <Dialog
                    open={dangerKind !== null}
                    onOpenChange={(open) => !open && setDangerKind(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {dangerKind === 'sms'
                                    ? 'Clear all SMS templates?'
                                    : 'Reset branding to defaults?'}
                            </DialogTitle>
                            <DialogDescription>
                                {dangerKind === 'sms'
                                    ? 'Removes the loan approved and declined templates. Members stop receiving loan status texts until a new template is saved. The change applies after you save.'
                                    : 'Primary, accent, and every palette color go back to the WIBS defaults on every portal. Custom colors are not recoverable from this screen. The change applies after you save.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="danger-confirm">
                                Type {dangerWord} to confirm
                            </Label>
                            <Input
                                id="danger-confirm"
                                value={resetWord}
                                onChange={(event) =>
                                    setResetWord(event.target.value)
                                }
                                autoComplete="off"
                                spellCheck={false}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDangerKind(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={resetWord.trim() !== dangerWord}
                                onClick={runDanger}
                            >
                                {dangerKind === 'sms'
                                    ? 'Clear templates'
                                    : 'Reset branding'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </PageShell>
        </AppLayout>
    );
}
