# Contributing to Homie

Thanks for considering a contribution. Homie is a personal project shared publicly, maintained
in spare time, so response times on issues and PRs may vary — but contributions are welcome.

## Getting set up

Follow the [README](README.md#local-development) to get a local instance running with Docker Compose.

## Before you open a PR

Branch off `main` and open a pull request against it. CI runs the checks below plus the
frontend build and the browser tests on every PR, and `main` only accepts commits that
have passed them. Run them locally first:

```bash
composer pint         # code style (auto-fixes)
composer phpstan      # static analysis
composer rector       # modernization, dry-run only
composer pest         # test suite
composer pest:browser # real-browser smoke tests
```

- Keep PRs focused — one feature or fix per PR is easier to review than a bundle of unrelated changes.
- Add or update tests for behavior changes (Pest). Sad paths (validation failures, unauthorized access,
  missing services, failed commands, broken connections) matter as much as the happy path.
- Match the existing code style and architecture: thin Livewire components, with logic that isn't
  UI state in plain classes under `app/Support/` (discovery, config export/import, API
  fetchers). See `CLAUDE.md` for details.
- If you're changing how configuration is stored, loaded, or exported, document the format and
  any migration steps needed.

## Reporting bugs / suggesting features

Open a GitHub issue with enough detail to reproduce (for a bug) or the problem you're trying to solve
(for a feature request). Screenshots and example configurations help.

## Code of conduct

Be respectful and constructive. Disagreements about approach are fine; personal attacks aren't.
