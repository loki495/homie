#!/usr/bin/env bash
set -e

# ext-sockets already comes from the shared `base` stage (see
# docker/setup-dev-container.sh) — every environment running this project's Pest
# suite needs it regardless of browser tests. ext-pcntl is test-stage-only: without
# it, PlaywrightNpmServer::stop() throws "Undefined constant SIGTERM" while tearing
# down the Playwright server process after an actual browser test run, failing the
# whole suite with exit code 1 even when every test itself passed.
docker-php-ext-install pcntl
docker-php-ext-enable pcntl

# Node.js is required to run the project's node_modules/.bin/playwright binary —
# Pest's browser plugin shells out to it directly (see ServerManager::command()),
# it does not talk to Playwright via any PHP-native driver.
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y --no-install-recommends nodejs
rm -rf /var/lib/apt/lists/*

# Bake the Chromium binary + its OS-level deps (fonts, libnss3, ...) into the image
# at build time via a throwaway npx-fetched Playwright CLI, pinned to the exact
# version in package.json/package-lock.json — Pest's plugin refuses to run against
# a mismatched Playwright version (PlaywrightOutdatedException). The browser cache
# lands under /root/.cache/ms-playwright, outside the bind-mounted /var/www/html,
# so it survives even though the project directory itself gets shadowed by the
# volume mount at container start.
npx -y playwright@1.62.1 install --with-deps chromium
