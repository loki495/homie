@props(['editAction', 'deleteAction', 'label', 'confirm'])

<flux:button
    icon="pencil"
    variant="ghost"
    size="sm"
    wire:click="{{ $editAction }}"
    aria-label="Edit {{ $label }}"
/>
<flux:button
    icon="trash"
    variant="ghost"
    size="sm"
    class="!text-rose-500 hover:!text-rose-600 dark:!text-rose-400 dark:hover:!text-rose-300"
    wire:click="{{ $deleteAction }}"
    wire:confirm="{{ $confirm }}"
    aria-label="Delete {{ $label }}"
/>
