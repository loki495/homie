# Homie — project context

Self-hosted home lab dashboard. Laravel 13 + Livewire 4 (SFC) + Alpine.js + Tailwind v4 +
Flux UI (free tier). SQLite. Dockerized dev environment behind Traefik
(`homie.dev.local.test`).

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

## Card icons

`app/Support/DashboardIcons.php` searches the free homarr-labs/dashboard-icons index
(no API key) for icons matching common self-hosted app names, letting card creation
suggest an icon for recognized services. Icons are hotlinked from jsDelivr's CDN —
never downloaded or cached locally on this app's storage (a deliberate choice: keeps
things simple, no storage/cleanup concern, matches how Homarr/Dashy do it). The
`metadata.json` index itself is cached server-side for a day via `Cache::remember`.
`Card.icon` just stores a plain URL — either a resolved CDN link or a manually pasted
one, no distinction made at render time.

## Card API auth

`card_apis.auth_type` selects between `api_key` (sent as an `X-Api-Key` header — the
arr-stack convention) and `basic` (username/password, sent via `Http::withBasicAuth`).
Only one is active at a time based on `auth_type`; the unused fields are nulled out on
save so stale credentials from a previous auth-type choice don't linger. `password` is
encrypted at rest the same way `api_key` already was.

## Discovery: don't gate on published ports alone

Both `discoverViaDocker` and `discoverViaSsh` in `⚡machine-manager.blade.php` check
for a Traefik `Host()` label *before* falling back to requiring a host-published port.
Got this wrong once already (shipped a version that required a published port even
when a Traefik label was present) — silently dropped every container that's only
reachable through Traefik's internal Docker-network routing, which is the common case
when only the reverse proxy itself publishes ports. If touching discovery again, keep
the label-first ordering.

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

- App container: `homie-app` (PHP 8.5-apache). Vite container: `homie-vite`.
- Run PHP tooling via `docker exec -u www-data homie-app ...` — use `-u www-data`
  (not root) so files stay owned by UID 1000, matching the host user on the bind mount.
  Composer script wrappers (`composer pint`, `phpstan`, `rector`, `pest`) already do this.
- SQLite database file lives at `database/database.sqlite`, gitignored (it will hold
  real lab config once the app is used — never commit it).
- `storage/ssh/` is mounted into the container for the user's own SSH keys, for
  ad-hoc use in "output" card commands (arbitrary shell commands the user supplies —
  it's on the user to make sure they run correctly in the container). Gitignored
  except `.gitkeep`.
- Machine-based SSH discovery is separate from the above: each `Machine` can store
  its own encrypted private key (`machines.ssh_private_key`), written to a 0600 temp
  file only for the duration of a scan. Never read from `storage/ssh/` — keeps
  discovery credentials scoped per target instead of one shared host-mounted key.

## Tooling

Default PHPStan level 6, Pint `laravel` preset, Rector `UP_TO_PHP_84` + code quality/dead
code sets (dry-run only — never auto-apply without reviewing the diff). Pre-commit hook
(symlinked from `~/.claude/hooks/laravel-pre-commit.sh`) runs Pint (auto-fix) → PHPStan
(block) → Rector dry-run (block) → Pest (block) on staged PHP files.

## Git

Single-branch: `main`. This project intentionally opts out of the global master/local
branch model (see `~/.claude/CLAUDE.md`) — there's no separate production deployment to
mirror, so work happens directly on `main`. Repo: `loki495/homie` on GitHub (public).
