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

<div class="space-y-4">
    <form wire:submit="save" class="space-y-2">
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ $editingId ? 'Rename group' : 'New group' }}
        </label>
        <div class="flex gap-2">
            <input
                type="text"
                wire:model="name"
                placeholder="e.g. Media"
                class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
            >
            <button
                type="submit"
                class="shrink-0 rounded-md bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white dark:bg-slate-100 dark:text-slate-800"
            >
                {{ $editingId ? 'Save' : 'Add' }}
            </button>
            @if ($editingId)
                <button
                    type="button"
                    wire:click="cancel"
                    class="shrink-0 rounded-md bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
                >
                    Cancel
                </button>
            @endif
        </div>
        @error('name')
            <p class="text-xs text-rose-500">{{ $message }}</p>
        @enderror
    </form>

    <ul class="space-y-1">
        @forelse ($this->groups() as $group)
            <li class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 dark:border-slate-700">
                <span class="text-sm text-slate-700 dark:text-slate-200">{{ $group->name }}</span>
                <div class="flex items-center gap-2 text-xs">
                    <button type="button" wire:click="edit({{ $group->id }})" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                        Edit
                    </button>
                    <button
                        type="button"
                        wire:click="delete({{ $group->id }})"
                        wire:confirm="Delete this group? Its cards will become ungrouped."
                        class="text-rose-500 hover:text-rose-600"
                    >
                        Delete
                    </button>
                </div>
            </li>
        @empty
            <li class="text-sm text-slate-400 dark:text-slate-500">No groups yet.</li>
        @endforelse
    </ul>
</div>
