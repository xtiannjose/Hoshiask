# Testing and the quality gate

## The gate

Run this before you push. It is what CI runs.

```bash
composer qa
npm run qa                # PowerShell: npm.cmd run qa
npm run qa:full           # the same plus the browser tests (read "Running the browser tests" first)
```

`composer qa` runs `composer validate --strict`, `composer audit`, PHPStan, `pint --test` and `php artisan test`, and stops at the first failure. If Packagist cannot be reached it stops at `composer audit`: run the remaining steps by hand then. `npm run qa` runs ESLint, the JavaScript tests, the build and `npm audit`.

## What each tool does

| Command | Checks |
|---|---|
| `php artisan test` | PHPUnit tests on SQLite in memory (about 3 minutes, one process). |
| `php artisan test --parallel` | The same tests across your CPU cores with ParaTest (about 70 seconds on 8 cores). Each process has its own in-memory database. Use it while you work. |
| `php artisan test --filter=NameOfClassOrTest` | One test class or one `test_` method. |
| `composer analyse` | PHPStan (level 5). |
| `vendor/bin/pint --test` | PHP code style. `vendor/bin/pint` fixes it. |
| `npm run lint` | ESLint. `npm run lint:fix` fixes what it can. |
| `npm run test:unit` | Vitest, the JavaScript tests. They sit next to their module as `*.test.js`. |
| `npm run test:e2e` | Playwright browser tests in `tests/e2e`. |

## Writing tests

- **Feature tests** (`tests/Feature`) go through the whole app: an HTTP request, a command, the database. **Unit tests** (`tests/Unit`) check one class.
- Use the helpers in `tests/Traits/CreatesTestData`: `createClient`, `createSupportUser`, `createAssignedSupportUser` (a technician), `createUser` for any role, `createCategory` and `createTicket`.
- `tests/TestCase.php` already fakes the attachments disk, switches Vite off and seeds the starter departments and categories (`StarterDataSeeder`) the way a new installation gets them, since migrations hold no data. `phpunit.xml` sets in-memory SQLite, an array cache, a synchronous queue and a mail array.
- `tests/Support/TestDatabaseGuard` refuses to run the suite on a real database: it only accepts SQLite in memory, or a database whose name ends in `_test`. This is because `RefreshDatabase` wipes the database.
- Guard tests such as `UiConsistencyGuardTest` and `QaAccessibilityMarkupTest` read the source text of Blade and JavaScript files. If you rename a shared partial or class, update them on purpose.
- A bug fix comes with a test that fails without the fix. To check, stash only the fix and run the test.
- Every query must work on SQLite, PostgreSQL and MySQL. CI runs the suite on PostgreSQL 16 as well.

## Running on PostgreSQL

Do this when you change a query, a migration or anything that depends on the database. Make a scratch database whose name ends in `_test`, because the suite wipes it:

```bash
psql -U postgres -c "CREATE DATABASE helpdesk_test;"
```

Then run the suite with these settings (PowerShell shown; in bash put them before the command):

```powershell
$env:DB_CONNECTION = "pgsql"; $env:DB_HOST = "127.0.0.1"; $env:DB_PORT = "5432"
$env:DB_DATABASE = "helpdesk_test"; $env:DB_USERNAME = "postgres"; $env:DB_PASSWORD = "your-password"
$env:CI_EXPECT_DB_DRIVER = "pgsql"     # makes a test fail if the suite is not really on PostgreSQL
php artisan test                        # about 5 minutes
```

Close the terminal (or remove the variables) afterwards and drop the scratch database.

## Running the browser tests

`playwright.config.js` starts `php artisan serve` on port 8000 and **refuses to start when something already answers there**, because the tests sign in and post forms to whatever is on that port. CI uses a SQLite file and the `E2eSmokeSeeder`:

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

The seeder and the tests write accounts and tickets, so give them a database of their own. `.env.e2e` is ignored by git. Laravel reads it instead of `.env` while `APP_ENV=e2e` is set.

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

Delete `.env.e2e` and the SQLite file when you are done. Setting `PLAYWRIGHT_BASE_URL` stops Playwright from starting its own server. `PLAYWRIGHT_REUSE_SERVER=1` lets it use a server that already runs on port 8000.

The login screenshots in `tests/e2e/accessibility-visual.spec.js-snapshots` show the sign-in page with the brand in `.env.example`. After a change to the sign-in page, the logo or the brand name, regenerate them with `npx playwright test tests/e2e/accessibility-visual.spec.js --update-snapshots=all` and commit the three PNG files.

When a browser test clicks something that a page script wires up, wait for `html[data-page-scripts-ready]` first, as the existing specs do. The script sets it when the page's scripts have started.

## CI

The workflow `.github/workflows/ci.yml` runs on every push to `main` and every pull request. It asks for a read-only token, and a new push to the same pull request cancels the run that is still going.

| Job | What it does |
|---|---|
| `secret-scan` | Looks for passwords and keys in the files (gitleaks). The other jobs wait for it. |
| `verify` | Composer validate and audit, PHPStan, Pint, the PHP tests on SQLite, a syntax check of every PHP file, then ESLint, the JavaScript tests, `npm audit` and the build. |
| `postgres` | The PHP tests again, on a real PostgreSQL 16 server. It fails if the suite is not really on PostgreSQL. |
| `e2e` | The Playwright browser tests on Windows, on a SQLite file seeded with `E2eSmokeSeeder`. |

`.github/workflows/audit.yml` runs `composer audit` and `npm audit` on the locked versions every Monday (and on demand from the Actions tab), because a new advisory can appear in a week with no push.

`.github/workflows/codeql.yml` analyses the JavaScript, but the workflow is **disabled in GitHub**: code scanning on a private repository needs GitHub Advanced Security, and its last run failed for that reason. Enable it from the Actions tab if your plan allows it, or delete the file. CodeQL does not support PHP, so PHP is covered by PHPStan and `composer audit`. GitHub keeps old failed runs: judge the project by the latest run or the badge in the README.

## Dependency and security checks

Run these now and then, and before a release:

```bash
composer diagnose
composer audit --locked
composer outdated --direct
npm audit --audit-level=high
npm outdated
# PowerShell: use npm.cmd for the npm commands
```

Dependabot opens weekly pull requests for Composer, npm and GitHub Actions (`.github/dependabot.yml`).
