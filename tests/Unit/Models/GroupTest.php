<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\Group;

it('orders its cards by sort_order', function () {
    $group = Group::factory()->create();

    $second = Card::factory()->create(['group_id' => $group->id, 'sort_order' => 2]);
    $first = Card::factory()->create(['group_id' => $group->id, 'sort_order' => 1]);

    expect($group->cards->pluck('id')->all())->toBe([$first->id, $second->id]);
});
