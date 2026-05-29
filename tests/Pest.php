<?php

use App\LoanRequestPersonRole;
use App\Models\AdminSignature;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function testPngSignatureDataUrl(string $variant = 'one'): string
{
    $base64 = match ($variant) {
        'two' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=',
        default => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=',
    };

    return 'data:image/png;base64,'.$base64;
}

function testPngSignatureBinary(string $variant = 'one'): string
{
    $encoded = str_replace(
        'data:image/png;base64,',
        '',
        testPngSignatureDataUrl($variant),
    );
    $decoded = base64_decode($encoded, true);

    if ($decoded === false) {
        throw new RuntimeException('Unable to decode test signature data.');
    }

    return $decoded;
}

function testOpaqueWhiteSignatureBinary(): string
{
    if (! function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('GD is required to build opaque signature fixtures.');
    }

    $image = imagecreatetruecolor(160, 60);
    $white = imagecolorallocate($image, 255, 255, 255);
    $ink = imagecolorallocate($image, 17, 24, 39);
    imagefill($image, 0, 0, $white);
    imagesetthickness($image, 4);
    imageline($image, 28, 36, 82, 20, $ink);
    imageline($image, 80, 20, 110, 42, $ink);
    imageline($image, 108, 42, 136, 16, $ink);

    ob_start();
    imagepng($image);
    $binary = (string) ob_get_clean();
    imagedestroy($image);

    return $binary;
}

function testOpaqueWhiteSignatureDataUrl(): string
{
    return 'data:image/png;base64,'.base64_encode(testOpaqueWhiteSignatureBinary());
}

function pngHasTransparency(string $pngBinary): bool
{
    if (! function_exists('imagecreatefromstring')) {
        throw new RuntimeException('GD is required to inspect PNG transparency.');
    }

    $image = @imagecreatefromstring($pngBinary);

    if (! $image instanceof GdImage) {
        throw new RuntimeException('Unable to decode PNG image for inspection.');
    }

    try {
        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;

                if ($alpha > 0) {
                    return true;
                }
            }
        }

        return false;
    } finally {
        imagedestroy($image);
    }
}

/**
 * @return array{width: int, height: int}
 */
function pngDimensions(string $pngBinary): array
{
    $size = getimagesizefromstring($pngBinary);

    if ($size === false) {
        throw new RuntimeException('Unable to determine PNG dimensions.');
    }

    return [
        'width' => (int) ($size[0] ?? 0),
        'height' => (int) ($size[1] ?? 0),
    ];
}

function storeTestSignatureFile(string $path, string $variant = 'one'): string
{
    Storage::disk('public')->put($path, testPngSignatureBinary($variant));

    return $path;
}

function createActiveAdminSignatureRecord(
    AppUser $admin,
    string $variant = 'one',
): AdminSignature {
    $path = storeTestSignatureFile(
        sprintf(
            'loan-manager-signatures/%d/%s-%s.png',
            $admin->user_id,
            $variant,
            uniqid('', true),
        ),
        $variant,
    );

    AdminSignature::query()
        ->where('user_id', $admin->user_id)
        ->update(['is_active' => false]);

    return AdminSignature::factory()->create([
        'user_id' => $admin->user_id,
        'signature_path' => $path,
        'is_active' => true,
    ]);
}

function prepareLoanRequestForApproval(
    LoanRequest $loanRequest,
    AppUser $admin,
): void {
    createActiveAdminSignatureRecord($admin);

    $loanRequest->loadMissing('people');

    foreach ([
        LoanRequestPersonRole::Applicant,
        LoanRequestPersonRole::CoMakerOne,
        LoanRequestPersonRole::CoMakerTwo,
    ] as $role) {
        $person = $loanRequest->people
            ->first(fn (LoanRequestPerson $item): bool => $item->role === $role);

        if ($person === null) {
            $person = LoanRequestPerson::factory()
                ->forLoanRequest($loanRequest)
                ->role($role)
                ->create();
        }

        $person->update([
            'signature_path' => storeTestSignatureFile(
                sprintf(
                    'loan-requests/signatures/%d-%s.png',
                    $loanRequest->id,
                    $role->value,
                ),
                match ($role) {
                    LoanRequestPersonRole::Applicant => 'one',
                    LoanRequestPersonRole::CoMakerOne => 'two',
                    LoanRequestPersonRole::CoMakerTwo => 'one',
                },
            ),
        ]);
    }
}
