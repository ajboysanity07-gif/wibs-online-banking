import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import OrganizationSettingsController from '@/actions/App/Http/Controllers/Admin/OrganizationSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import type { BreadcrumbItem } from '@/types';

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

const LOGO_MAX_BYTES = 2 * 1024 * 1024;
const LOGO_ALLOWED_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
]);
const FAVICON_MAX_BYTES = 1024 * 1024;
const FAVICON_ALLOWED_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/x-icon',
    'image/vnd.microsoft.icon',
]);
const DEFAULT_BRAND_PRIMARY = mrdincTheme.hex.primary.toLowerCase();
const DEFAULT_BRAND_ACCENT = mrdincTheme.hex.accent.toLowerCase();
const PRIMARY_COLOR_ERROR =
    'Primary color must be a valid hex value (e.g., #1a2b3c).';
const ACCENT_COLOR_ERROR =
    'Accent color must be a valid hex value (e.g., #1a2b3c).';

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
    const logoInputRef = useRef<HTMLInputElement>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);
    const faviconInputRef = useRef<HTMLInputElement>(null);
    const [faviconPreview, setFaviconPreview] = useState<string | null>(null);
    const [brandPrimaryValue, setBrandPrimaryValue] = useState(() =>
        normalizeHexInputValue(branding.brandPrimaryColor),
    );
    const [brandPrimaryTouched, setBrandPrimaryTouched] = useState(false);
    const [brandAccentValue, setBrandAccentValue] = useState(() =>
        normalizeHexInputValue(branding.brandAccentColor),
    );
    const [brandAccentTouched, setBrandAccentTouched] = useState(false);
    const normalizedPrimary = normalizeHexValue(brandPrimaryValue);
    const normalizedAccent = normalizeHexValue(brandAccentValue);
    const primarySwatch = normalizedPrimary ?? DEFAULT_BRAND_PRIMARY;
    const accentSwatch = normalizedAccent ?? DEFAULT_BRAND_ACCENT;
    const brandPrimaryHelpId = 'brand_primary_color_help';
    const brandAccentHelpId = 'brand_accent_color_help';

    useEffect(() => {
        if (!logoPreview) {
            return;
        }

        return () => {
            URL.revokeObjectURL(logoPreview);
        };
    }, [logoPreview]);

    useEffect(() => {
        if (!faviconPreview) {
            return;
        }

        return () => {
            URL.revokeObjectURL(faviconPreview);
        };
    }, [faviconPreview]);

    useEffect(() => {
        setBrandPrimaryValue(
            normalizeHexInputValue(branding.brandPrimaryColor),
        );
        setBrandPrimaryTouched(false);
    }, [branding.brandPrimaryColor]);

    useEffect(() => {
        setBrandAccentValue(
            normalizeHexInputValue(branding.brandAccentColor),
        );
        setBrandAccentTouched(false);
    }, [branding.brandAccentColor]);

    const handleLogoChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (!LOGO_ALLOWED_TYPES.has(file.type)) {
            showErrorToast(
                null,
                'Please select a JPG, PNG, or WebP image.',
                {
                    id: 'organization-logo-type',
                },
            );
            event.target.value = '';
            return;
        }

        if (file.size > LOGO_MAX_BYTES) {
            showErrorToast(null, 'Image must be 2MB or smaller.', {
                id: 'organization-logo-size',
            });
            event.target.value = '';
            return;
        }

        setLogoPreview(URL.createObjectURL(file));
    };

    const clearLogoPreview = () => {
        setLogoPreview(null);

        if (logoInputRef.current) {
            logoInputRef.current.value = '';
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

        setFaviconPreview(URL.createObjectURL(file));
    };

    const clearFaviconPreview = () => {
        setFaviconPreview(null);

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

                <Card>
                    <CardHeader>
                        <CardTitle>Branding settings</CardTitle>
                        <CardDescription>
                            These settings control branding, portal labeling,
                            and support contact details across the member
                            experience.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...OrganizationSettingsController.update.form()}
                            options={{ preserveScroll: true }}
                            encType="multipart/form-data"
                            onSuccess={() => {
                                showSuccessToast(
                                    adminToastCopy.success.updated('Branding'),
                                    {
                                        id: 'organization-branding-update',
                                    },
                                );
                                clearLogoPreview();
                                clearFaviconPreview();
                                setBrandPrimaryTouched(false);
                                setBrandAccentTouched(false);
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
                                    brandPrimaryValue.trim() !== '' &&
                                    normalizedPrimary === null
                                        ? PRIMARY_COLOR_ERROR
                                        : undefined;
                                const accentClientError =
                                    brandAccentTouched &&
                                    brandAccentValue.trim() !== '' &&
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
                                                Company name and logo shown
                                                across the portal.
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
                                                    defaultValue={
                                                        branding.companyName
                                                    }
                                                    placeholder="Company name"
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.company_name
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-3">
                                                <Label htmlFor="company_logo">
                                                    Company logo
                                                </Label>
                                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                                                    <div className="flex h-20 w-20 items-center justify-center rounded-lg border border-border bg-muted/40">
                                                        <img
                                                            src={
                                                                logoPreview ??
                                                                branding.logoUrl
                                                            }
                                                            alt={
                                                                branding.appTitle
                                                            }
                                                            className="h-14 w-auto object-contain"
                                                        />
                                                    </div>
                                                    <div className="space-y-2 text-sm text-muted-foreground">
                                                        <p>
                                                            Upload a JPG, PNG,
                                                            or WebP image (max
                                                            2MB).
                                                        </p>
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    logoInputRef.current?.click()
                                                                }
                                                            >
                                                                Change logo
                                                            </Button>
                                                            {logoPreview ? (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={
                                                                        clearLogoPreview
                                                                    }
                                                                >
                                                                    Reset preview
                                                                </Button>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                </div>
                                                <input
                                                    id="company_logo"
                                                    ref={logoInputRef}
                                                    name="company_logo"
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp"
                                                    className="sr-only"
                                                    onChange={handleLogoChange}
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.company_logo
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="space-y-6">
                                        <div className="space-y-1">
                                            <h3 className="text-base font-semibold">
                                                Portal branding
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                Set the portal label and
                                                favicon shown in the browser.
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
                                                    defaultValue={
                                                        branding.portalLabel
                                                    }
                                                    placeholder="Member Portal"
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.portal_label
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-3 md:col-span-2">
                                                <Label htmlFor="favicon">
                                                    Portal favicon
                                                </Label>
                                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                                                    <div className="flex h-14 w-14 items-center justify-center rounded-lg border border-border bg-muted/40">
                                                        <img
                                                            src={
                                                                faviconPreview ??
                                                                branding.faviconUrl
                                                            }
                                                            alt={`${branding.appTitle} favicon`}
                                                            className="h-8 w-8 object-contain"
                                                        />
                                                    </div>
                                                    <div className="space-y-2 text-sm text-muted-foreground">
                                                        <p>
                                                            Upload a JPG, PNG,
                                                            WebP, or ICO image
                                                            (max 1MB).
                                                        </p>
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    faviconInputRef.current?.click()
                                                                }
                                                            >
                                                                Change favicon
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
                                                                    Reset preview
                                                                </Button>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                </div>
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
                                                Future theme settings
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
                                                        value={brandPrimaryValue}
                                                        onChange={(event) => {
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
                                                        value={brandAccentValue}
                                                        onChange={(event) => {
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

                                    <div className="flex items-center gap-4">
                                        <Button disabled={processing}>
                                            Save changes
                                        </Button>
                                        <Transition
                                            show={recentlySuccessful}
                                            enter="transition ease-in-out"
                                            enterFrom="opacity-0"
                                            leave="transition ease-in-out"
                                            leaveTo="opacity-0"
                                        >
                                            <p className="text-sm text-muted-foreground">
                                                Saved
                                            </p>
                                        </Transition>
                                    </div>
                                    </>
                                );
                            }}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
