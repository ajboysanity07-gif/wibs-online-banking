<?php

use Inertia\Testing\AssertableInertia as Assert;

test('renders the welcome page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('name', 'MRDINC Portal')
            ->has('canRegister'));
});
