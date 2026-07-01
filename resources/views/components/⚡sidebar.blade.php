<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div x-data x-cloak>
    <div
        x-show="$store.sidebar.open"
        x-on:click="$store.sidebar.open = false"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-slate-900/50"
    ></div>

    <aside
        x-show="$store.sidebar.open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col bg-white shadow-xl dark:bg-slate-800"
    >
        <div
            x-data="{ tab: 'groups' }"
            class="flex h-full flex-col"
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Manage</h2>
                <button
                    type="button"
                    x-on:click="$store.sidebar.open = false"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                >
                    ✕
                </button>
            </div>

            <div class="flex border-b border-slate-200 px-4 dark:border-slate-700">
                <button
                    type="button"
                    x-on:click="tab = 'groups'"
                    x-bind:class="tab === 'groups' ? 'border-slate-800 text-slate-800 dark:border-slate-100 dark:text-slate-100' : 'border-transparent text-slate-400'"
                    class="border-b-2 px-3 py-2 text-xs font-semibold"
                >
                    Groups
                </button>
                <button
                    type="button"
                    x-on:click="tab = 'cards'"
                    x-bind:class="tab === 'cards' ? 'border-slate-800 text-slate-800 dark:border-slate-100 dark:text-slate-100' : 'border-transparent text-slate-400'"
                    class="border-b-2 px-3 py-2 text-xs font-semibold"
                >
                    Cards
                </button>
                <button
                    type="button"
                    x-on:click="tab = 'machines'"
                    x-bind:class="tab === 'machines' ? 'border-slate-800 text-slate-800 dark:border-slate-100 dark:text-slate-100' : 'border-transparent text-slate-400'"
                    class="border-b-2 px-3 py-2 text-xs font-semibold"
                >
                    Discovery
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                <div x-show="tab === 'groups'">
                    <livewire:group-manager />
                </div>
                <div x-show="tab === 'cards'">
                    <livewire:card-manager />
                </div>
                <div x-show="tab === 'machines'">
                    <livewire:machine-manager />
                </div>
            </div>
        </div>
    </aside>
</div>
