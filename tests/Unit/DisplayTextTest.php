<?php

use App\Support\DisplayText;

test('normalize converts a fully ALL-CAPS string to title case', function (): void {
    expect(DisplayText::normalize('CECIL\'S DE GRACIA PHARMACY'))->toBe('Cecil\'S De Gracia Pharmacy');
    expect(DisplayText::normalize('JUAN DELA CRUZ'))->toBe('Juan Dela Cruz');
});

test('normalize leaves mixed-case and lowercase input untouched', function (): void {
    expect(DisplayText::normalize('Juan Dela Cruz'))->toBe('Juan Dela Cruz');
    expect(DisplayText::normalize('already lowercase'))->toBe('already lowercase');
    expect(DisplayText::normalize('IT Administrator'))->toBe('IT Administrator');
});

test('normalize handles null and blank values', function (): void {
    expect(DisplayText::normalize(null))->toBeNull();
    expect(DisplayText::normalize(''))->toBe('');
    expect(DisplayText::normalize('   '))->toBe('');
});

test('normalizeFields only touches listed string keys and leaves the rest alone', function (): void {
    $data = [
        'first_name' => 'JUAN',
        'civil_status' => 'MARRIED',
        'age' => 30,
        'nickname' => null,
    ];

    $normalized = DisplayText::normalizeFields($data, ['first_name', 'nickname', 'missing_key']);

    expect($normalized['first_name'])->toBe('Juan');
    expect($normalized['civil_status'])->toBe('MARRIED');
    expect($normalized['age'])->toBe(30);
    expect($normalized['nickname'])->toBeNull();
});
