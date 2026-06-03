<?php

use App\Services\SignaturePngService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

test('signature png service removes white background and trims whitespace', function () {
    $service = app(SignaturePngService::class);
    $normalizedBinary = $service->normalizeBase64Png(
        testOpaqueWhiteSignatureDataUrl(),
    );

    expect($normalizedBinary)->toBeString();
    expect(pngHasTransparency($normalizedBinary))->toBeTrue();

    $dimensions = pngDimensions($normalizedBinary);

    expect($dimensions['width'])->toBeLessThan(160);
    expect($dimensions['height'])->toBeLessThan(60);
});

test('signature png service prepares cleaned overlay image variants', function () {
    Storage::fake('public');

    $relativePath = 'loan-requests/signatures/overlay-white-background.png';
    Storage::disk('public')->put($relativePath, testOpaqueWhiteSignatureBinary());
    $absolutePath = Storage::disk('public')->path($relativePath);
    $service = app(SignaturePngService::class);
    $overlayImage = $service->prepareOverlayImage($absolutePath);

    expect($overlayImage['temporary'])->toBeTrue();
    expect($overlayImage['path'])->not->toBe($absolutePath);
    expect(File::exists($overlayImage['path']))->toBeTrue();

    $cleanedBinary = File::get($overlayImage['path']);

    expect(pngHasTransparency($cleanedBinary))->toBeTrue();

    $dimensions = pngDimensions($cleanedBinary);

    expect($dimensions['width'])->toBeLessThan(160);
    expect($dimensions['height'])->toBeLessThan(60);

    File::delete($overlayImage['path']);
});
