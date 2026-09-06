{{--
    Demo mode only (config('homie.demo_mode')) - self-guarded so it's safe to
    include anywhere without remembering the check at the call site. Explains
    what's available for a visitor building their own cards: the mock arr-stack
    API services from docker/mock-arr-api/router.php (see config/homie.php's
    demo_mock_sonarr_url/demo_mock_radarr_url), and the locked-down SSH sandbox
    for output-card commands (docker/ssh-sandbox/README.md) - the latter's
    connection details are only shown when it's actually configured
    (config('homie.demo_sandbox_ssh_private_key')), same guard
    DemoDashboardSeeder uses to decide whether to seed the "Demo sandbox"
    Machine/"Try: uptime" card at all, so this panel never advertises a
    machine that isn't really there.
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
                @if (config('homie.demo_sandbox_ssh_private_key'))
                    <p class="mt-1 text-sky-800/90 dark:text-sky-200/90">
                        Output-type cards run a real shell command over SSH. The pre-seeded "Try: uptime"
                        card already targets a locked-down sandbox machine built just for this demo — it
                        can only ever run five fixed commands, nothing else, regardless of what's typed at
                        it. Open Manage &rarr; Cards, add a card, set its type to "Output", and use the
                        <strong>Demo sandbox</strong> machine with any of:
                    </p>
                    <dl class="mt-3 space-y-2">
                        <div class="rounded-md bg-white/60 p-2 dark:bg-white/5">
                            <dt class="font-semibold"><code>uptime</code></dt>
                            <dd>How long the sandbox has been running</dd>
                        </div>
                        <div class="rounded-md bg-white/60 p-2 dark:bg-white/5">
                            <dt class="font-semibold"><code>df -h</code></dt>
                            <dd>Disk usage</dd>
                        </div>
                        <div class="rounded-md bg-white/60 p-2 dark:bg-white/5">
                            <dt class="font-semibold"><code>date</code></dt>
                            <dd>Current date/time on the sandbox</dd>
                        </div>
                        <div class="rounded-md bg-white/60 p-2 dark:bg-white/5">
                            <dt class="font-semibold"><code>whoami</code></dt>
                            <dd>The sandbox's own restricted user</dd>
                        </div>
                        <div class="rounded-md bg-white/60 p-2 dark:bg-white/5">
                            <dt class="font-semibold"><code>echo &lt;text&gt;</code></dt>
                            <dd>Echoes back whatever text you give it</dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-sky-700/80 dark:text-sky-300/80">
                        Anything else — a different command, chaining with <code>;</code>/<code>&amp;&amp;</code>,
                        an attempt at a real shell — is rejected outright by the sandbox itself, not just
                        hidden by this UI. See <code>docker/ssh-sandbox/README.md</code> in the repo for the
                        full threat model if you're curious how it's locked down.
                    </p>
                @else
                    <p class="mt-1 text-sky-800/90 dark:text-sky-200/90">
                        Output-type cards run a real shell command against a saved machine. This particular
                        deployment doesn't have the sandboxed demo target configured, so there's no safe
                        machine to point one at here — the feature still works, it just has nothing to
                        demo against on this instance.
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif
