<?php

namespace App\Services;

use App\Models\OrganizationSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrganizationSettingsService
{
    private const DEFAULT_PORTAL_LABEL = 'Member Portal';

    public const LOGO_PRESET_MARK = 'mark';

    public const LOGO_PRESET_FULL = 'full';

    private const DEFAULT_LOGO_PRESET = self::LOGO_PRESET_MARK;

    private const LOGO_MARK_ASSET = 'mrdinc-logo-mark.png';

    private const LOGO_FULL_ASSET = 'mrdinc-logo.png';

    private const DEFAULT_FAVICON_ASSET = 'favicon.ico';

    /**
     * @return array{
     *     companyName: string,
     *     portalLabel: string,
     *     appTitle: string,
     *     logoPreset: string,
     *     logoIsWordmark: bool,
     *     logoPath: ?string,
     *     logoUrl: string,
     *     logoMarkUrl: string,
     *     logoFullUrl: string,
     *     logoMarkDefaultUrl: string,
     *     logoFullDefaultUrl: string,
     *     logoMarkIsDefault: bool,
     *     logoFullIsDefault: bool,
     *     faviconPath: ?string,
     *     faviconUrl: string,
     *     faviconDefaultUrl: string,
     *     brandPrimaryColor: ?string,
     *     brandAccentColor: ?string,
     *     supportEmail: ?string,
     *     supportPhone: ?string,
     *     supportContactName: ?string
     * }
     */
    public function branding(): array
    {
        $setting = OrganizationSetting::query()->first();

        return $this->mapBranding($setting);
    }

    /**
     * @return array<string, string|null>
     */
    public function defaultAttributes(): array
    {
        return [
            'company_name' => $this->defaultCompanyName(),
            'company_logo_path' => null,
            'logo_preset' => self::DEFAULT_LOGO_PRESET,
            'logo_mark_path' => null,
            'logo_full_path' => null,
            'portal_label' => self::DEFAULT_PORTAL_LABEL,
            'favicon_path' => null,
            'brand_primary_color' => null,
            'brand_accent_color' => null,
            'support_email' => null,
            'support_phone' => null,
            'support_contact_name' => null,
        ];
    }

    /**
     * @return array{
     *     companyName: string,
     *     portalLabel: string,
     *     appTitle: string,
     *     logoPreset: string,
     *     logoIsWordmark: bool,
     *     logoPath: ?string,
     *     logoUrl: string,
     *     logoMarkUrl: string,
     *     logoFullUrl: string,
     *     logoMarkDefaultUrl: string,
     *     logoFullDefaultUrl: string,
     *     logoMarkIsDefault: bool,
     *     logoFullIsDefault: bool,
     *     faviconPath: ?string,
     *     faviconUrl: string,
     *     faviconDefaultUrl: string,
     *     brandPrimaryColor: ?string,
     *     brandAccentColor: ?string,
     *     supportEmail: ?string,
     *     supportPhone: ?string,
     *     supportContactName: ?string
     * }
     */
    private function mapBranding(?OrganizationSetting $setting): array
    {
        $companyName = $this->resolveCompanyName($setting?->company_name);
        $portalLabel = $this->resolvePortalLabel($setting?->portal_label);
        $logoPreset = $this->resolveLogoPreset($setting?->logo_preset);
        $faviconPath = $this->normalizeValue($setting?->favicon_path);
        $markLogo = $this->resolveLogoVariant(
            $setting?->logo_mark_path,
            self::LOGO_MARK_ASSET,
        );
        $fullLogo = $this->resolveLogoVariant(
            $setting?->logo_full_path,
            self::LOGO_FULL_ASSET,
        );
        $activeLogo = $logoPreset === self::LOGO_PRESET_FULL
            ? $fullLogo
            : $markLogo;

        return [
            'companyName' => $companyName,
            'portalLabel' => $portalLabel,
            'appTitle' => $this->resolveAppTitle($companyName, $portalLabel),
            'logoPreset' => $logoPreset,
            'logoIsWordmark' => $logoPreset === self::LOGO_PRESET_FULL,
            'logoPath' => $activeLogo['path'],
            'logoUrl' => $activeLogo['url'],
            'logoMarkUrl' => $markLogo['url'],
            'logoFullUrl' => $fullLogo['url'],
            'logoMarkDefaultUrl' => asset(self::LOGO_MARK_ASSET),
            'logoFullDefaultUrl' => asset(self::LOGO_FULL_ASSET),
            'logoMarkIsDefault' => $markLogo['isDefault'],
            'logoFullIsDefault' => $fullLogo['isDefault'],
            'faviconPath' => $faviconPath,
            'faviconUrl' => $this->resolveFaviconUrl($faviconPath),
            'faviconDefaultUrl' => asset(self::DEFAULT_FAVICON_ASSET),
            'brandPrimaryColor' => $this->normalizeValue(
                $setting?->brand_primary_color,
            ),
            'brandAccentColor' => $this->normalizeValue(
                $setting?->brand_accent_color,
            ),
            'supportEmail' => $this->normalizeValue($setting?->support_email),
            'supportPhone' => $this->normalizeValue($setting?->support_phone),
            'supportContactName' => $this->normalizeValue(
                $setting?->support_contact_name,
            ),
        ];
    }

    public function logoDataUri(): ?string
    {
        $setting = OrganizationSetting::query()->first();
        $logoPreset = $this->resolveLogoPreset($setting?->logo_preset);
        $logoPath = $logoPreset === self::LOGO_PRESET_FULL
            ? $setting?->logo_full_path
            : $setting?->logo_mark_path;
        $fallbackAsset = $logoPreset === self::LOGO_PRESET_FULL
            ? self::LOGO_FULL_ASSET
            : self::LOGO_MARK_ASSET;
        $logoSource = $this->resolveLogoContents($logoPath, $fallbackAsset);

        if ($logoSource['contents'] === null) {
            return null;
        }

        $mimeType = $this->resolveLogoMimeType(
            $logoSource['path'] ?? $fallbackAsset,
        );

        return sprintf(
            'data:%s;base64,%s',
            $mimeType,
            base64_encode($logoSource['contents']),
        );
    }

    private function resolveCompanyName(?string $companyName): string
    {
        $normalized = $this->normalizeValue($companyName);

        if ($normalized !== null) {
            return $normalized;
        }

        return $this->defaultCompanyName();
    }

    private function resolvePortalLabel(?string $portalLabel): string
    {
        $normalized = $this->normalizeValue($portalLabel);

        if ($normalized !== null) {
            return $normalized;
        }

        return self::DEFAULT_PORTAL_LABEL;
    }

    private function resolveAppTitle(string $companyName, string $portalLabel): string
    {
        $portalLabel = $this->normalizeValue($portalLabel);

        if ($portalLabel === null) {
            return $companyName;
        }

        if (Str::contains(Str::lower($portalLabel), Str::lower($companyName))) {
            return $portalLabel;
        }

        return trim(sprintf('%s - %s', $portalLabel, $companyName));
    }

    private function resolveLogoPreset(?string $logoPreset): string
    {
        $preset = $this->normalizeValue($logoPreset);

        if ($preset === self::LOGO_PRESET_MARK) {
            return self::LOGO_PRESET_MARK;
        }

        if ($preset === self::LOGO_PRESET_FULL) {
            return self::LOGO_PRESET_FULL;
        }

        if ($preset === 'mrdinc_mark') {
            return self::LOGO_PRESET_MARK;
        }

        if ($preset === 'mrdinc_full') {
            return self::LOGO_PRESET_FULL;
        }

        return self::DEFAULT_LOGO_PRESET;
    }

    /**
     * @return list<string>
     */
    public function logoPresets(): array
    {
        return [
            self::LOGO_PRESET_MARK,
            self::LOGO_PRESET_FULL,
        ];
    }

    /**
     * @return array{url: string, path: ?string, isDefault: bool}
     */
    private function resolveLogoVariant(
        ?string $storedPath,
        string $fallbackAsset,
    ): array {
        if ($storedPath !== null && Storage::disk('public')->exists($storedPath)) {
            return [
                'url' => Storage::disk('public')->url($storedPath),
                'path' => $storedPath,
                'isDefault' => false,
            ];
        }

        return [
            'url' => asset($fallbackAsset),
            'path' => null,
            'isDefault' => true,
        ];
    }

    /**
     * @return array{contents: ?string, path: ?string, isDefault: bool}
     */
    private function resolveLogoContents(
        ?string $storedPath,
        string $fallbackAsset,
    ): array {
        if ($storedPath !== null && Storage::disk('public')->exists($storedPath)) {
            return [
                'contents' => Storage::disk('public')->get($storedPath),
                'path' => $storedPath,
                'isDefault' => false,
            ];
        }

        $fallbackPath = public_path($fallbackAsset);

        if (! is_file($fallbackPath)) {
            return [
                'contents' => null,
                'path' => null,
                'isDefault' => true,
            ];
        }

        $contents = file_get_contents($fallbackPath);

        if ($contents === false) {
            return [
                'contents' => null,
                'path' => null,
                'isDefault' => true,
            ];
        }

        return [
            'contents' => $contents,
            'path' => $fallbackAsset,
            'isDefault' => true,
        ];
    }

    private function resolveLogoMimeType(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }

    private function resolveFaviconUrl(?string $faviconPath): string
    {
        if ($faviconPath !== null) {
            return Storage::disk('public')->url($faviconPath);
        }

        return asset(self::DEFAULT_FAVICON_ASSET);
    }

    private function defaultCompanyName(): string
    {
        $appName = trim((string) config('app.name', ''));

        if ($appName === '') {
            return '';
        }

        $baseName = trim(Str::before($appName, ' Portal'));

        return $baseName !== '' ? $baseName : $appName;
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
