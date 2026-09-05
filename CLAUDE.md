# Homie — project context

Self-hosted home lab dashboard. Laravel 13 + Livewire 4 (SFC) + Alpine.js + Tailwind v4 +
Flux UI (free tier). SQLite. Dockerized dev environment; reachable via a published
port (`APP_PORT`, default 8090) out of the box, or via Andres's own Traefik +
ac495.net routing (external to this repo, not shipped or assumed) when working on
his machine specifically.

## UI components

Sidebar manager forms/buttons (Groups, Cards, Discovery) use Flux UI components
(`<flux:input>`, `<flux:select>`, `<flux:textarea>`, `<flux:button>`) — free tier only,
no Pro license. Delete actions use `variant="ghost"` with a `!text-rose-*` class
override (not `variant="danger"`, which renders a filled red button — Flux's own
`text-zinc-800`/`dark:text-white` ghost-variant classes otherwise win the cascade tie
against a plain color override, so the `!` important modifier is required). The
off-canvas sidebar shell and the Groups/Cards/Discovery tab bar remain custom
Alpine — no Flux equivalent was worth the migration risk for either. Dark mode stays
on the project's own `Alpine.store('theme')` + inline FOUC-prevention script; Flux's
`@fluxAppearance`/`@fluxScripts` directives are included for the components' own
needs but the toggle button itself is not Flux's.
- The Cards sidebar list's filter input is plain Alpine (`x-model` + `x-show` per `<li>`
  matching against a `data-search` attribute) — no Livewire round-trip. This is the
  pattern for any future client-side-only filtering: cheaper than a Livewire property
  for something that never needs to touch the server.

## Card icons

`app/Support/DashboardIcons.php` searches two free, no-API-key icon indexes for card
creation: homarr-labs/dashboard-icons for recognized self-hosted app logos (sonarr,
radarr, plex, ...), and Heroicons' `24/outline` set (via jsDelivr's npm CDN) for
generic icons (folder, router, link, ...) when a card isn't a specific branded
service — app-logo matches are returned first, heroicons fill the rest up to the
(generous, 40) limit. Both icons are hotlinked from jsDelivr, never downloaded or
cached locally on this app's storage (a deliberate choice: keeps things simple, no
storage/cleanup concern, matches how Homarr/Dashy do it). Both indexes are cached
server-side for a day via `Cache::remember`. `Card.icon` just stores a plain URL —
either a resolved CDN link or a manually pasted one, no distinction made at render
time.

Heroicons are monochrome (`stroke="currentColor"` in the SVG), which only resolves
correctly when an SVG is inlined into the page — hotlinked via a plain `<img>` tag
(every icon in this app is) it renders solid black regardless of theme, invisible
against a dark card background. Every place a card icon renders goes through the
shared `<x-card-icon :src="...">` component (`resources/views/components/card-
icon.blade.php`), which applies a `dark:invert` filter when
`DashboardIcons::isMonochrome($url)` matches the icon's URL against the heroicons CDN
host — homarr's full-color app logos must never get that filter, so this is a URL
host check, not a general "is this SVG" check. Any new place that renders `Card.icon`
(or a raw search-result URL) must go through this component rather than a bare
`<img>`, or a heroicon picked for that spot will silently disappear in dark mode.

## Card API auth

`card_apis.auth_type` selects between `api_key` (sent as an `X-Api-Key` header — the
arr-stack convention) and `basic` (username/password, sent via `Http::withBasicAuth`).
Only one is active at a time based on `auth_type`; the unused fields are nulled out on
save so stale credentials from a previous auth-type choice don't linger. `password` is
encrypted at rest the same way `api_key` already was.

## Provider-specific API stats

`ApiProvider::fetcher()` maps every enum case to an `App\Support\ApiProviders\*Fetcher`
(implementing `ProviderFetcher`) — every provider in the enum has one, non-nullable, by
design (see "Adding a new provider" below). Each fetcher does its own HTTP calls and
returns `{status, summary, stats[], raw}` plus three optional keys (`downloaded[]`,
`deleted[]`, `current`) that a fetcher only includes when it has that kind of data —
`stats` is a small list of label/value pairs rendered as chips on the card-api-widget
instead of the generic "HTTP 200" line. Endpoint shapes were verified against the
gethomepage/homepage widget source (a mature OSS project with working integrations for
all of these) plus live calls against Andres's own instances — not guessed. Notable
per-provider quirks:
- Sonarr: `/api/v3/series` (count), `/api/v3/queue` and `/api/v3/wanted/missing` both
  paginated with a `totalRecords` field — request `pageSize=1` to avoid pulling the
  full list just for the count.
- Radarr v3 has no `/wanted/missing` endpoint (only existed in v1). Missing count is
  computed client-side from `/api/v3/movie`: monitored && !hasFile.
- NZBGet doesn't use an API key — it's HTTP Basic Auth (`ControlUsername`/
  `ControlPassword`), a JSON-RPC POST to `/jsonrpc` with `{"method": "status"}`. This is
  exactly what `auth_type = 'basic'` was built for.
- Prowlarr: same Servarr framework as Sonarr/Radarr, `X-Api-Key` header via
  `ApiHttpClient`. `/api/v1/indexer` (list, filter `enable === true` for the enabled
  count) and `/api/v1/indexerstats` (sum `numberOfGrabs`/`numberOfFailed*` across its
  `indexers` array) — no single endpoint gives an aggregate, so it fetches both.
- Bazarr does **not** follow the arr-stack header convention — it's `?apikey=` as a
  query string only (confirmed: an unauthenticated request to `/api/movies/wanted`
  returns a 401, and gethomepage/homepage's working integration only ever sends the key
  in the query string). `BazarrFetcher` calls `Http::get()` directly instead of going
  through `ApiHttpClient`, since that helper's basic-auth/header logic doesn't apply
  here. Missing-subtitle counts come from `/api/movies/wanted` and
  `/api/episodes/wanted`, both `{"total": N, ...}`.

Adding a new provider: add the enum case, a Fetcher implementing `ProviderFetcher`, and
one `match` arm in `ApiProvider::fetcher()`, all in the same change — the widget needs
no changes. Every case must resolve to a real fetcher (the return type is
non-nullable); don't add an enum case before its fetcher exists.

## Sonarr/Radarr recent activity, and NZBGet's current download

`SonarrFetcher`/`RadarrFetcher` each add two extra calls to `/api/v3/history` (page 1,
pageSize 5, sorted by date descending), filtered server-side via the numeric `eventType`
query param — Sonarr's `EpisodeHistoryEventType` and Radarr's `MovieHistoryEventType`
enums assign different integers to the same concepts (downloadFolderImported = 3 in
both, but deleted is `episodeFileDeleted = 5` for Sonarr vs `movieFileDeleted = 6` for
Radarr — confirmed against each project's C# source, not guessed), so the two fetchers
each hardcode their own pair of constants rather than sharing one. Each history record's
`sourceTitle` is used as the display name and `date` is formatted with
`Carbon::parse()->diffForHumans()`. For deleted entries specifically, `sourceTitle` is
the file's relative path rather than a release name (unlike grabbed/imported entries),
so `history()` takes a `basenameOnly` flag — only the deleted call passes it — and runs
the name through `basename()`; harmless no-op on a slash-free release name, so the same
helper serves both lists without a second code path. card-api-widget renders these as two native
`<details>`/`<summary>` collapsible lists ("Recently downloaded"/"Recently deleted") so
they don't dominate the card. Since Api-type cards are wrapped in a whole-card `<a
href>` (see below), the `<summary>` has an inline `onclick="event.stopPropagation()"` —
without it, toggling the list also fires the link navigation. Each list item's name gets
`min-w-0` alongside Tailwind's `truncate` (a plain flex item won't actually shrink/ellide
without `min-w-0`) plus a `title` attribute with the full string, so a long release name
truncates visually but is still readable on hover.

NZBGet's `current` (the file actively downloading) comes from a second JSON-RPC call,
`listgroups` with `params: [0]` — the queue entry whose `Status === 'DOWNLOADING'`
(NZBGet computes this server-side; a group can also be `PAUSED`/`QUEUED`/postprocessing
states), read from its `NZBName` field. Confirmed against NZBGet's own C++ source
(`daemon/remote/XmlRpc.cpp`), since homepage's widget doesn't cover this endpoint.

All three of these extra calls are individually try/caught and default to
empty/null on any failure (timeout, non-2xx, unexpected shape) — deliberately *not*
sharing the fetcher's outer try/catch, so a hiccup on the history/listgroups call alone
never flips the whole card into its error state when the main stats call succeeded fine.

## Discovery: host-network containers need an inspect fallback

`docker ps`/`/containers/json` report an empty `Ports` for containers running with
`--network host` — there's no mapping to report, the container's ports *are* the host's
ports directly. Without a fallback, every host-network container with no Traefik label
silently vanished from discovery results (found via a real scan: Home Assistant and
ESPHome were both missing from a host with 11 running containers). Both `viaDocker` and
`viaSsh` on `app/Support/Discovery/MachineDiscovery` (extracted from
`⚡machine-manager.blade.php` — see "Discovery logic lives outside the Livewire
component" below) now do a follow-up lookup for exactly these stragglers — `docker
inspect` (SSH) or `GET /containers/{id}/json` (API) — and read the
first port out of `Config.ExposedPorts` (the image's declared `EXPOSE`, present even
under host networking since it's build-time metadata, not a runtime port mapping).
This only recovers a *port* for containers whose image actually declares `EXPOSE` —
some (Home Assistant, notably) declare none. Rather than drop those silently, they're
still surfaced with a bare `http://{host}` URL (no port) — reachable, we just don't know
at which port, so it's left for the user to fill in when they add the card. Hardcoding a
per-image default port was considered and rejected: it's exactly the kind of
lab-specific special-casing the project's distributability rule forbids (see below), and
there's no reliable app-agnostic source for it. Bridge/default-network containers with
no port and no label are still correctly excluded outright (not surfaced with a bare
URL) — the host-network check (`Networks === 'host'` over SSH,
`HostConfig.NetworkMode === 'host'` via the API) is what gates the fallback, so we don't
invent URLs for containers that genuinely have no path to the host at all.

## API cards are links, but the wrapping lives outside the widget

`card.blade.php` wraps `<livewire:card-api-widget>` in an `<a href="{{ $card->url }}">`
itself, rather than having the widget component decide whether to render a link. This is
deliberate: `$editing` (Arrange mode) needs to gate the link exactly like it already does
for Link-type cards — no `<a>` while arranging, so clicking a card doesn't navigate away
mid-drag. If that logic lived inside `⚡card-api-widget.blade.php` instead, it'd hit the
same lazy-load-island staleness problem as the entry below (`$editing` passed as a prop
would only reflect its value at first mount, not later toggles of Arrange mode). Doing
the wrap in `card.blade.php` sidesteps that entirely — it's a plain Blade partial that
always re-renders fresh with Dashboard, no separate component lifecycle involved. The
same wrapper also now renders the card's icon/name (see the entry below) — `card.blade.php`
owns the whole visible box (border/bg/padding) for Output and Api cards, not just the link.

## Card titles render eagerly; only fetched content is lazy-loaded

`⚡card-output-widget.blade.php` and `⚡card-api-widget.blade.php` are `lazy`-loaded
nested Livewire components — Livewire renders their `placeholder()` skeleton on first
paint and only mounts the real component (running the shell command / API fetch) on a
follow-up request. Originally the icon/name lived *inside* those widgets, so every
output/api card showed a full generic skeleton box on load with no indication of what
it even was. Icon/name are cheap, already-loaded `Card` attributes — no reason to gate
them behind the same round-trip as an actual command execution or HTTP call. Fixed by
moving the icon/name markup out into `card.blade.php` (a plain Blade partial, rendered
eagerly, no lazy boundary) and shrinking each widget's own template down to just the
part that genuinely depends on the fetch: status dot, refresh countdown/spinner, and
the output/stats body. `card.blade.php` now owns the full card box (border/bg/padding)
for Output and Api cards; the widgets' `placeholder()`/root templates no longer include
their own border or box, only a content-shaped skeleton. Verified via
`Livewire::test('dashboard')` — the card's name is present in the very first render
pass (before the lazy child ever mounts), while the widget's actual fetched content is
provably absent until the follow-up request (see `DashboardTest`'s "renders ... title
immediately, without waiting on its lazy-loaded content" tests, which assert exactly
that split).

One side effect: since icon/name no longer live inside the widgets, the
`#[On('dashboard-updated')] refreshCard()` listener that used to exist on both widgets
purely to catch up `$this->card->fresh()` after a sidebar edit is now moot for
`⚡card-api-widget.blade.php` (nothing else in that widget depends on `$card` after
`mount()`) — removed entirely. `⚡card-output-widget.blade.php` keeps its listener,
because it does other useful work: refreshing `$refreshIntervalSeconds` from
`$this->card->output?->refresh_interval_seconds` so an edited poll interval takes
effect without re-running the command (see the wire:poll entry below) — that's
unrelated to the header and still needed. (`#[Reactive]` on the `card` prop was tried
originally for this same staleness problem — doesn't cross the lazy-load boundary in
practice, and complicates `mount()` since a `#[Reactive]` prop can't be reassigned from
inside the component without throwing `CannotMutateReactivePropException`.)

## Output cards and SSH discovery must never let Process exceptions escape

`⚡card-output-widget.blade.php`'s `mount()` runs a user-supplied shell command
(`Process::timeout(10)->run(...)`) — for remote/SSH commands this can genuinely time
out or fail to spawn, and an uncaught `ProcessTimedOutException` (or any other
`Throwable`) crashes the whole Livewire request with a 500 and a jarring error dialog,
even though the failure is expected/routine (a flaky SSH connection, not a bug). Wrap
every direct `Process::run()` call in try/catch and turn a timeout into a normal
"Command timed out after 10s." card output instead of letting it propagate. The same
applies to `⚡machine-manager.blade.php`'s SSH discovery (`discoverViaSsh` and
`resolveHostNetworkPortsViaSsh`) — both now catch `Throwable` around their
`Process::run()` calls and set `$scanError`/fall back gracefully rather than crashing.
(`ApiHttpClient`-based API fetchers already caught `Throwable` around their HTTP calls
from the start, so they were never affected by this.)

## Output cards can auto-refresh via wire:poll, with an overlap guard

`card_outputs.refresh_interval_seconds` (nullable) lets an output-type card auto-refresh
— the card-manager form takes an amount + seconds/minutes unit, normalized to seconds on
save, and round-tripped back into the same two fields when editing. `⚡card-output-
widget.blade.php` renders `wire:poll.{seconds}s="refreshOutput"` on its wrapper `<div>`
only when an interval is set. Livewire's poll directive is a plain `setInterval` with no
built-in per-request dedup, so a command slower than its own poll interval (a flaky SSH
hop, a laggy remote host) would otherwise stack up overlapping executions. `runCommand()`
guards against this with a non-blocking `Cache::lock("card-output-running:{id}", 15)`: if
a run is already holding the lock, the poll just redisplays the card's last known
`last_output`/`last_exit_code` from the DB instead of re-running the command. The lock
TTL (15s) is a few seconds past the command's own `Process::timeout(10)`, so a lock from
a run that dies without releasing (e.g. the worker gets killed mid-request) self-expires
instead of deadlocking the card forever. `dashboard-updated` continues to only refresh
`$refreshIntervalSeconds` (read fresh off `card->output`) rather than re-running the
command — editing the interval mid-session takes effect on the next poll without an
extra command execution, consistent with how that listener already avoids re-running the
command for name/icon edits (see above).

## Drag-and-drop reordering sits alongside the arrow buttons, not instead of them

`⚡dashboard.blade.php` supports both: native HTML5 drag-and-drop (Arrange mode only)
*and* the original up/down arrow buttons, side by side. This was deliberate — native
HTML5 drag-and-drop has no touch-device support at all (mobile Safari/Chrome don't
implement it), and this dashboard is used from phones on the LAN, not just desktop. Drag
is the fast path for a mouse; the arrows are what makes reordering possible from a
touch device at all, so they stay. `reorderCards`/`reorderGroups` on the component take
an ordered list of IDs and write `sort_order` in one pass — added alongside the
existing `moveCard`/`moveGroup` (which still do the one-swap-per-click thing the arrows
use), not replacing them. Each group/ungrouped section owns its own Alpine `x-data`
(`dragCardIndex`/`cardOrder`/`onCardDrop`) scoped to that container's card list, and a
separate outer `x-data` on the groups wrapper handles group-level dragging
(`dragGroupIndex`/`groupOrder`/`onGroupDrop`) — dragging is scoped to reordering within
a container, not moving a card between groups (same scope the arrows already had).
`draggable`/the drag event handlers only render when `$editing` is true, same gating as
the arrow buttons and the move-button overlay on cards.

## Discovery: don't gate on published ports alone

Both `viaDocker` and `viaSsh` on `MachineDiscovery` check for a Traefik `Host()` label
*before* falling back to requiring a host-published port. Got this wrong once already
(shipped a version that required a published port even when a Traefik label was
present) — silently dropped every container that's only reachable through Traefik's
internal Docker-network routing, which is the common case when only the reverse proxy
itself publishes ports. If touching discovery again, keep the label-first ordering.

## Discovery logic lives outside the Livewire component

`⚡machine-manager.blade.php` used to have Docker-API discovery, SSH discovery, and all
the Traefik-label/EXPOSE-port resolution heuristics inline (400+ lines mixing that with
plain CRUD form handling). It's now `app/Support/Discovery/MachineDiscovery`, a plain
class with no Livewire dependency — `viaDocker(Machine $machine)` and `viaSsh(Machine
$machine)` each return a `DiscoveryResult` DTO (`containers` + `error`) instead of
mutating component state directly; `discover()` on the component just assigns the
result to `$discovered`/`$scanError`. Behavior is byte-for-byte the same as before the
move — this was a pure extraction, verified by the pre-existing `MachineManagerTest`
suite passing unmodified through the same Livewire component boundary, plus a live SSH
scan re-run against a real machine. One side effect worth knowing: `phpstan.neon`
excludes `resources/views`, so this logic was silently unanalyzed by PHPStan and Rector
the entire time it lived in a `.blade.php` file — moving it into `app/` surfaced two
real (if minor) findings that had been invisible until then (a `collect()` call PHPStan
couldn't resolve template types for on `mixed`-typed JSON, and one Rector first-class-
callable modernization). Worth remembering if other blade-embedded logic ever needs the
same treatment: extracting it isn't just cleaner, it's the only way static analysis
ever sees it.

## PHPStan was blind to every enum-casted model property until this was found

Larastan's `parseModelCastsMethod` option defaults to `false`, so it only reads type
casts off the legacy `$casts` property and never parses the `casts()` method body —
the pattern this project's models actually use (Laravel 11+ convention). Every
enum-casted property (`Card::type`, `CardApi::provider`, `Machine::discovery_method`)
was therefore typed as plain `string` by static analysis the whole time, silently
missing any `->value`-style misuse. Found while writing `ConfigExporter` (see below),
the first code outside a model to access one of these with `->value`. Fixed by setting
`parameters.parseModelCastsMethod: true` in `phpstan.neon` — re-running the full suite
after that change turned up no other latent errors, but it's worth knowing this gap
existed in case a similarly-shaped issue resurfaces with a newer Larastan release.

## Config export/import (Backup tab)

`app/Support/Config/ConfigExporter` and `ConfigImporter` (both plain classes, no
Livewire dependency, same pattern as `MachineDiscovery`) back the sidebar's "Backup"
tab (`⚡backup-manager.blade.php`). Export walks groups → cards → output/api, and
machines, into one JSON structure; import is a **full replace**, not a merge — it
deletes every existing group/card/machine inside one DB transaction before recreating
them from the file, so re-importing the same file twice never duplicates anything, but
it does mean import is destructive by design (the UI guards it with a plain
`confirm()` before the upload is even sent to `$wire.import()`).

Secrets (`card_apis.api_key`/`password`, `machines.ssh_private_key`) are **never**
included in the export — only a `has_api_key`/`has_password`/`has_ssh_private_key`
boolean, so a restore can tell the user which cards/machines need credentials
re-entered afterward rather than silently leaving them broken. This was a deliberate
choice over "export with a warning": a JSON backup file is far more likely to be
copied, shared, or committed somewhere by accident than the SQLite file it's backing
up, so secrets simply never leave the database. Runtime/cache fields (`last_output`,
`cached_data`, `last_fetched_at`, ...) are excluded too — this is a config backup, not
a full table dump, and all of those regenerate on next poll/fetch.

Both classes build their arrays with explicit `foreach` loops rather than
`Collection::map()` chains — the latter left Larastan unable to resolve the closures'
return types here, same class of issue as the `collect()`-on-mixed-data problem
documented above for `MachineDiscovery`/`DashboardIcons`.

`ConfigImporter` validates every row defensively (`is_array`/`is_string` checks,
`Enum::tryFrom()` for type/provider/discovery-method fields) rather than through
Laravel's validator: this reads a user-supplied file that could be hand-edited, from
an older export version, or just malformed, and a single bad row should be skipped
with a warning rather than aborting the whole restore.

Verified live against the real dashboard: exported the actual production-data SQLite
file, re-imported it through the browser (confirm dialog and all), and diffed the
result — structure matched exactly, and API/SSH-backed cards correctly went to
"Could not reach"/permission-denied since their secrets were (as designed) stripped.
The live DB was backed up first and restored afterward; restoring the raw SQLite file
bypasses `MachineObserver`, so the `storage/ssh/{slug}` synced key files it manages
were left in their post-import (deleted) state — a plain `$machine->save()` per
affected machine re-triggers the sync. Worth remembering if the DB file is ever
restored from a snapshot outside the app itself: `storage/ssh/` needs a manual resync
afterward, it doesn't self-heal from the DB alone.

## Design principle: this is a distributable app

No lab-specific machine name, hostname, IP, service, or credential should ever be
hardcoded in application code, config defaults, seeders, or views. Anyone should be
able to clone this repo and get an empty dashboard they configure themselves through
the UI/database — not one pre-wired to Andres's home lab.

- Services, machines, groups, card order, output-card commands, and API connection
  details are all rows in the database, never PHP constants or `.env` values baked
  into a controller.
- `.env`/`.env.example` should only ever contain generic Laravel/app config (DB driver,
  app URL, etc.) — never a specific service's address or token.
- If a feature needs a "default" (e.g. a demo card), seed it behind a flag or a
  separate demo seeder, not the default seeder path.

## Container / infra

- App container: `homie-app` (PHP 8.5-apache). Vite container: `homie-vite`. Both have
  `restart: unless-stopped` and the vite service has a healthcheck (polls
  `/@vite/client`) so a crashed dev server is visible in `docker ps`/restarted
  automatically instead of silently leaving HMR dead. A third service, `app-test`
  (`profiles: ["test"]`, never started by a plain `docker compose up`), exists solely
  to run browser tests — see "Browser testing" under Testing below for why it's a
  separate image rather than something added to `homie-app` itself.
- `app` publishes `${APP_PORT:-8090}:80` — found missing during a hardcoding audit:
  the app's Traefik `Host()` router label was removed in an earlier commit (LAN
  access moved to `ac495.net`, routed from a file outside this repo,
  `~/www/traefik/dynamic/ac495-sites.yml`), but nothing replaced it, so a fresh
  clone following this repo's own README had no way to reach the app at all —
  `vite`'s equivalent label was left behind as stale documentation of the old
  scheme, not a working path for a new clone either. The published port is the
  distributable fallback; Traefik/ac495.net remains available on top of it for
  Andres's own machine specifically, never assumed.
- Run PHP tooling via `docker exec -u www-data homie-app ...` — use `-u www-data`
  (not root) so files stay owned by UID 1000, matching the host user on the bind mount.
  Composer script wrappers (`composer pint`, `phpstan`, `rector`, `pest`) already do this.
- SQLite database file lives at `database/database.sqlite`, gitignored (it will hold
  real lab config once the app is used — never commit it).
- `storage/ssh/` is mounted into the container for SSH keys used in "output" card
  commands (arbitrary shell commands the user supplies — it's on the user to make sure
  they run correctly in the container). Gitignored except `.gitkeep`.
- Machine-based SSH discovery keeps its own encrypted private key
  (`machines.ssh_private_key`), decrypted to a 0600 temp file only for the duration of a
  scan — discovery itself never reads from `storage/ssh/`.
- `MachineObserver` (via `MachineSshKeySync`) auto-syncs that same key into
  `storage/ssh/{slug}` (e.g. `storage/ssh/media`) in plaintext, 0600, on every save —
  and deletes it if the key is cleared or the machine is deleted. This is a deliberate
  security tradeoff, confirmed with Andres before building: the DB copy stays encrypted
  and is decrypted only transiently for scans, but the synced copy sits on disk
  permanently (still container-internal, but readable by anyone with filesystem access,
  and it survives container rebuilds since `storage/ssh` is host-mounted) — done to let
  output-card commands SSH to a machine without duplicating key management. If a machine
  is renamed, the old slug's file is left behind harmlessly (not cleaned up — a minor
  known gap, not worth the added complexity to chase).

## Tooling

Default PHPStan level 6, Pint `laravel` preset, Rector `UP_TO_PHP_84` + code quality/dead
code sets (dry-run only — never auto-apply without reviewing the diff). Pre-commit hook
(symlinked from `~/.claude/hooks/laravel-pre-commit.sh`) runs Pint (auto-fix) → PHPStan
(block) → Rector dry-run (block) → Pest (block) on staged PHP files.

- The pre-commit hook's `docker exec` calls (Rector and Pest) run as the container's
  *default* user, which is **root** — not `www-data` (confirmed via `docker exec
  homie-app whoami`). Rector's `/tmp/rector_cached_files` cache ends up owned by root
  as a result. Running `./vendor/bin/rector` manually with `-u www-data` (the
  convention for Pint/PHPStan/Composer, to keep bind-mounted files owned by the host
  UID) will then fail with "Permission denied" trying to clean that root-owned cache —
  it's not a real Rector problem, just a user mismatch versus the hook. Run Rector
  manually the same way the hook does, without `-u www-data` (or `-u root`), to match.

## Embedding in an iframe, and why there is no CSRF exemption

This dashboard is embedded as an `<iframe>` in a Home Assistant dashboard, which is a
**different origin**. That is a cookie problem, not an IP problem, and it is worth
knowing which one you are actually looking at if the symptom ever returns.

Laravel's session cookie defaults to `SameSite=Lax`, and browsers do not send a Lax
cookie on cross-site subrequests — an iframe on another origin included. Inside that
frame there is therefore no session, so there is no session CSRF token to compare
against, so every POST fails token verification. The fix is `SESSION_SAME_SITE=none`
plus `SESSION_SECURE_COOKIE=true` (browsers only honour `None` alongside `Secure`, so
the dashboard must be served over HTTPS — Traefik already terminates TLS here). Both
are documented and commented out in `.env.example`; leave them unset for a
same-origin-only deployment, since Lax is the safer default.

**What this replaced, and why:** the original workaround was a custom
`App\Http\Middleware\VerifyCsrfToken` that skipped token verification entirely whenever
`$request->ip()` fell in a private or reserved range. That treated the symptom (POSTs
failing) rather than the cause (no cookie, hence no session), and it was unsound:
combined with `trustProxies(at: '*')`, Symfony walks the whole `X-Forwarded-For` chain
and takes the leftmost, client-supplied entry, and Traefik *appends* to that header
rather than replacing it — so a request arriving through the proxy carrying
`X-Forwarded-For: 10.0.0.1` resolved to a private IP and waived CSRF. A spoofable
header was the only thing between an attacker and an unprotected POST. Fixing the
cookie removes the need for any exemption, so the middleware and its test are gone and
Laravel's normal token check now runs on every request — strictly more protection than
before, not less.

`trustProxies(at: '*')` remains, but is no longer load-bearing for security: it now only
affects the accuracy of the client IP in logs. Narrowing it to Traefik's actual
container/network CIDR is still tidier, just no longer urgent.

## Git

Single-branch: `main`. This project intentionally opts out of the global master/local
branch model (see `~/.claude/CLAUDE.md`) — there's no separate production deployment to
mirror, so work happens directly on `main`. Repo: `loki495/homie` on GitHub (public).

## Testing

161 Pest tests (Feature + Unit) as of this writing, covering happy *and* sad paths for
essentially every Livewire component and support class — cards, groups, machines,
discovery (Docker API + SSH, including the host-network/Traefik-label edge cases),
backup import/export, icon search, CSRF/middleware, and every `ApiProvider` fetcher
including its failure modes (unreachable, non-2xx, malformed history). When auditing
for coverage gaps, check what's actually there first — this suite is not a blank slate.

A few testing patterns worth knowing:
- **`tests/Feature/HomeTest.php`** calls `$this->withoutVite()` before hitting `/`,
  since `home.blade.php` is the only view that renders `@vite` and no built
  `public/build/manifest.json` exists in a fresh CI checkout — `withoutVite()` swaps
  in a no-op Vite facade for that request instead.
- **Pest's `it()`/`test()` closures bind `$this` to `Tests\TestCase` at runtime**
  (`Closure::bindTo`), which grants protected-method access the same as a real method
  body would get — but PHPStan can't see that a global closure's scope was rebound, so
  it still flags `$this->withoutVite()` as an out-of-scope protected call, and
  `assertSeeLivewire()` (a macro Livewire registers on `TestResponse` at runtime) as an
  undefined method. Both are ignored in `phpstan.neon`, scoped to `tests/*` by exact
  message match — the same class of gap as the enum-casts entry above (PHPStan blind
  to something real but dynamically resolved), not a real bug.
- **`ApiProvider::fetcher()` has a dedicated `tests/Unit/Enums/ApiProviderTest.php`**
  that runs a dataset over every enum case, asserting each resolves to a real
  `ProviderFetcher` — a regression test for the "every case must resolve, non-nullable
  by design" invariant documented above, so a new enum case added without its fetcher
  fails immediately instead of surfacing as a runtime crash on that one card.

### Browser testing (Pest + Playwright) — deliberately isolated from `homie-app`

`tests/Browser/` holds real-browser smoke tests (`pestphp/pest-plugin-browser` +
Playwright/Chromium), run via `composer pest:browser`. This does **not** run inside
the regular `homie-app` container — that image also serves production over the
tunnel (see "Container / infra" above), and browser testing needs a full Node.js
runtime plus a ~300MB Chromium binary that have no business shipping in a production
PHP-apache image. Instead, `docker-compose.yml` defines a second service, `app-test`
(`profiles: ["test"]`, so a plain `docker compose up` never starts it), built from a
`test` stage layered on top of the same `base` stage `app` uses — a YAML anchor
(`&app-dockerfile`) keeps the inline multi-stage Dockerfile defined once and shared
between both services rather than duplicated. `docker/setup-test-container.sh` (the
`test` stage's own setup script, parallel to `setup-dev-container.sh`) installs
Node.js and bakes the Chromium binary + its OS-level deps into the image at build
time via a throwaway `npx playwright@<version> install --with-deps chromium` —
pinned to the exact version in `package.json`/`package-lock.json`, since Pest's
plugin refuses to run against a mismatched Playwright version. The browser binary
lands under `/root/.cache/ms-playwright`, outside the bind-mounted `/var/www/html`,
so it survives even though the project directory itself gets shadowed by the volume
mount at container start; the `playwright` npm package itself, however, **must**
live in the project's own `node_modules` (Pest's `ServerManager` shells out to
`node_modules/.bin/playwright` by a hardcoded relative path), so it's a real
`package.json` devDependency, not something installed only inside the image.

**Merely requiring `pestphp/pest-plugin-browser` breaks the entire Pest suite
everywhere it's installed, not just browser tests.** Its `Plugin::boot()`
unconditionally registers a global `afterEach` hook (scoped to the whole test root,
not `tests/Browser`) that eagerly constructs a `ServerManager` singleton — which
calls `socket_create_listen()` for port allocation — after *every single test*,
regardless of whether that test ever calls `visit()`. Since `vendor/` is shared via
the bind mount between `homie-app` and `app-test`, this meant the regular 169-test
suite went from all-green to all-failing (`Call to undefined function
Pest\Browser\Support\socket_create_listen()`) the moment the package was required,
until `sockets` was added to `homie-app`'s own PHP extensions too (in
`setup-dev-container.sh`, not just the test stage) — a deliberate, narrow exception
to "keep production clean": `ext-sockets` is a small built-in extension with no
external binaries, unlike Node/Chromium, so this doesn't reintroduce the bloat the
separate `app-test` stage exists to avoid. The same reasoning applies to CI's `php`
job, which also needs `sockets` in its `shivammathur/setup-php` extensions list for
exactly the same reason. `ext-pcntl`, by contrast, stays test-stage-only — it's only
needed for `PlaywrightNpmServer::stop()`'s signal handling when a Playwright server
was actually started, which only happens when a test really calls `visit()`.

Browser tests use the same `RefreshDatabase`-backed SQLite test database as
Feature/Unit tests, not real data — `pestphp/pest-plugin-browser`'s
`LaravelHttpServer` serves requests by handing them to the *already-booted* Laravel
kernel from the current test process (same `app()` container, same `.env`/`phpunit.xml`
config), not by shelling out to `php artisan serve` against the real `.env`. `tests/
Pest.php`'s `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in(...)`
list includes `'Browser'` for this reason. Unlike `HomeTest.php`, browser tests do
**not** call `withoutVite()` — the point of a real-browser test is to exercise the
actual built CSS/JS bundle, so `public/build/` must exist (`npm run build`) before
running `composer pest:browser` locally; CI's `browser` job builds it as part of the
`composer install`/`npm install` steps inside the `app-test` container.

## CI (GitHub Actions)

`.github/workflows/ci.yml` runs on every push to `main` and every pull request,
three independent jobs (no `needs:`, so one failing doesn't block the others from
reporting):
- **php**: mirrors the local pre-commit hook's order (Pint `--test` → PHPStan →
  Rector `--dry-run` → Pest), on PHP 8.3 — the floor version declared in
  `composer.json` (`"php": "^8.3"`), not the 8.5 the dev container happens to run,
  so CI actually proves the "clone and it works" claim in the distributability
  principle above rather than only testing Andres's own environment. Uses
  `shivammathur/setup-php` directly on the runner (no Docker) since GitHub-hosted
  runners don't need the container indirection local dev uses for host/UID
  reasons. Its extensions list includes `sockets` — see "Testing" above for why
  that's unavoidable once `pestphp/pest-plugin-browser` is a dependency at all.
  SQLite DB is created fresh (`touch` + `migrate --force`) — no production data
  ever touches CI, consistent with SQLite being gitignored.
- **frontend**: also runs `composer install` before `npm ci && npm run build` —
  `resources/css/app.css` directly `@import`s `vendor/livewire/flux/dist/flux.css`,
  so the build fails outright without `vendor/` present, not just missing styles.
  No Feature/Unit test visits a route that renders `@vite` (`HomeTest.php`
  explicitly disables it), so this job exists purely to catch a broken
  Vite/Tailwind build before merge.
- **browser**: builds the `app-test` image (`docker compose --profile test build`)
  and runs `tests/Browser` inside it, installing Composer/npm deps, **building
  assets** (unlike Feature/Unit tests, `tests/Browser` tests don't call
  `withoutVite()` — see "Browser testing" above — so this step is required, not
  optional), and migrating a fresh SQLite DB, all through `docker compose
  --profile test run` instead of running directly on the runner.

Composer/npm dependencies in the `php` and `frontend` jobs are cached by lockfile
hash; the `browser` job's Docker layer cache is not currently persisted across runs
(each run rebuilds the `app-test` image from scratch, ~1-2 minutes) — worth revisiting
with GitHub Actions' Docker Buildx cache if that cost becomes annoying.

**`composer.json`'s `config.platform.php` is pinned to `8.3.0` — do not remove it.**
The dev container (`homie-app`) runs PHP 8.5, but the declared floor is `^8.3`
(CI's `php`/`frontend` jobs run on 8.3 specifically, to prove that floor is real).
Without this pin, running `composer update`/`require` inside `homie-app` lets
composer's solver resolve packages against the *actual* running 8.5 interpreter —
which is how this repo ended up with `symfony/console` (and a dozen other symfony/*
packages) locked to a v8.1.x line requiring PHP ≥8.4.1, silently breaking `composer
install` on the declared 8.3 floor with no local symptom at all (homie-app's 8.5
happily satisfied it). Found the hard way: CI's first real run failed on `composer
install` with a wall of "your php version (8.3.33) does not satisfy that
requirement" errors, from a lock file that had been broken this way since before
this session even started. Fixed by pinning the platform and running `composer
update` once to relock against genuinely-8.3-compatible versions (symfony/* moved
to their 7.4.x line) — re-pin and re-lock the same way if this ever resurfaces
after a future `composer update`.
