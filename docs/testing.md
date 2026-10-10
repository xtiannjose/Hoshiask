# Testing and quality gate

## The gate

Run this full gate before push/release:

```bash
composer qa
npm.cmd run qa
npm.cmd run qa:full # include Playwright UI and accessibility checks when UI behavior changes

# Or run the individual commands:
composer validate --strict
composer audit --locked --no-interaction
composer analyse
vendor/bin/pint --test
php artisan test

# PowerShell-friendly npm invocations:
npm.cmd run lint
npm.cmd run test:unit
npm.cmd run build
npm.cmd audit --audit-level=high
```

`composer qa` stops at `composer audit` when Packagist cannot be reached; run the remaining steps by hand then.

## What each tool covers

| Command | Checks |
|---|---|
| `php artisan test` | PHPUnit feature and unit tests on in-memory SQLite (about 2 minutes, serial) |
| `composer analyse` | PHPStan |
| `vendor/bin/pint --test` | PHP code style |
| `npm run lint` | ESLint |
| `npm run test:unit` | Vitest, next to the module as `*.test.js` |
| `npm run test:e2e` | Playwright browser tests in `tests/e2e` (needs a seeded database, see below) |
| `npm run qa:full` | everything above plus Playwright |

## Running the browser tests

`playwright.config.js` starts `php artisan serve` on port 8000. CI uses a SQLite file and the `E2eSmokeSeeder`:

```bash
touch database/database.sqlite
# .env: DB_CONNECTION=sqlite, DB_DATABASE=database/database.sqlite, LEGAL_REQUIRE_ACCEPTANCE=false
php artisan migrate --force
php artisan db:seed --class=E2eSmokeSeeder --force
npx playwright install chromium
npm run test:e2e
```

## CI

- CI workflow lives at `.github/workflows/ci.yml` and validates secret scanning, backend checks, frontend checks, and e2e.
- `.github/workflows/codeql.yml` analyzes JavaScript/TypeScript, but the workflow is **disabled in GitHub** (code scanning on a private repository needs GitHub Advanced Security, and its last run failed for that reason). Enable it from the Actions tab if the plan allows, or delete the file.
- PHP static/security checks are handled in CI (PHPStan/composer audit), not CodeQL language matrix.
- GitHub Actions history keeps old failed runs; verify current health using the latest run or workflow badge status.

## Dependency and security checks

Run these regularly (or before release):

```bash
composer diagnose
composer audit --locked
composer outdated --direct
npm audit --audit-level=high
npm outdated
# Windows PowerShell fallback for npm:
# npm.cmd audit --audit-level=high
# npm.cmd outdated
```
