{{--
    Demo mode only (config('homie.demo_mode')) - self-guarded so it's safe to
    include anywhere without remembering the check at the call site. Explains
    what's available for a visitor building their own cards: the mock arr-stack
    API services from docker/mock-arr-api/router.php (see config/homie.php's
    demo_mock_sonarr_url/demo_mock_radarr_url). The output-card section is a
    placeholder - the sandboxed SSH target for that feature is being built
    separately, see .ai/plans/2026-09-06-demo-sites-and-cd (outside this repo).
--}}
@if (config('homie.demo_mode'))
    <div
        x-data="{ open: false }"
        class="rounded-lg border border-sky-200 bg-sky-50 text-sm dark:border-sky-900 dark:bg-sky-950/40"
    >
        <button
            type="button"
            x-on:click="open = !open"
            x-bind:aria-expanded="open"
            class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left font-semibold text-sky-800 dark:text-sky-200"
        >
            <span>This is a live demo — add your own cards</span>
            <span x-text="open ? '−' : '+'" class="text-lg leading-none" aria-hidden="true"></span>
        </button>

        <div x-show="open" class="space-y-4 border-t border-sky-200 px-4 py-4 text-sky-900 dark:border-sky-900 dark:text-sky-100">
            <div>
                <p class="font-semibold">Mock API servers for API-type cards</p>
                <p class="mt-1 text-sky-800/90 dark:text-sky-200/90">
                    Two small mock services stand in for a real Sonarr/Radarr install, so an API-type
                    card shows real fetched stats without touching anyone's actual media server. Open
                    Manage &rarr; Cards, add a card, set its type to "API", and use one of these:
                </p>
                <dl class="mt-3 space-y-2">
                    <div class="rounded-md bg-white/60 p-2 dark:bg-white/5">
                        <dt class="font-semibold">Sonarr</dt>
                        <dd>
                            Provider: <code>Sonarr</code>
                            &middot; Base URL: <code>{{ config('homie.demo_mock_sonarr_url') }}</code>
                            &middot; API key: any value (the mock doesn't check it)
                        </dd>
                    </div>
                    <div class="rounded-md bg-white/60 p-2 dark:bg-white/5">
                        <dt class="font-semibold">Radarr</dt>
                        <dd>
                            Provider: <code>Radarr</code>
                            &middot; Base URL: <code>{{ config('homie.demo_mock_radarr_url') }}</code>
                            &middot; API key: any value (the mock doesn't check it)
                        </dd>
                    </div>
                </dl>
                <p class="mt-2 text-xs text-sky-700/80 dark:text-sky-300/80">
                    The pre-seeded "Sonarr" card on this dashboard already uses the Sonarr mock above —
                    add the Radarr one yourself to see it in action. Running Discovery from Manage &rarr;
                    Machines also finds both mock services directly.
                </p>
            </div>

            <div>
                <p class="font-semibold">Output-card commands</p>
                <!-- TODO: SSH sandbox command list, added separately -->
                <p class="mt-1 text-sky-800/90 dark:text-sky-200/90">
                    Output-type cards run a real shell command against a saved machine. The sandboxed
                    demo target for that is being finished separately — check back soon for a safe,
                    allowlisted command list to try here.
                </p>
            </div>
        </div>
    </div>
@endif
