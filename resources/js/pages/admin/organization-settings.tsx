import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import OrganizationSettingsController from '@/actions/App/Http/Controllers/Admin/OrganizationSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useBranding } from '@/hooks/use-branding';
import AppLayout from '@/layouts/app-layout';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import { dashboard } from '@/routes/admin';
import { organization as organizationSettings } from '@/routes/admin/settings';
import { mrdincTheme } from '@/theme/clients/mrdinc';
import type { BreadcrumbItem, LogoPreset } from '@/types';

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
const LOGO_ALLOWED_TYPES = new Set([
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

const normalizeHexInputValue = (
    value: string | null | undefined,
): string => {
    if (!value) {
        return '';
    }

    const normalized = normalizeHexValue(value);

    return normalized ?? value.trim();
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
    const primaryInputValue = brandPrimaryTouched
        ? brandPrimaryValue
        : normalizeHexInputValue(branding.brandPrimaryColor);
    const accentInputValue = brandAccentTouched
        ? brandAccentValue
        : normalizeHexInputValue(branding.brandAccentColor);
    const normalizedPrimary = normalizeHexValue(primaryInputValue);
    const normalizedAccent = normalizeHexValue(accentInputValue);
    const primarySwatch = normalizedPrimary ?? DEFAULT_BRAND_PRIMARY;
    const accentSwatch = normalizedAccent ?? DEFAULT_BRAND_ACCENT;
    const brandPrimaryHelpId = 'brand_primary_color_help';
    const brandAccentHelpId = 'brand_accent_color_help';
    const logoMarkPreviewUrl =
        logoMarkPreview ??
        (logoMarkReset ? branding.logoMarkDefaultUrl : branding.logoMarkUrl);
    const logoFullPreviewUrl =
        logoFullPreview ??
        (logoFullReset ? branding.logoFullDefaultUrl : branding.logoFullUrl);
    const logoPreviewUrl =
        logoPreset === 'full' ? logoFullPreviewUrl : logoMarkPreviewUrl;
    const showCompanyNamePreview = logoPreset !== 'full';
    const companyNamePreview =
        companyNameValue.trim() !== ''
            ? companyNameValue.trim()
            : branding.companyName;
    const portalLabelPreview =
        portalLabelValue.trim() !== ''
            ? portalLabelValue.trim()
            : branding.portalLabel;
    const faviconPreviewUrl =
        faviconPreview ??
        (faviconReset ? branding.faviconDefaultUrl : branding.faviconUrl);
    const logoMarkIsDefault =
        logoMarkReset || (!logoMarkPreview && branding.logoMarkIsDefault);
    const logoFullIsDefault =
        logoFullReset || (!logoFullPreview && branding.logoFullIsDefault);
    const hasStoredFavicon = branding.faviconPath !== null;

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
        setLogoPreset(branding.logoPreset);
        setCompanyNameValue(branding.companyName);
        setPortalLabelValue(branding.portalLabel);
        setLogoMarkPreview(null);
        setLogoFullPreview(null);
        setLogoMarkReset(false);
        setLogoFullReset(false);
        setFaviconReset(false);
    }, [branding.companyName, branding.logoPreset, branding.portalLabel]);

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

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Organization branding"
                    description="Manage organization identity, portal labeling, and support details shown to members."
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
                    <Card>
                    <CardHeader>
                        <CardTitle>Brand settings</CardTitle>
                        <CardDescription>
                            Configure identity, portal labeling, and support
                            details shown across member experiences and
                            reports.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...OrganizationSettingsController.update.form()}
                            options={{ preserveScroll: true }}
                            encType="multipart/form-data"
                            onChange={() => setHasChanges(true)}
                            onSuccess={() => {
                                showSuccessToast(
                                    adminToastCopy.success.updated('Branding'),
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
                                setHasChanges(false);
                            }}
                            onError={(formErrors) => {
                                showErrorToast(
                                    formErrors,
                                    adminToastCopy.error.updated('branding'),
                                    { id: 'organization-branding-update' },
                                );
                            }}
                            className="space-y-10"
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
                                const primaryError =
                                    formErrors.brand_primary_color ??
                                    primaryClientError;
                                const accentError =
                                    formErrors.brand_accent_color ??
                                    accentClientError;
                                const primaryInvalid =
                                    primaryError !== undefined;
                                const accentInvalid = accentError !== undefined;

                                return (
                                    <>
                                        <div className="space-y-6">
                                            <div className="space-y-1">
                                            <h3 className="text-base font-semibold">
                                                Organization identity
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                Company name used across the
                                                portal and reports.
                                            </p>
                                        </div>

                                        <div className="grid gap-6">
                                            <div className="grid gap-2">
                                                <Label htmlFor="company_name">
                                                    Company name
                                                </Label>
                                                <Input
                                                    id="company_name"
                                                    name="company_name"
                                                    value={companyNameValue}
                                                    onChange={(event) => {
                                                        setCompanyNameValue(
                                                            event.target.value,
                                                        );
                                                        setHasChanges(true);
                                                    }}
                                                    placeholder="Company name"
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.company_name
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="space-y-6">
                                        <div className="space-y-1">
                                            <h3 className="text-base font-semibold">
                                                Brand assets
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                Choose the primary logo and
                                                portal icon used throughout the
                                                member experience.
                                            </p>
                                        </div>

                                        <div className="grid gap-6">
                                            <div className="grid gap-3">
                                                <Label>Primary logo</Label>
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

                                                            return (
                                                                <div
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    role="radio"
                                                                    aria-checked={
                                                                        isSelected
                                                                    }
                                                                    tabIndex={
                                                                        0
                                                                    }
                                                                    onClick={() => {
                                                                        setLogoPreset(
                                                                            option.value,
                                                                        );
                                                                        setHasChanges(
                                                                            true,
                                                                        );
                                                                    }}
                                                                    onKeyDown={(
                                                                        event,
                                                                    ) => {
                                                                        if (
                                                                            event.key ===
                                                                                'Enter' ||
                                                                            event.key ===
                                                                                ' '
                                                                        ) {
                                                                            event.preventDefault();
                                                                            setLogoPreset(
                                                                                option.value,
                                                                            );
                                                                            setHasChanges(
                                                                                true,
                                                                            );
                                                                        }
                                                                    }}
                                                                    className={`flex cursor-pointer flex-col gap-4 rounded-xl border p-4 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 ${
                                                                        isSelected
                                                                            ? 'border-primary/70 bg-primary/5 ring-1 ring-primary/30'
                                                                            : 'border-border hover:border-primary/40'
                                                                    }`}
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
                                                                                className="text-[10px] uppercase"
                                                                            >
                                                                                Selected
                                                                            </Badge>
                                                                        ) : (
                                                                            <span className="text-[10px] uppercase text-muted-foreground">
                                                                                Choose
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                    <div className="flex h-20 items-center justify-center rounded-lg border border-border/70 bg-muted/40">
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
                                                                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                                                        <span>
                                                                            {isDefault
                                                                                ? 'Default asset'
                                                                                : 'Custom asset'}
                                                                        </span>
                                                                        {isReset ? (
                                                                            <span className="text-primary">
                                                                                Reset after save
                                                                            </span>
                                                                        ) : null}
                                                                    </div>
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
                                                                                Remove selection
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
                                                <input
                                                    type="hidden"
                                                    name="logo_preset"
                                                    value={logoPreset}
                                                />
                                                <p className="text-sm text-muted-foreground">
                                                    The selected logo appears
                                                    on member forms, navigation,
                                                    and reports.
                                                </p>
                                                <InputError
                                                    message={
                                                        formErrors.logo_preset
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-3">
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div className="space-y-1">
                                                        <Label htmlFor="favicon">
                                                            Portal icon
                                                        </Label>
                                                        <p className="text-sm text-muted-foreground">
                                                            Used in browser tabs
                                                            and compact app
                                                            surfaces.
                                                        </p>
                                                    </div>
                                                    {faviconReset ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-[10px] uppercase"
                                                        >
                                                            Default
                                                        </Badge>
                                                    ) : hasStoredFavicon ||
                                                      faviconPreview ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-[10px] uppercase"
                                                        >
                                                            Custom
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <div className="rounded-xl border border-border/60 bg-muted/30 p-4">
                                                    <div className="flex flex-wrap items-center justify-between gap-4">
                                                        <div className="flex flex-wrap items-center gap-4">
                                                            <div className="flex h-12 w-12 items-center justify-center rounded-lg border border-border/70 bg-background">
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
                                                                    (size) => (
                                                                        <div
                                                                            key={
                                                                                size
                                                                            }
                                                                            className="flex h-9 w-9 items-center justify-center rounded-md border border-border/70 bg-background"
                                                                        >
                                                                            <img
                                                                                src={
                                                                                    faviconPreviewUrl
                                                                                }
                                                                                alt={`${branding.appTitle} ${size}px`}
                                                                                className="object-contain"
                                                                                style={{
                                                                                    width:
                                                                                        size,
                                                                                    height:
                                                                                        size,
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
                                                                Upload icon
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
                                                                    Remove selection
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
                                                        Upload a JPG, PNG,
                                                        WebP, or ICO image (max
                                                        1MB).
                                                    </p>
                                                </div>
                                                {faviconReset ? (
                                                    <p className="text-xs text-primary">
                                                        Default icon will be
                                                        used after saving.
                                                    </p>
                                                ) : null}
                                                <input
                                                    id="favicon"
                                                    ref={faviconInputRef}
                                                    name="favicon"
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon"
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
                                                    message={formErrors.favicon}
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="space-y-6">
                                        <div className="space-y-1">
                                            <h3 className="text-base font-semibold">
                                                Portal appearance
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                Customize how the portal label
                                                appears in navigation and
                                                headers.
                                            </p>
                                        </div>

                                        <div className="grid gap-6 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="portal_label">
                                                    Portal label
                                                </Label>
                                                <Input
                                                    id="portal_label"
                                                    name="portal_label"
                                                    value={portalLabelValue}
                                                    onChange={(event) => {
                                                        setPortalLabelValue(
                                                            event.target.value,
                                                        );
                                                        setHasChanges(true);
                                                    }}
                                                    placeholder="Member Portal"
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.portal_label
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="space-y-6">
                                        <div className="space-y-1">
                                            <h3 className="text-base font-semibold">
                                                Support contact
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                Optional contact details shown
                                                on the welcome and sign-in
                                                screens.
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
                                    </div>

                                    <Separator />

                                    <div className="space-y-6">
                                        <div className="space-y-1">
                                            <h3 className="text-base font-semibold">
                                                Theme colors
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                Stored for future dynamic
                                                theming. The current theme
                                                remains unchanged.
                                            </p>
                                        </div>

                                        <div className="grid gap-6 md:grid-cols-2">
                                            <div className="grid gap-3">
                                                <Label htmlFor="brand_primary_color">
                                                    Brand primary color
                                                </Label>
                                                <p
                                                    id={brandPrimaryHelpId}
                                                    className="text-sm text-muted-foreground"
                                                >
                                                    Used for primary actions.
                                                    Default:{' '}
                                                    {DEFAULT_BRAND_PRIMARY}.
                                                </p>
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <input
                                                        id="brand_primary_color_picker"
                                                        type="color"
                                                        value={primarySwatch}
                                                        aria-label="Brand primary color picker"
                                                        aria-describedby={
                                                            brandPrimaryHelpId
                                                        }
                                                        className="h-9 w-9 cursor-pointer rounded-md border border-border bg-transparent p-0"
                                                        onChange={(event) => {
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
                                                        value={primaryInputValue}
                                                        onChange={(event) => {
                                                            setBrandPrimaryTouched(
                                                                true,
                                                            );
                                                            setBrandPrimaryValue(
                                                                event.target.value,
                                                            );
                                                        }}
                                                        onBlur={(event) => {
                                                            setBrandPrimaryTouched(
                                                                true,
                                                            );
                                                            setBrandPrimaryValue(
                                                                normalizeHexInputValue(
                                                                    event.target
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
                                                        spellCheck={false}
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
                                                            setHasChanges(true);
                                                            setBrandPrimaryValue(
                                                                DEFAULT_BRAND_PRIMARY,
                                                            );
                                                        }}
                                                    >
                                                        Reset
                                                    </Button>
                                                </div>
                                                <InputError
                                                    message={primaryError}
                                                />
                                            </div>

                                            <div className="grid gap-3">
                                                <Label htmlFor="brand_accent_color">
                                                    Brand accent color
                                                </Label>
                                                <p
                                                    id={brandAccentHelpId}
                                                    className="text-sm text-muted-foreground"
                                                >
                                                    Used for accents and
                                                    highlights. Default:{' '}
                                                    {DEFAULT_BRAND_ACCENT}.
                                                </p>
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <input
                                                        id="brand_accent_color_picker"
                                                        type="color"
                                                        value={accentSwatch}
                                                        aria-label="Brand accent color picker"
                                                        aria-describedby={
                                                            brandAccentHelpId
                                                        }
                                                        className="h-9 w-9 cursor-pointer rounded-md border border-border bg-transparent p-0"
                                                        onChange={(event) => {
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
                                                        value={accentInputValue}
                                                        onChange={(event) => {
                                                            setBrandAccentTouched(
                                                                true,
                                                            );
                                                            setBrandAccentValue(
                                                                event.target.value,
                                                            );
                                                        }}
                                                        onBlur={(event) => {
                                                            setBrandAccentTouched(
                                                                true,
                                                            );
                                                            setBrandAccentValue(
                                                                normalizeHexInputValue(
                                                                    event.target
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
                                                        spellCheck={false}
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
                                                            setHasChanges(true);
                                                            setBrandAccentValue(
                                                                DEFAULT_BRAND_ACCENT,
                                                            );
                                                        }}
                                                    >
                                                        Reset
                                                    </Button>
                                                </div>
                                                <InputError
                                                    message={accentError}
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="sticky bottom-4 z-10 rounded-xl border border-border/60 bg-background/90 p-4 backdrop-blur">
                                        <div className="flex flex-wrap items-center justify-between gap-4">
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                {hasChanges ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[10px] uppercase"
                                                    >
                                                        Unsaved changes
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-[10px] uppercase"
                                                    >
                                                        Up to date
                                                    </Badge>
                                                )}
                                                <Transition
                                                    show={recentlySuccessful}
                                                    enter="transition ease-in-out"
                                                    enterFrom="opacity-0"
                                                    leave="transition ease-in-out"
                                                    leaveTo="opacity-0"
                                                >
                                                    <span>Saved</span>
                                                </Transition>
                                            </div>
                                            <Button disabled={processing}>
                                                Save changes
                                            </Button>
                                        </div>
                                    </div>
                                    </>
                                );
                            }}
                        </Form>
                    </CardContent>
                </Card>

                <div className="space-y-6 lg:sticky lg:top-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Live preview</CardTitle>
                            <CardDescription>
                                Review how the portal and reports will look
                                before saving changes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="space-y-2">
                                <p className="text-xs font-semibold uppercase text-muted-foreground">
                                    Portal header
                                </p>
                                <div className="rounded-xl border border-border/60 bg-muted/30 p-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-lg border border-border/60 bg-background">
                                            <img
                                                src={logoPreviewUrl}
                                                alt={`${companyNamePreview} logo`}
                                                className={`w-auto object-contain ${
                                                    logoPreset === 'full'
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

                            <div className="space-y-2">
                                <p className="text-xs font-semibold uppercase text-muted-foreground">
                                    Portal icon
                                </p>
                                <div className="flex flex-wrap items-center gap-3 rounded-xl border border-border/60 bg-muted/30 p-4">
                                    {ICON_PREVIEW_SIZES.map((size) => (
                                        <div
                                            key={size}
                                            className="flex h-10 w-10 items-center justify-center rounded-md border border-border/70 bg-background"
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
                                <p className="text-xs font-semibold uppercase text-muted-foreground">
                                    Report header
                                </p>
                                <div className="rounded-xl border border-border/60 bg-background p-4">
                                    <div className="flex items-center gap-3">
                                        <img
                                            src={logoPreviewUrl}
                                            alt={`${companyNamePreview} report logo`}
                                            className={`w-auto object-contain ${
                                                logoPreset === 'full'
                                                    ? 'h-10'
                                                    : 'h-8'
                                            }`}
                                        />
                                        {showCompanyNamePreview ? (
                                            <p className="text-sm font-semibold">
                                                {companyNamePreview}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="mt-3 h-px bg-border/60" />
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        Loan request PDF preview
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
            </div>
        </AppLayout>
    );
}
