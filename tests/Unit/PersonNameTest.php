<?php

use App\Support\PersonName;

test('formats first name, middle initial, and last name', function () {
    expect(PersonName::format('Emma', 'Alcantara', 'Requilme'))->toBe('Emma A. Requilme');
});

test('title-cases all-caps parts while abbreviating the middle name', function () {
    expect(PersonName::format('EMMA', 'ALCANTARA', 'REQUILME'))->toBe('Emma A. Requilme');
});

test('leaves already mixed-case parts untouched', function () {
    expect(PersonName::format('Juan', 'Paulo', 'Cruz'))->toBe('Juan P. Cruz');
});

test('omits the middle initial when there is no middle name', function () {
    expect(PersonName::format('Beneficiary', null, 'One'))->toBe('Beneficiary One');
    expect(PersonName::format('Beneficiary', '', 'One'))->toBe('Beneficiary One');
});

test('trims whitespace from each part', function () {
    expect(PersonName::format(' Emma ', ' Alcantara ', ' Requilme '))->toBe('Emma A. Requilme');
});

test('returns an empty string when all parts are blank', function () {
    expect(PersonName::format(null, null, null))->toBe('');
});
