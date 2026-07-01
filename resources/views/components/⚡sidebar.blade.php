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
        class="fixed inset-0 z-40 bg-slate-900/60"
    ></div>

    <aside
        x-show="$store.sidebar.open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-50 flex w-full flex-col bg-white shadow-xl sm:max-w-md dark:bg-slate-800"
    >
        <div
            x-data="{ tab: 'groups' }"
            x-on:switch-sidebar-tab.window="tab = $event.detail.tab"
            class="flex h-full flex-col"
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-5 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Manage</h2>
                <button
                    type="button"
                    x-on:click="$store.sidebar.open = false"
                    class="-m-2 flex h-11 w-11 items-center justify-center rounded-full text-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                >
                    ✕
                </button>
            </div>

            <div class="flex border-b border-slate-200 dark:border-slate-700">
                <button
                    type="button"
                    x-on:click="tab = 'groups'"
                    x-bind:class="tab === 'groups' ? 'border-slate-800 text-slate-800 dark:border-slate-100 dark:text-slate-100' : 'border-transparent text-slate-400'"
                    class="flex-1 border-b-2 py-3.5 text-sm font-semibold"
                >
                    Groups
                </button>
                <button
                    type="button"
                    x-on:click="tab = 'cards'"
                    x-bind:class="tab === 'cards' ? 'border-slate-800 text-slate-800 dark:border-slate-100 dark:text-slate-100' : 'border-transparent text-slate-400'"
                    class="flex-1 border-b-2 py-3.5 text-sm font-semibold"
                >
                    Cards
                </button>
                <button
                    type="button"
                    x-on:click="tab = 'machines'"
                    x-bind:class="tab === 'machines' ? 'border-slate-800 text-slate-800 dark:border-slate-100 dark:text-slate-100' : 'border-transparent text-slate-400'"
                    class="flex-1 border-b-2 py-3.5 text-sm font-semibold"
                >
                    Discovery
                </button>
            </div>

            <div
                class="flex-1 overflow-y-auto p-5"
                x-ref="scrollBody"
                x-on:scroll-sidebar-top.window="$refs.scrollBody.scrollTop = 0"
            >
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
