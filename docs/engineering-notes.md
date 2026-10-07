# Engineering notes

Problems that were worth writing down, and what each one changed. The architecture and
conventions live in [CLAUDE.md](../CLAUDE.md); the README lists the major decisions.

## Per-visitor demo databases, and the middleware order that broke them

Homie has no per-user data model, so concurrent demo visitors would share and overwrite
one database. `ResolveDemoDatabase` gives each visitor a cookie that maps to a private
copy of a template SQLite file. Two ordering rules came out of it:

- It must run before `RequireAuthentication`, because the login check queries the
  `users` table of the visitor's own copy. A real-browser test caught the wrong order.
- Repointing `database.connections.sqlite.database` is not enough if the connection was
  already resolved in the same process; the middleware also calls `DB::purge('sqlite')`.
  That conflicts with `RefreshDatabase`'s shared in-memory connection, so the tests that
  exercise it roll back the real PDO themselves (see "Demo mode" in CLAUDE.md).

## Pinning the Composer platform to the declared PHP floor

The dev container runs PHP 8.5, but `composer.json` declares `^8.3`. Without
`config.platform.php`, `composer update` inside the container resolved Symfony packages
that need PHP 8.4+, and nothing failed locally. CI, which runs on 8.3, failed on
`composer install`. The platform is now pinned to `8.3.0`, so the lock file is always
installable on the declared floor.

## Larastan was blind to enum casts

Larastan only read casts from the legacy `$casts` property, not the `casts()` method this
project's models use, so every enum-cast property was typed as `string` and a misuse like
`->value` on it went unnoticed. Setting `parseModelCastsMethod: true` in `phpstan.neon`
fixed it, and nothing else surfaced. Extracting logic out of Blade files into `app/`
had the same effect: PHPStan excludes `resources/views`, so that code had never been
analysed.

## Why a spoofable header can't be a trust boundary

Two security fixes had the same cause. CSRF used to be waived for private IPs, and the
login check used to skip any request with an `X-Livewire` header; both relied on
something the client controls (`X-Forwarded-For` behind `trustProxies('*')`, or an
arbitrary header). The CSRF fix was to make the session cookie work in the embedding
iframe (`SameSite=None; Secure`) instead of exempting requests. The login fix exempts
only Livewire's update route and re-runs the auth middleware on each update as Livewire
persistent middleware. Auto-login now reads the socket peer address.
