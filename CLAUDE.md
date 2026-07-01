# Homie — project context

Self-hosted home lab dashboard. Laravel 13 + Livewire 4 (SFC) + Alpine.js + Tailwind v4.
SQLite. Dockerized dev environment behind Traefik (`homie.dev.local.test`).

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
- `storage/ssh/` is mounted into the container for the user's own SSH keys (used by
  "output" cards that run commands on other LAN machines). Gitignored except
  `.gitkeep`. SSH is not a first-class feature — output-card commands are arbitrary
  shell commands the user supplies; it's on the user to make sure they run correctly
  in the container.

## Tooling

Default PHPStan level 6, Pint `laravel` preset, Rector `UP_TO_PHP_84` + code quality/dead
code sets (dry-run only — never auto-apply without reviewing the diff). Pre-commit hook
(symlinked from `~/.claude/hooks/laravel-pre-commit.sh`) runs Pint (auto-fix) → PHPStan
(block) → Rector dry-run (block) → Pest (block) on staged PHP files.

## Git

Single-branch: `main`. This project intentionally opts out of the global master/local
branch model (see `~/.claude/CLAUDE.md`) — there's no separate production deployment to
mirror, so work happens directly on `main`. Repo: `loki495/homie` on GitHub (public).
