<?php

it('defines sidebar tokens for the MRDINC theme', function () {
    $contents = file_get_contents(
        base_path('resources/js/theme/clients/mrdinc.ts'),
    );

    expect($contents)->toContain(
        "sidebar: '",
        "'sidebar-foreground':",
        "'sidebar-primary':",
        "'sidebar-primary-foreground':",
        "'sidebar-accent':",
        "'sidebar-accent-foreground':",
        "'sidebar-border':",
        "'sidebar-ring':",
    );
});
