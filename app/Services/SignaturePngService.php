<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SignaturePngService
{
    private const WHITE_THRESHOLD = 245;

    private const MIN_VISIBLE_ALPHA = 120;

    private const MIN_TRIM_PADDING = 8;

    public function normalizeBase64Png(string $data): ?string
    {
        if (! str_starts_with($data, 'data:image/png;base64,')) {
            return null;
        }

        $encoded = substr($data, strlen('data:image/png;base64,'));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || $decoded === '') {
            return null;
        }

        return $this->normalizePngBinary($decoded);
    }

    public function normalizePngBinary(string $pngBinary): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $pngBinary;
        }

        $sourceImage = @imagecreatefromstring($pngBinary);

        if (! $sourceImage instanceof GdImage) {
            return null;
        }

        $cleanedImage = null;

        try {
            $cleanedImage = $this->removeWhiteBackgroundAndTrim($sourceImage);

            return $this->encodePng($cleanedImage);
        } finally {
            imagedestroy($sourceImage);

            if ($cleanedImage instanceof GdImage) {
                imagedestroy($cleanedImage);
            }
        }
    }

    /**
     * @return array{path: string, temporary: bool}
     */
    public function prepareOverlayImage(string $absolutePath): array
    {
        $contents = @file_get_contents($absolutePath);

        if (! is_string($contents) || $contents === '') {
            return [
                'path' => $absolutePath,
                'temporary' => false,
            ];
        }

        $normalizedBinary = $this->normalizePngBinary($contents);

        if (! is_string($normalizedBinary) || $normalizedBinary === '') {
            return [
                'path' => $absolutePath,
                'temporary' => false,
            ];
        }

        $directory = storage_path('app/tmp/signature-overlays');
        File::ensureDirectoryExists($directory);
        $temporaryPath = $directory.DIRECTORY_SEPARATOR.Str::uuid().'.png';
        file_put_contents($temporaryPath, $normalizedBinary);

        return [
            'path' => $temporaryPath,
            'temporary' => true,
        ];
    }

    private function removeWhiteBackgroundAndTrim(GdImage $sourceImage): GdImage
    {
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $processedImage = imagecreatetruecolor($width, $height);
        $this->prepareTransparentCanvas($processedImage, $width, $height);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($sourceImage, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                if (
                    $alpha >= 127
                    || $this->isNearWhite($red, $green, $blue)
                ) {
                    imagesetpixel(
                        $processedImage,
                        $x,
                        $y,
                        imagecolorallocatealpha(
                            $processedImage,
                            255,
                            255,
                            255,
                            127,
                        ),
                    );

                    continue;
                }

                imagesetpixel(
                    $processedImage,
                    $x,
                    $y,
                    imagecolorallocatealpha(
                        $processedImage,
                        $red,
                        $green,
                        $blue,
                        $alpha,
                    ),
                );

                if ($alpha < self::MIN_VISIBLE_ALPHA) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            return $processedImage;
        }

        $padding = max(
            self::MIN_TRIM_PADDING,
            (int) round(min($width, $height) * 0.03),
        );
        $cropX = max(0, $minX - $padding);
        $cropY = max(0, $minY - $padding);
        $cropWidth = min($width - $cropX, ($maxX - $minX + 1) + ($padding * 2));
        $cropHeight = min(
            $height - $cropY,
            ($maxY - $minY + 1) + ($padding * 2),
        );
        $croppedImage = imagecrop($processedImage, [
            'x' => $cropX,
            'y' => $cropY,
            'width' => $cropWidth,
            'height' => $cropHeight,
        ]);

        if (! $croppedImage instanceof GdImage) {
            return $processedImage;
        }

        imagedestroy($processedImage);
        imagealphablending($croppedImage, false);
        imagesavealpha($croppedImage, true);

        return $croppedImage;
    }

    private function prepareTransparentCanvas(
        GdImage $image,
        int $width,
        int $height,
    ): void {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle(
            $image,
            0,
            0,
            $width,
            $height,
            imagecolorallocatealpha($image, 255, 255, 255, 127),
        );
    }

    private function isNearWhite(int $red, int $green, int $blue): bool
    {
        return $red >= self::WHITE_THRESHOLD
            && $green >= self::WHITE_THRESHOLD
            && $blue >= self::WHITE_THRESHOLD;
    }

    private function encodePng(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
