# Set up a development machine

How to get the app running locally. For servers, see [operations.md](operations.md).

## Prerequisites

- PHP 8.3+ with the extensions `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml` (`pdo_sqlite` too if you run the tests)
- Composer
- Node.js >= 22.12.0 and npm
- PostgreSQL (default) or MySQL/MariaDB

## Install and run

1. Install dependencies:
```bash
composer install
npm install
# Windows PowerShell fallback if npm script execution is restricted:
# npm.cmd install
```

2. Configure environment:
```bash
cp .env.example .env
# Windows PowerShell alternative:
# Copy-Item .env.example .env
php artisan key:generate
```

3. Configure database values in `.env` (`DB_*`) and run migrations:
```bash
php artisan migrate --seed
php artisan storage:link
```

4. Build frontend assets:
```bash
npm run build
# Windows PowerShell fallback:
# npm.cmd run build
```

5. Run the app:
```bash
php artisan serve
npm run dev
# Windows PowerShell fallback:
# npm.cmd run dev
```

The tests do not need a database server: they use SQLite in memory.

## Seeded accounts

After `php artisan migrate --seed`, these accounts are created:

- `admin@example.com` (`admin`)
- `shadow@example.com` (`shadow`)
- `super.user@example.com` (`super_user`)
- `technical@example.com` (`technical`)
- `client.one@example.com` (`client`)
- `client.two@example.com` (`client`)

Password behavior:

- Staff seeded users (`admin`, `super_user`, `technical`) use `STAFF_DEFAULT_PASSWORD` and must change password on first login.
- Shadow user password comes from `SHADOW_PASSWORD`.
- Client users use:
  - `CLIENT_DEFAULT_PASSWORD` when `CLIENT_PASSWORD_MODE=fixed`
  - random 10-character passwords when `CLIENT_PASSWORD_MODE=random`
- In random client mode, one-time plaintext credentials are written to a private handoff file under `storage/app/private/seeded-client-credentials/`.
- If required password env values are missing, seeding fails fast instead of silently using hardcoded credentials.

## Windows notes

- In PowerShell, call `npm.cmd` instead of `npm` when script execution is restricted, and `Copy-Item .env.example .env` instead of `cp`.
- `composer` may fail with `curl error 60`; fix it as follows.

If Composer commands show `curl error 60` (`SSL certificate problem: unable to get local issuer certificate`):

1. Download the latest CA bundle from `https://curl.se/ca/cacert.pem`.
2. Save it to a stable path, for example `C:\tools\php\certs\cacert.pem`.
3. Update your active `php.ini`:
   - `curl.cainfo="C:\tools\php\certs\cacert.pem"`
   - `openssl.cafile="C:\tools\php\certs\cacert.pem"`
4. Restart terminal/IDE, then verify:

```bash
php -i | findstr /I "curl.cainfo openssl.cafile"
composer diagnose
```
