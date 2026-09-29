<?php

// Surfaces (background, card, sidebar, borders) live in resources/css/app.css;
// the MRDINC client theme only overrides the brand-driven sidebar tokens.
it('defines sidebar tokens for the MRDINC theme', function () {
    $theme = file_get_contents(
        base_path('resources/js/theme/clients/mrdinc.ts'),
    );
    $css = file_get_contents(base_path('resources/css/app.css'));

    expect($theme)->toContain(
        "'sidebar-primary':",
        "'sidebar-primary-foreground':",
        "'sidebar-accent':",
        "'sidebar-accent-foreground':",
        "'sidebar-ring':",
    );

    expect($css)->toContain(
        '--sidebar:',
        '--sidebar-foreground:',
        '--sidebar-border:',
    );
});
