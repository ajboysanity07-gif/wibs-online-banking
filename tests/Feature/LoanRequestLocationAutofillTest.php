<?php

test('loan request location fields wire province autofill', function () {
    $contents = file_get_contents(
        base_path('resources/js/components/loan-request/loan-request-fields.tsx'),
    );

    expect($contents)->toContain('birthplaceProvinceSearch.setSelectedValue');
    expect($contents)->toContain("onChange('birthplace_province'");
    expect($contents)->toContain('addressProvinceSearch.setSelectedValue');
    expect($contents)->toContain("onChange('address3'");
    expect($contents)->toContain('employerProvinceSearch.setSelectedValue');
    expect($contents)->toContain("onChange('employer_business_address3'");
});
