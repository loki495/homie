<?php

declare(strict_types=1);

use App\Models\Card;

it('renders the dashboard in a real browser', function () {
    $page = visit('/');

    $page->assertSee('No cards yet');
});

it('renders a card once one exists', function () {
    Card::factory()->create(['group_id' => null]);

    $page = visit('/');

    $page->assertDontSee('No cards yet');
});
