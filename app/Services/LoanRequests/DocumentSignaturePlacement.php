<?php

namespace App\Services\LoanRequests;

class DocumentSignaturePlacement
{
    /**
     * Shared signature scale factor for generated PDF and Excel documents.
     */
    public const SIGNATURE_SCALE_FACTOR = 2.0;

    /**
     * @param  array{
     *     scale?: float|int|null,
     *     max_width?: float|int|null,
     *     max_height?: float|int|null,
     *     offset_x?: float|int|null,
     *     offset_y?: float|int|null
     * }  $options
     * @return array{x: float, y: float, width: float, height: float}
     */
    public function calculateFromImagePath(
        string $absolutePath,
        float $x,
        float $y,
        float $width,
        float $height,
        array $options = [],
    ): array {
        $size = @getimagesize($absolutePath);

        if ($size === false || ($size[0] ?? 0) <= 0 || ($size[1] ?? 0) <= 0) {
            return $this->fallbackPlacement($x, $y, $width, $height, $options);
        }

        return $this->calculateFromDimensions(
            (float) $size[0],
            (float) $size[1],
            $x,
            $y,
            $width,
            $height,
            $options,
        );
    }

    /**
     * @param  array{
     *     scale?: float|int|null,
     *     max_width?: float|int|null,
     *     max_height?: float|int|null,
     *     offset_x?: float|int|null,
     *     offset_y?: float|int|null
     * }  $options
     * @return array{x: float, y: float, width: float, height: float}
     */
    public function calculateFromDimensions(
        float $imageWidth,
        float $imageHeight,
        float $x,
        float $y,
        float $width,
        float $height,
        array $options = [],
    ): array {
        if ($imageWidth <= 0 || $imageHeight <= 0 || $width <= 0 || $height <= 0) {
            return $this->fallbackPlacement($x, $y, $width, $height, $options);
        }

        $scale = $this->positiveFloat($options['scale'] ?? null)
            ?? self::SIGNATURE_SCALE_FACTOR;
        $targetWidth = $width * $scale;
        $targetHeight = $height * $scale;
        $maximumWidth = $this->positiveFloat($options['max_width'] ?? null);
        $maximumHeight = $this->positiveFloat($options['max_height'] ?? null);

        if ($maximumWidth !== null) {
            $targetWidth = min($targetWidth, $maximumWidth);
        }

        if ($maximumHeight !== null) {
            $targetHeight = min($targetHeight, $maximumHeight);
        }

        if ($targetWidth <= 0 || $targetHeight <= 0) {
            return $this->fallbackPlacement($x, $y, $width, $height, $options);
        }

        $imageRatio = $imageWidth / max($imageHeight, 0.0001);
        $targetRatio = $targetWidth / max($targetHeight, 0.0001);

        if ($imageRatio > $targetRatio) {
            $renderWidth = $targetWidth;
            $renderHeight = $targetWidth / $imageRatio;
        } else {
            $renderHeight = $targetHeight;
            $renderWidth = $targetHeight * $imageRatio;
        }

        $centerX = $x + ($width / 2);
        $centerY = $y + ($height / 2);
        $offsetX = $this->floatValue($options['offset_x'] ?? null) ?? 0.0;
        $offsetY = $this->floatValue($options['offset_y'] ?? null) ?? 0.0;

        return [
            'x' => round($centerX - ($renderWidth / 2) + $offsetX, 3),
            'y' => round($centerY - ($renderHeight / 2) + $offsetY, 3),
            'width' => round($renderWidth, 3),
            'height' => round($renderHeight, 3),
        ];
    }

    /**
     * @param  array{
     *     offset_x?: float|int|null,
     *     offset_y?: float|int|null
     * }  $options
     * @return array{x: float, y: float, width: float, height: float}
     */
    private function fallbackPlacement(
        float $x,
        float $y,
        float $width,
        float $height,
        array $options,
    ): array {
        $offsetX = $this->floatValue($options['offset_x'] ?? null) ?? 0.0;
        $offsetY = $this->floatValue($options['offset_y'] ?? null) ?? 0.0;

        return [
            'x' => round($x + $offsetX, 3),
            'y' => round($y + $offsetY, 3),
            'width' => round($width, 3),
            'height' => round($height, 3),
        ];
    }

    private function positiveFloat(mixed $value): ?float
    {
        $number = $this->floatValue($value);

        if ($number === null || $number <= 0) {
            return null;
        }

        return $number;
    }

    private function floatValue(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
