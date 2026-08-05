<?php

use App\Support\LocationComposer;

test('compose joins street, city, and province without barangay', function (): void {
    expect(LocationComposer::compose('123 Main St', 'Manila', 'Metro Manila'))
        ->toBe('123 Main St, Manila, Metro Manila');
});

test('compose inserts barangay between street and city when given', function (): void {
    expect(LocationComposer::compose('123 Main St', 'Manila', 'Metro Manila', 'Barangay Uno'))
        ->toBe('123 Main St, Barangay Uno, Manila, Metro Manila');
});

test('compose omits barangay when blank', function (): void {
    expect(LocationComposer::compose('123 Main St', 'Manila', 'Metro Manila', ''))
        ->toBe('123 Main St, Manila, Metro Manila');
    expect(LocationComposer::compose('123 Main St', 'Manila', 'Metro Manila', null))
        ->toBe('123 Main St, Manila, Metro Manila');
});

test('composeBirthplace is unaffected by the barangay parameter', function (): void {
    expect(LocationComposer::composeBirthplace('Cebu City', 'Cebu'))
        ->toBe('Cebu City, Cebu');
});
