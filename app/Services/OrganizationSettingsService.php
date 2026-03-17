<?php

namespace App\Services;

use App\Models\OrganizationSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrganizationSettingsService
{
    private const DEFAULT_PORTAL_LABEL = 'Member Portal';

    private const DEFAULT_LOGO_ASSET = 'mrdinc-logo-mark.png';

    private const DEFAULT_FAVICON_ASSET = 'favicon.ico';

    /**
     * @return array{
     *     companyName: string,
     *     portalLabel: string,
     *     appTitle: string,
     *     logoPath: ?string,
     *     logoUrl: string,
     *     faviconPath: ?string,
     *     faviconUrl: string,
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
     *     logoPath: ?string,
     *     logoUrl: string,
     *     faviconPath: ?string,
     *     faviconUrl: string,
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
        $logoPath = $this->normalizeValue($setting?->company_logo_path);
        $faviconPath = $this->normalizeValue($setting?->favicon_path);

        return [
            'companyName' => $companyName,
            'portalLabel' => $portalLabel,
            'appTitle' => $this->resolveAppTitle($companyName, $portalLabel),
            'logoPath' => $logoPath,
            'logoUrl' => $this->resolveLogoUrl($logoPath),
            'faviconPath' => $faviconPath,
            'faviconUrl' => $this->resolveFaviconUrl($faviconPath),
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
        $logoPath = OrganizationSetting::query()
            ->value('company_logo_path');
        $logoPath = $this->normalizeValue($logoPath);
        $logoFilePath = $this->resolveLogoFilePath($logoPath);

        if ($logoFilePath === null) {
            return null;
        }

        $contents = file_get_contents($logoFilePath);

        if ($contents === false) {
            return null;
        }

        $mimeType = $this->resolveLogoMimeType($logoFilePath);

        return sprintf(
            'data:%s;base64,%s',
            $mimeType,
            base64_encode($contents),
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

    private function resolveLogoUrl(?string $logoPath): string
    {
        if ($logoPath !== null) {
            return Storage::disk('public')->url($logoPath);
        }

        return asset(self::DEFAULT_LOGO_ASSET);
    }

    private function resolveLogoFilePath(?string $logoPath): ?string
    {
        if ($logoPath !== null) {
            if (! Storage::disk('public')->exists($logoPath)) {
                return null;
            }

            return Storage::disk('public')->path($logoPath);
        }

        $fallbackPath = public_path(self::DEFAULT_LOGO_ASSET);

        return is_file($fallbackPath) ? $fallbackPath : null;
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
