<?php

declare(strict_types=1);
use Tests\TestCase;

it('renders the home page with the dashboard and sidebar', function () {
    /** @var TestCase $this */
    $this->withoutVite();

    $response = $this->get('/');

    $response->assertOk()
        ->assertSeeLivewire('dashboard')
        ->assertSeeLivewire('sidebar');
});
