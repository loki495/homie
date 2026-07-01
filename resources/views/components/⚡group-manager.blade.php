<?php

use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    public ?int $editingId = null;

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            Group::findOrFail($this->editingId)->update(['name' => $this->name]);
        } else {
            Group::create([
                'name' => $this->name,
                'sort_order' => (Group::max('sort_order') ?? -1) + 1,
            ]);
        }

        $this->reset('name', 'editingId');
        $this->dispatch('dashboard-updated');
    }

    public function edit(int $groupId): void
    {
        $group = Group::findOrFail($groupId);
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->dispatch('scroll-sidebar-top');
    }

    public function cancel(): void
    {
        $this->reset('name', 'editingId');
    }

    public function delete(int $groupId): void
    {
        Group::findOrFail($groupId)->delete();

        if ($this->editingId === $groupId) {
            $this->reset('name', 'editingId');
        }

        $this->dispatch('dashboard-updated');
    }

    /**
     * @return Collection<int, Group>
     */
    public function groups(): Collection
    {
        return Group::query()->orderBy('sort_order')->get();
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="space-y-3">
        <flux:input wire:model="name" :label="$editingId ? 'Rename group' : 'New group'" placeholder="e.g. Media" />
        <div class="flex gap-2">
            <flux:button type="submit" variant="primary" class="flex-1">
                {{ $editingId ? 'Save' : 'Add group' }}
            </flux:button>
            @if ($editingId)
                <flux:button type="button" wire:click="cancel">Cancel</flux:button>
            @endif
        </div>
    </form>

    <ul class="space-y-2">
        @forelse ($this->groups() as $group)
            <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3.5 dark:border-slate-700">
                <span class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $group->name }}</span>
                <div class="flex shrink-0 items-center gap-1">
                    <flux:button
                        icon="pencil"
                        variant="ghost"
                        size="sm"
                        wire:click="edit({{ $group->id }})"
                        aria-label="Edit {{ $group->name }}"
                    />
                    <flux:button
                        icon="trash"
                        variant="ghost"
                        size="sm"
                        class="!text-rose-500 hover:!text-rose-600 dark:!text-rose-400 dark:hover:!text-rose-300"
                        wire:click="delete({{ $group->id }})"
                        wire:confirm="Delete this group? Its cards will become ungrouped."
                        aria-label="Delete {{ $group->name }}"
                    />
                </div>
            </li>
        @empty
            <li class="text-sm text-slate-400 dark:text-slate-500">No groups yet.</li>
        @endforelse
    </ul>
</div>
