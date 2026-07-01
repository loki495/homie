# Homie

A self-hosted, configurable homepage/dashboard for home lab services — cards for each
service or module, grouped and reorderable, with live output widgets (CPU/mem/disk,
custom commands) and optional API integrations for common self-hosted apps (the *arr
stack, nzbget, etc).

Built to be **distributable**: no service, machine, or credential is hardcoded anywhere
in the app. Everything — services, machine targets, output-card commands, API
connections, card order, and groups — is user-configured data, not code.

## Stack

- Laravel 13
- Livewire 4 (single-file components) + Alpine.js (bundled with Livewire)
- Tailwind CSS v4 (via `@tailwindcss/vite`)
- SQLite
- Docker + Traefik for local development

## Planned features

- Service cards that open the linked site on click
- Docker service discovery: save a scan target (name + host) in Settings, run a manual
  scan against its Docker Engine API, and turn discovered containers into cards
- Manual custom links for anything discovery doesn't cover
- Editable "output" cards: user-defined shell commands (local or remote, e.g. via SSH),
  run non-blockingly on each page load, rendering raw output (disk space, load, etc.)
- API-connected cards for services with an API (arr stack, nzbget, and similar)
- Drag-and-drop card reordering
- Expandable/collapsible groups ("folders") of cards

## Local development

Requires Docker and a `web` external Docker network with Traefik routing
`*.dev.local.test` domains to their containers.

```bash
docker compose up -d --build
docker compose run --rm vite npm install   # first time only
```

Site: http://homie.dev.local.test
Vite dev server (HMR): http://vite.homie.dev.local.test

### Common commands

Run from the host — these wrap `docker exec` into the `homie-app` container:

```bash
composer pint         # code style (auto-fix)
composer phpstan      # static analysis (level 6)
composer rector       # modernization suggestions (dry-run only)
composer rector:apply # apply rector changes (review the diff first)
composer pest         # run the test suite
```

### Ownership note

Commands that write files inside the container should run as `www-data` (UID 1000,
matching the host user) to avoid root-owned files on the bind mount:

```bash
docker exec -u www-data homie-app <command>
```

The composer script wrappers above already do this. If you run `docker exec` directly
as root and end up with permission errors editing files afterward, fix ownership with:

```bash
docker exec -u root homie-app chown -R 1000:1000 /var/www/html
```

## Git workflow

Single-branch: `main`. This project doesn't use the master/local split — work happens
directly on `main`.
