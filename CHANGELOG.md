# Changelog

## 0.2.0 - 2026-10-07

### Added
- Application login (`php artisan homie:make-admin`); every route except `/login` requires it. The demo uses the same login.
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
