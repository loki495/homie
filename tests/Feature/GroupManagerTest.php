<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\Group;
use Livewire\Livewire;

it('creates a group', function () {
    Livewire::test('group-manager')
        ->set('name', 'Media')
        ->call('save');

    expect(Group::where('name', 'Media')->exists())->toBeTrue();
});

it('requires a name', function () {
    Livewire::test('group-manager')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name');
});

it('renames an existing group', function () {
    $group = Group::factory()->create(['name' => 'Old name']);

    Livewire::test('group-manager')
        ->call('edit', $group->id)
        ->set('name', 'New name')
        ->call('save');

    expect($group->fresh()->name)->toBe('New name');
});

it('deletes a group', function () {
    $group = Group::factory()->create();

    Livewire::test('group-manager')->call('delete', $group->id);

    expect(Group::find($group->id))->toBeNull();
});

it('ungroups cards when their group is deleted', function () {
    $group = Group::factory()->create();
    $card = Card::factory()->create(['group_id' => $group->id]);

    Livewire::test('group-manager')->call('delete', $group->id);

    expect($card->fresh()->group_id)->toBeNull();
});
