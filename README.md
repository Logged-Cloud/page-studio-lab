# page-studio-lab

The **Dusk + Selenium harness** for [`logged-cloud/page-studio`](https://github.com/Logged-Cloud/page-studio). A real Laravel 13 + Livewire 4 app with the package mounted, used to:

- Run Dusk browser tests against the live editor (drag-and-drop, lock ribbons, collab presence, nested layouts, keyboard shortcuts).
- Capture the README screenshots that ship with the package.
- Reproduce package bugs in an isolated environment without spinning up a host app of your own.

## What's in the tree

| Path | Purpose |
| --- | --- |
| `tests/Browser/` | All Dusk tests. Each file targets one editor feature (drag-drop, collab, finder, mobile layout, nested columns, etc.). |
| `tests/Browser/screenshots/` | Generated PNGs from screenshot-style tests · gitignored. |
| `resources/views/` | Pages that mount the page-studio Livewire components (route-builder, page-builder, variable-library). |
| `routes/web.php` | The handful of routes the Dusk tests hit (`/pages/{id}/edit`, `/variables`, etc.). |

## Local boot

The lab runs as part of the multi-app Docker stack on the dev host:

```
docker compose up -d page-studio-lab page-studio-selenium
docker exec page-studio-lab composer install
docker exec page-studio-lab php artisan migrate --force
```

Then hit http://localhost:8106 for the editor, or kick off the Dusk suite:

```
docker exec page-studio-lab php artisan dusk
docker exec page-studio-lab php artisan dusk tests/Browser/NestedColumnsDndTest.php
```

Selenium runs in the sibling `page-studio-selenium` container; the lab points `DUSK_DRIVER_URL` at it.

## Standalone setup

If you're outside the dev host's compose stack, you need a Chrome/Chromium binary and either:

- A Selenium standalone container with `DUSK_DRIVER_URL=http://your-selenium:4444`, or
- Stock chromedriver via `php artisan dusk:chrome-driver`.

Composer pulls page-studio from its GitHub VCS repo, so a plain `composer install` resolves the latest stable. Pin to a specific tag in `composer.json` if you're testing a release candidate.

## Why a separate repo

The package itself ships a Pest suite that covers pure-PHP rendering, registry, model, and unit-level behaviour. That suite runs anywhere PHP runs, no browser required, and is what gates the package's CI matrix.

The Dusk surface is necessarily heavier · a real browser, a real DOM, real drag events. Keeping it in a separate consumer-shaped repo means:

- The package stays small and fast in CI.
- The lab can drift on PHP / Laravel versions independently.
- The lab can carry per-feature playground views without bloating the package.

The two repos move together for features that need browser-level proof · a nested-columns drag-drop test lands here, the pure recursion proof lands in the package's Pest suite.
