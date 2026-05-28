<?php

namespace App\Services;

use App\Models\OrganizationSetting;
use App\Support\LocationComposer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OrganizationSettingsService
{
    private const DEFAULT_PORTAL_LABEL = 'Member Portal';

    private const DEFAULT_LOAN_SMS_APPROVED_TEMPLATE = '{company_name} {portal_label}: Your loan request ({loan_reference}) has been APPROVED for {approved_amount} payable over {approved_term} months. Please visit the {office_name} office to finalize your loan.';

    private const DEFAULT_LOAN_SMS_DECLINED_TEMPLATE = '{company_name} {portal_label}: Your loan request ({loan_reference}) has been DECLINED. For questions or clarification, please contact the {office_name} office.';

    public const LOGO_PRESET_MARK = 'mark';

    public const LOGO_PRESET_FULL = 'full';

    private const DEFAULT_LOGO_PRESET = self::LOGO_PRESET_MARK;

    private const LOGO_MARK_ASSET = 'mrdinc-logo-mark.png';

    private const LOGO_FULL_ASSET = 'mrdinc-logo.png';

    private const DEFAULT_FAVICON_ASSET = 'favicon.ico';

    private const REPORT_FONT_DEFAULT_FAMILY = 'DejaVu Sans';

    private const REPORT_FONT_WEIGHT_OPTIONS = [
        300,
        400,
        500,
        600,
        700,
        800,
        900,
    ];

    private const REPORT_LABEL_DEFAULT_WEIGHT = 400;

    private const REPORT_VALUE_DEFAULT_WEIGHT = 600;

    private const REPORT_LABEL_DEFAULT_SIZE = 8;

    private const REPORT_VALUE_DEFAULT_SIZE = 10;

    private const REPORT_FONT_MIN_SIZE = 6;

    private const REPORT_FONT_MAX_SIZE = 24;

    /**
     * @return array<string, mixed>
     */
    public function branding(): array
    {
        try {
            return $this->mapBranding($this->currentSetting());
        } catch (Throwable $exception) {
            Log::warning('Organization branding lookup failed. Using fallback branding.', [
                'exception' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return $this->fallbackBranding();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackBranding(): array
    {
        return $this->mapBranding(null);
    }

    /**
     * @return array<string, string|null>
     */
    public function defaultAttributes(): array
    {
        return [
            'company_name' => $this->defaultCompanyName(),
            'business_address' => null,
            'business_address1' => null,
            'business_address2' => null,
            'business_address3' => null,
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
            'loan_sms_approved_template' => self::DEFAULT_LOAN_SMS_APPROVED_TEMPLATE,
            'loan_sms_declined_template' => self::DEFAULT_LOAN_SMS_DECLINED_TEMPLATE,
            'report_header_design_path' => null,
            'report_label_font_color' => null,
            'report_value_font_color' => null,
            'report_label_font_family' => null,
            'report_label_font_variant' => null,
            'report_label_font_weight' => null,
            'report_label_font_size' => null,
            'report_value_font_family' => null,
            'report_value_font_variant' => null,
            'report_value_font_weight' => null,
            'report_value_font_size' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBranding(?OrganizationSetting $setting): array
    {
        $companyName = $this->resolveCompanyName($setting?->company_name);
        $businessAddress = $this->resolveBusinessAddress($setting);
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
        $reportHeader = $this->resolveReportHeader($setting);
        $reportTypography = $this->resolveReportTypography($setting);
        $loanSmsTemplates = $this->resolveLoanSmsTemplates($setting);

        return [
            'companyName' => $companyName,
            'businessAddress' => $businessAddress['address'],
            'businessAddress1' => $businessAddress['address1'],
            'businessAddress2' => $businessAddress['address2'],
            'businessAddress3' => $businessAddress['address3'],
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
            'reportHeader' => $reportHeader,
            'reportTypography' => $reportTypography,
            'general' => [
                'companyName' => $companyName,
                'businessAddress' => $businessAddress['address'],
                'businessAddress1' => $businessAddress['address1'],
                'businessAddress2' => $businessAddress['address2'],
                'businessAddress3' => $businessAddress['address3'],
                'portalLabel' => $portalLabel,
                'appTitle' => $this->resolveAppTitle($companyName, $portalLabel),
            ],
            'assets' => [
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
            ],
            'contact' => [
                'supportEmail' => $this->normalizeValue(
                    $setting?->support_email,
                ),
                'supportPhone' => $this->normalizeValue(
                    $setting?->support_phone,
                ),
                'supportContactName' => $this->normalizeValue(
                    $setting?->support_contact_name,
                ),
            ],
            'reports' => [
                'header' => $reportHeader,
                'typography' => $reportTypography,
            ],
            'communications' => [
                'loanSmsTemplates' => $loanSmsTemplates,
            ],
        ];
    }

    /**
     * @return array{
     *     address: string|null,
     *     address1: string|null,
     *     address2: string|null,
     *     address3: string|null
     * }
     */
    private function resolveBusinessAddress(?OrganizationSetting $setting): array
    {
        $address1 = $this->normalizeValue($setting?->business_address1);
        $address2 = $this->normalizeValue($setting?->business_address2);
        $address3 = $this->normalizeValue($setting?->business_address3);
        $legacyAddress = $this->normalizeValue($setting?->business_address);

        if (
            $address1 === null
            && $address2 === null
            && $address3 === null
            && $legacyAddress !== null
        ) {
            $parsed = LocationComposer::parseLegacyAddress($legacyAddress);
            $address1 = $parsed['address1'];
            $address2 = $parsed['address2'];
            $address3 = $parsed['address3'];
        }

        $address = $address1 !== null || $address2 !== null || $address3 !== null
            ? LocationComposer::compose($address1, $address2, $address3)
            : $legacyAddress;

        return [
            'address' => $address !== '' ? $address : null,
            'address1' => $address1,
            'address2' => $address2,
            'address3' => $address3,
        ];
    }

    /**
     * @return array{approved: string, declined: string}
     */
    public function loanSmsTemplates(?OrganizationSetting $setting = null): array
    {
        $setting = $setting ?? OrganizationSetting::query()->first();

        return $this->resolveLoanSmsTemplates($setting);
    }

    public function resolveMessagePrefix(string $companyName, string $portalLabel): string
    {
        $companyName = $this->normalizeValue($companyName);
        $portalLabel = $this->normalizeValue($portalLabel);

        if ($portalLabel !== null && $companyName !== null) {
            if (Str::contains(Str::lower($portalLabel), Str::lower($companyName))) {
                return $portalLabel;
            }

            return trim(sprintf('%s %s', $companyName, $portalLabel));
        }

        return $portalLabel ?? $companyName ?? '';
    }

    public function resolvePortalLabelForMessage(
        string $companyName,
        string $portalLabel,
    ): string {
        $companyName = $this->normalizeValue($companyName);
        $portalLabel = $this->normalizeValue($portalLabel);

        if ($portalLabel === null) {
            return $companyName ?? '';
        }

        if ($companyName === null) {
            return $portalLabel;
        }

        if (! Str::contains(Str::lower($portalLabel), Str::lower($companyName))) {
            return $portalLabel;
        }

        $stripped = str_ireplace($companyName, '', $portalLabel);
        $stripped = preg_replace('/\\s{2,}/', ' ', trim($stripped));
        $stripped = trim($stripped ?? '', '-: ');

        return $stripped !== '' ? $stripped : $portalLabel;
    }

    public function resolveOfficeName(string $companyName, string $portalLabel): string
    {
        $companyName = $this->normalizeValue($companyName);

        if ($companyName !== null) {
            return $companyName;
        }

        $portalLabel = $this->normalizeValue($portalLabel);

        return $portalLabel ?? 'coop';
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

    protected function currentSetting(): ?OrganizationSetting
    {
        return OrganizationSetting::query()->first();
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

    /**
     * @return array{approved: string, declined: string}
     */
    private function resolveLoanSmsTemplates(?OrganizationSetting $setting): array
    {
        return [
            'approved' => $this->resolveLoanSmsTemplate(
                $setting?->loan_sms_approved_template,
                self::DEFAULT_LOAN_SMS_APPROVED_TEMPLATE,
            ),
            'declined' => $this->resolveLoanSmsTemplate(
                $setting?->loan_sms_declined_template,
                self::DEFAULT_LOAN_SMS_DECLINED_TEMPLATE,
            ),
        ];
    }

    private function resolveLoanSmsTemplate(?string $value, string $fallback): string
    {
        $normalized = $this->normalizeValue($value);

        return $normalized ?? $fallback;
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

    /**
     * @return array{
     *     designPath: ?string,
     *     designUrl: ?string,
     *     designData: ?string
     * }
     */
    private function resolveReportHeader(?OrganizationSetting $setting): array
    {
        $storedPath = $this->normalizeValue(
            $setting?->report_header_design_path,
        );

        if (
            $storedPath === null
            || ! Storage::disk('public')->exists($storedPath)
        ) {
            return [
                'designPath' => null,
                'designUrl' => null,
                'designData' => null,
            ];
        }

        return [
            'designPath' => $storedPath,
            'designUrl' => Storage::disk('public')->url($storedPath),
            'designData' => $this->buildReportHeaderDesignDataUri($storedPath),
        ];
    }

    private function buildReportHeaderDesignDataUri(string $storedPath): ?string
    {
        if (! Storage::disk('public')->exists($storedPath)) {
            return null;
        }

        $contents = Storage::disk('public')->get($storedPath);
        $mimeType = $this->resolveLogoMimeType($storedPath);

        return sprintf(
            'data:%s;base64,%s',
            $mimeType,
            base64_encode($contents),
        );
    }

    /**
     * @return array{
     *     googleFontUrl: ?string,
     *     label: array{
     *         family: string,
     *         variant: string,
     *         weight: int,
     *         size: int,
     *         color: ?string,
     *         cssFamily: string,
     *         cssStyle: string
     *     },
     *     value: array{
     *         family: string,
     *         variant: string,
     *         weight: int,
     *         size: int,
     *         color: ?string,
     *         cssFamily: string,
     *         cssStyle: string
     *     }
     * }
     */
    private function resolveReportTypography(
        ?OrganizationSetting $setting,
    ): array {
        $labelColor = $this->normalizeHexColor(
            $setting?->report_label_font_color,
        );
        $valueColor = $this->normalizeHexColor(
            $setting?->report_value_font_color,
        );

        $reportTypography = [
            'label' => array_merge(
                $this->resolveReportFont(
                    $setting?->report_label_font_family,
                    $setting?->report_label_font_variant,
                    $setting?->report_label_font_weight,
                    $setting?->report_label_font_size,
                    self::REPORT_LABEL_DEFAULT_SIZE,
                    self::REPORT_LABEL_DEFAULT_WEIGHT,
                ),
                ['color' => $labelColor],
            ),
            'value' => array_merge(
                $this->resolveReportFont(
                    $setting?->report_value_font_family,
                    $setting?->report_value_font_variant,
                    $setting?->report_value_font_weight,
                    $setting?->report_value_font_size,
                    self::REPORT_VALUE_DEFAULT_SIZE,
                    self::REPORT_VALUE_DEFAULT_WEIGHT,
                ),
                ['color' => $valueColor],
            ),
        ];

        $reportTypography['googleFontUrl'] = $this->buildGoogleFontUrl([
            $reportTypography['label'],
            $reportTypography['value'],
        ]);

        return $reportTypography;
    }

    /**
     * @return array{
     *     family: string,
     *     variant: string,
     *     weight: int,
     *     size: int,
     *     cssFamily: string,
     *     cssStyle: string
     * }
     */
    private function resolveReportFont(
        ?string $family,
        ?string $variant,
        ?string $weight,
        ?int $size,
        int $defaultSize,
        int $defaultWeight,
    ): array {
        $resolvedFamily = $this->normalizeFontFamily($family)
            ?? self::REPORT_FONT_DEFAULT_FAMILY;
        $resolvedVariant = $this->normalizeFontVariant($variant);
        $resolvedWeight = $this->normalizeFontWeight($weight, $defaultWeight);
        $resolvedSize = $this->normalizeFontSize($size, $defaultSize);

        return [
            'family' => $resolvedFamily,
            'variant' => $resolvedVariant,
            'weight' => $resolvedWeight,
            'size' => $resolvedSize,
            'cssFamily' => $this->buildFontStack($resolvedFamily),
            'cssStyle' => $resolvedVariant === 'italic' ? 'italic' : 'normal',
        ];
    }

    private function normalizeHexColor(?string $value): ?string
    {
        $normalized = $this->normalizeValue($value);

        if ($normalized === null) {
            return null;
        }

        $lower = strtolower($normalized);

        if (! str_starts_with($lower, '#')) {
            $lower = '#'.$lower;
        }

        if (preg_match('/^#([0-9a-f]{3})$/', $lower, $matches) === 1) {
            $expanded = '';

            foreach (str_split($matches[1]) as $char) {
                $expanded .= $char.$char;
            }

            return '#'.$expanded;
        }

        if (preg_match('/^#[0-9a-f]{6}$/', $lower) !== 1) {
            return null;
        }

        return $lower;
    }

    private function normalizeFontFamily(?string $value): ?string
    {
        $normalized = $this->normalizeValue($value);

        if ($normalized === null) {
            return null;
        }

        $sanitized = preg_replace('/[^A-Za-z0-9\\s\\-+&.]/', '', $normalized);

        if ($sanitized === null) {
            return null;
        }

        $sanitized = trim($sanitized);

        return $sanitized === '' ? null : mb_substr($sanitized, 0, 100);
    }

    private function normalizeFontWeight(?string $weight, int $default): int
    {
        if ($weight === null || trim($weight) === '') {
            return $default;
        }

        $value = (int) $weight;

        return in_array($value, self::REPORT_FONT_WEIGHT_OPTIONS, true)
            ? $value
            : $default;
    }

    private function normalizeFontVariant(?string $variant): string
    {
        $value = strtolower(trim((string) $variant));

        if ($value === '' || $value === 'regular' || $value === 'normal') {
            return 'regular';
        }

        if ($value === 'italic' || str_ends_with($value, 'italic')) {
            return 'italic';
        }

        return 'regular';
    }

    private function normalizeFontSize(?int $size, int $default): int
    {
        if ($size === null) {
            return $default;
        }

        if ($size < self::REPORT_FONT_MIN_SIZE) {
            return self::REPORT_FONT_MIN_SIZE;
        }

        if ($size > self::REPORT_FONT_MAX_SIZE) {
            return self::REPORT_FONT_MAX_SIZE;
        }

        return $size;
    }

    private function buildFontStack(string $family): string
    {
        $sanitized = str_replace('"', '', $family);
        $fallback = self::REPORT_FONT_DEFAULT_FAMILY;

        if (strcasecmp($sanitized, $fallback) === 0) {
            return sprintf('"%s", sans-serif', $fallback);
        }

        return sprintf('"%s", "%s", sans-serif', $sanitized, $fallback);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fonts
     */
    private function buildGoogleFontUrl(array $fonts): ?string
    {
        $googleFontFamilies = [];

        foreach ($fonts as $font) {
            $family = trim((string) ($font['family'] ?? ''));

            if ($family === '' || strcasecmp($family, self::REPORT_FONT_DEFAULT_FAMILY) === 0) {
                continue;
            }

            if (! preg_match('/^[A-Za-z0-9\\s\\-+&.]+$/', $family)) {
                continue;
            }

            $googleFontFamilies[$family] = true;
        }

        if ($googleFontFamilies === []) {
            return null;
        }

        $googleFontParams = [];

        foreach (array_keys($googleFontFamilies) as $family) {
            $encodedFamily = rawurlencode($family);
            $encodedFamily = str_replace('%20', '+', $encodedFamily);
            $googleFontParams[] = sprintf('family=%s', $encodedFamily);
        }

        return sprintf(
            'https://fonts.googleapis.com/css2?%s&display=swap',
            implode('&', $googleFontParams),
        );
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
