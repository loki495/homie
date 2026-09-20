#!/usr/bin/env bash
set -e

# Every artisan call here runs as www-data, never root — a root-run seed leaves
# storage/ssh/{slug} (synced by MachineObserver) root-owned and unreadable by
# the www-data-run Apache workers that actually execute output-card commands on
# a real request. Confirmed directly: see docker/ssh-sandbox/README.md.
run_as_www_data() {
    su -s /bin/sh www-data -c "$1"
}

# Named production volumes mount over the image-owned directories. Ensure the
# Apache worker can write sessions, logs, SSH keys, and the SQLite database
# before Artisan creates cache or migration files in them.
chown -R www-data:www-data /var/www/html/storage

if [ "${DEMO_MODE:-false}" = "true" ]; then
    # Demo data isn't real user data - rebuilding the template fresh on every
    # boot (migrate:fresh + reseed, see BuildDemoTemplate) keeps each deploy
    # stateless by design, matching ResolveDemoDatabase's own per-visitor-copy
    # model. No volume needed for any of this.
    run_as_www_data "php artisan demo:build-template"
else
    # Non-demo (a real self-hoster's own deployment) - Laravel doesn't create a
    # missing SQLite file on its own, so migrate would just fail against a fresh
    # volume otherwise.
    db_path="${DB_DATABASE:-/var/www/html/storage/app/database.sqlite}"
    if [ ! -f "$db_path" ]; then
        mkdir -p "$(dirname "$db_path")"
        touch "$db_path"
        chown www-data:www-data "$db_path"
    fi
    run_as_www_data "php artisan migrate --force"
fi

exec "$@"
