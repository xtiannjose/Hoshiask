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
| `php artisan test` | PHPUnit feature and unit tests on in-memory SQLite (about 2 to 3 minutes, serial) |
| `php artisan test --parallel` | The same tests across CPU cores with ParaTest (about 70 seconds on 8 cores). Each process has its own in-memory database. Use it while working; `composer qa` and CI stay serial |
| `composer analyse` | PHPStan |
| `vendor/bin/pint --test` | PHP code style |
| `npm run lint` | ESLint |
| `npm run test:unit` | Vitest, next to the module as `*.test.js` |
| `npm run test:e2e` | Playwright browser tests in `tests/e2e` (needs a seeded database, see below) |
| `npm run qa:full` | everything above plus Playwright |

## Running the browser tests

`playwright.config.js` starts `php artisan serve` on port 8000 and refuses to start when something already answers there, because the tests sign in and post forms to whatever is on that port. CI uses a SQLite file and the `E2eSmokeSeeder`:

```bash
touch database/database.sqlite
# .env: DB_CONNECTION=sqlite, DB_DATABASE=database/database.sqlite, LEGAL_REQUIRE_ACCEPTANCE=false
php artisan migrate --force
php artisan db:seed --class=E2eSmokeSeeder --force
npx playwright install chromium
npm run build
npm run test:e2e
```

Run `npm run build` again after any change under `resources/js` or `resources/css`: the browser tests use the built files in `public/build`.

### On your own machine, without touching your development database

The seeder and the tests write accounts and tickets, so give them a database of their own. `.env.e2e` is ignored by git; Laravel reads it instead of `.env` while `APP_ENV=e2e` is set.

```powershell
# 1. An env file that points at an empty SQLite file:
$db = "$env:TEMP\helpdesk-e2e.sqlite"; New-Item -ItemType File -Path $db -Force | Out-Null
@("APP_ENV=e2e", "APP_KEY=$(php artisan key:generate --show)", "APP_DEBUG=false", "APP_URL=http://127.0.0.1:8765",
  "DB_CONNECTION=sqlite", "DB_DATABASE=$db", "SESSION_DRIVER=database", "CACHE_STORE=database",
  "QUEUE_CONNECTION=sync", "MAIL_MAILER=log", "LEGAL_REQUIRE_ACCEPTANCE=false") | Set-Content .env.e2e -Encoding ascii

# 2. Migrate and seed that database, then start a server on a free port with the same settings:
$env:APP_ENV = "e2e"
php artisan migrate --force
php artisan db:seed --class=E2eSmokeSeeder --force
php artisan serve --no-reload --host=127.0.0.1 --port=8765      # leave running, use a second terminal below

# 3. In the second terminal:
$env:APP_ENV = "e2e"; $env:PLAYWRIGHT_BASE_URL = "http://127.0.0.1:8765"
npm run test:e2e
```

Delete `.env.e2e` and the SQLite file when you are done. Setting `PLAYWRIGHT_BASE_URL` stops Playwright from starting its own server; `PLAYWRIGHT_REUSE_SERVER=1` lets it use one already running on port 8000.

The login screenshots in `tests/e2e/accessibility-visual.spec.js-snapshots` are what the sign-in page looks like with the brand in `.env.example`. After a change to the sign-in page, the logo or the brand name, regenerate them with `npx playwright test tests/e2e/accessibility-visual.spec.js --update-snapshots=all` and commit the three PNG files.

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
