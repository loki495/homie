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

<div class="space-y-6">
    <form wire:submit="save" class="space-y-3">
        <label class="block text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ $editingId ? 'Rename group' : 'New group' }}
        </label>
        <input
            type="text"
            wire:model="name"
            placeholder="e.g. Media"
            class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
        >
        @error('name')
            <p class="text-sm text-rose-500">{{ $message }}</p>
        @enderror
        <div class="flex gap-2">
            <button
                type="submit"
                class="flex-1 rounded-lg bg-slate-800 px-4 py-3 text-sm font-semibold text-white active:bg-slate-700 dark:bg-slate-100 dark:text-slate-800 dark:active:bg-slate-200"
            >
                {{ $editingId ? 'Save' : 'Add group' }}
            </button>
            @if ($editingId)
                <button
                    type="button"
                    wire:click="cancel"
                    class="rounded-lg bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-600 active:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:active:bg-slate-600"
                >
                    Cancel
                </button>
            @endif
        </div>
    </form>

    <ul class="space-y-2">
        @forelse ($this->groups() as $group)
            <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3.5 dark:border-slate-700">
                <span class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $group->name }}</span>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        wire:click="edit({{ $group->id }})"
                        aria-label="Edit {{ $group->name }}"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                    >
                        <x-icons.pencil class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        wire:click="delete({{ $group->id }})"
                        wire:confirm="Delete this group? Its cards will become ungrouped."
                        aria-label="Delete {{ $group->name }}"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 hover:bg-rose-50 hover:text-rose-500 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                    >
                        <x-icons.trash class="h-5 w-5" />
                    </button>
                </div>
            </li>
        @empty
            <li class="text-sm text-slate-400 dark:text-slate-500">No groups yet.</li>
        @endforelse
    </ul>
</div>
