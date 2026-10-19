# Changelog

## Unreleased

## 0.2.2 - 2026-10-19

### Added
- Every response sends `Content-Security-Policy: frame-ancestors 'self'`, so other sites cannot frame the dashboard. To embed it, list the embedding origin in `HOMIE_FRAME_ANCESTORS` alongside the `SESSION_SAME_SITE=none` setting; an empty or malformed value falls back to `'self'`.

### Changed
- Dependency updates (Livewire, Vite, PHPStan, Playwright and GitHub Actions) and a pinned Playwright version in the test container setup.
- Docs: the demo sandbox README states what each defence layer does on its own, and the authentication docs list the routes the login does not cover.

## 0.2.1 - 2026-10-14

### Fixed
- Demo mode: API cards only accept the built-in mock Sonarr/Radarr URLs; previously any URL was fetched from the app container, which let a visitor probe the network the demo runs on. A backup import in demo mode drops API connections to any other URL.
- Demo mode: the seeded "Disk space" output card ran `df -h /`, which the demo allowlist refuses; it now runs `df -h`.
- Demo mode: output cards only accept a fixed set of commands (`uptime`, `df -h`, `date`, `whoami`, `echo <text>`) and run them only over SSH on the demo sandbox; previously any command typed into a demo output card ran in the app container. The sandbox network is now internal-only and the sandbox and mock ports are no longer published on all interfaces.

## 0.2.0 - 2026-10-07

### Added
- Application login (`php artisan homie:make-admin`); every page except `/login` requires it. The demo uses the same login.
- Production Compose file (`docker-compose.production.yml`) and a README section on backups, upgrades and recovering from a lost `APP_KEY`.
- Opt-in owner auto-login for LAN requests or Cloudflare Access (`AUTO_LOGIN_*`).
- Backup import validates the file's structure before replacing the configuration.
- Issue and PR templates, Dependabot configuration.

### Changed
- The owner's demo deployment file is now `docker-compose.demo.yml`.
- Screenshots refreshed; the demo seed no longer includes an unreachable NZBGet card.
- Config export works after `APP_KEY` is lost.
- Docs corrected for shell command execution, contributing and the Git workflow.

### Fixed
- Login bypass: the `X-Livewire` header no longer skips authentication.
- Auto-login's LAN check uses the socket peer address instead of `X-Forwarded-For`.

### Known limitations
- One shared admin login, no per-user accounts.
- Output cards run shell commands as `www-data` in the app container; treat the admin login like shell access.
- SSH discovery does not verify host keys.
- Editing a machine or API card after `APP_KEY` is lost fails until you export and re-import the configuration.

## 0.1.0

First public prerelease. It has no authentication and is not meant to be deployed.
