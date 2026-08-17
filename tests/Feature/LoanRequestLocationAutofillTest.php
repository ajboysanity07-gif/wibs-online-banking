<?php

test('loan request location fields wire province autofill', function () {
    $contents = file_get_contents(
        base_path('resources/js/components/loan-request/loan-request-fields.tsx'),
    );

    // Prettier may wrap a long onChange(...) call across multiple lines, so
    // this tolerates whitespace (including newlines) between the opening
    // paren and the field name -- this test only cares that the wiring
    // exists, not its formatting.
    expect($contents)->toContain('birthplaceProvinceSearch.setSelectedValue');
    expect($contents)->toMatch("/onChange\(\s*'birthplace_province'/");
    expect($contents)->toContain('addressProvinceSearch.setSelectedValue');
    expect($contents)->toMatch("/onChange\(\s*'address3'/");
    expect($contents)->toContain('employerProvinceSearch.setSelectedValue');
    expect($contents)->toMatch("/onChange\(\s*'employer_business_address3'/");
});
