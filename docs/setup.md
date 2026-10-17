# Set up a development machine

How to get Hoshiask running on your own computer. It takes about 20 minutes. For a server, see [operations.md](operations.md).

## What you need

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3 or newer | Needs these extensions: `ctype`, `dom`, `fileinfo`, `filter`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pdo`, `phar`, `session`, `simplexml`, `tokenizer`, `xml`, `xmlwriter`, `zlib`. Most are switched on in a normal PHP. Check with `php -m`. |
| PHP database driver | | `pdo_pgsql` for PostgreSQL, `pdo_mysql` for MySQL or MariaDB, `pdo_sqlite` for SQLite. Tests always use `pdo_sqlite`. |
| Optional PHP extensions | | `zip` (only to import `.xlsx` files), `intl` (for `php artisan db:show`). |
| Composer | 2 | |
| Node.js and npm | Node 22.12 or newer | |
| A database | | PostgreSQL 16 is what production and CI use. SQLite needs no server and is the quickest way to try the app. |

## Install and run

**1. Get the code and install the packages**

```bash
git clone https://github.com/xtiannjose/Hoshiask.git
cd Hoshiask
composer install
npm install
```

**2. Create your `.env` file**

```bash
cp .env.example .env            # PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

**3. Edit `.env`.** Open it in an editor and set the following. Every setting is explained in [configuration.md](configuration.md).

*The database.* Pick one:

- **SQLite** (quickest). Create an empty file, then set two lines:

  ```bash
  touch database/database.sqlite      # PowerShell: New-Item database/database.sqlite -ItemType File
  ```

  ```text
  DB_CONNECTION=sqlite
  DB_DATABASE=database/database.sqlite
  ```

- **PostgreSQL** (like production). Create a database and a user, then fill in the `DB_*` lines:

  ```bash
  psql -U postgres -c "CREATE USER hoshiask WITH PASSWORD 'choose-a-password';"
  psql -U postgres -c "CREATE DATABASE hoshiask OWNER hoshiask;"
  ```

  ```text
  DB_CONNECTION=pgsql
  DB_HOST=127.0.0.1
  DB_PORT=5432
  DB_DATABASE=hoshiask
  DB_USERNAME=hoshiask
  DB_PASSWORD=choose-a-password
  ```

*Three passwords. This step cannot be skipped.* They are blank in `.env.example`, and the next step stops with `Missing required password configuration: SHADOW_PASSWORD` until you fill them in. Choose your own:

```text
STAFF_DEFAULT_PASSWORD=choose-a-staff-password
CLIENT_DEFAULT_PASSWORD=choose-a-client-password
SHADOW_PASSWORD=choose-a-shadow-password
```

*Three settings that make development easier:*

```text
APP_URL=http://127.0.0.1:8000
QUEUE_CONNECTION=sync
MAIL_MAILER=log
```

`QUEUE_CONNECTION=sync` sends queued work at once, so you do not need a queue worker. `MAIL_MAILER=log` writes emails to `storage/logs` instead of sending them. If you also want to skip the terms screen at sign-in, add `LEGAL_REQUIRE_ACCEPTANCE=false`.

**4. Create the tables and the sample data**

```bash
php artisan migrate --seed
php artisan storage:link
```

The seeder creates 6 accounts (see below), the 13 starter departments, 5 categories and 8 sample tickets. The departments and categories come from `StarterDataSeeder`, not from the migrations, and it only fills an empty table: on a database that was migrated without `--seed`, run `php artisan db:seed --class=StarterDataSeeder` (without departments every user form refuses the department, and `hoshiask:ops-status` says so). The knowledge base and the canned responses start empty: `php artisan db:seed --class=StarterContentSeeder` adds 7 starter articles and 7 canned responses, and is safe to run again.

**5. Start the app**

```bash
npm run build           # once; PowerShell: npm.cmd run build
php artisan serve
```

Open http://127.0.0.1:8000/login. While you change the front end, run `npm run dev` in a second terminal instead of building each time.

## Sign in

You can sign in with the username or the email address.

| Username | Email | Role | Password |
|---|---|---|---|
| `admin` | `admin@example.com` | admin | `STAFF_DEFAULT_PASSWORD` |
| `super.user` | `super.user@example.com` | super user | `STAFF_DEFAULT_PASSWORD` |
| `technical` | `technical@example.com` | technician | `STAFF_DEFAULT_PASSWORD` |
| `shadow` | `shadow@example.com` | shadow (hidden monitor account) | `SHADOW_PASSWORD` |
| `client.one` | `client.one@example.com` | client | `CLIENT_DEFAULT_PASSWORD` |
| `client.two` | `client.two@example.com` | client | `CLIENT_DEFAULT_PASSWORD` |

- Admin, super user and technician must choose a new password the first time they sign in.
- With `CLIENT_PASSWORD_MODE=random` each client gets a random password instead. It is written once to a private file in `storage/app/private/seeded-client-credentials/`.
- Staff land on `/admin/dashboard`, clients on `/client/dashboard`.
- To put accounts back to the passwords in `.env`, run their seeders: `php artisan db:seed --class=ShadowSeeder`, `--class=AdminSeeder` or `--class=UserSeeder` (super user, technician and the clients). Do not run the plain `php artisan db:seed` again: it adds 8 more sample tickets each time.
- Eight wrong passwords lock a login for 15 minutes. `php artisan hoshiask:unlock-login <username>` lifts the lock.

## Emails, reminders and automatic jobs

The scheduler sends reminders, closes old resolved tickets and makes backups. While you develop, you can leave it off. To try these jobs, run each in its own terminal:

```bash
php artisan schedule:work      # the scheduled jobs
php artisan queue:work         # only needed when QUEUE_CONNECTION is not sync
```

## Run the tests

```bash
php artisan test               # PHP tests, SQLite in memory, no database server needed (about 3 minutes)
php artisan test --parallel    # the same across your CPU cores (about 70 seconds)
npm run test:unit              # JavaScript tests
composer qa && npm run qa      # everything CI checks
```

More in [testing.md](testing.md).

## Windows notes

- In PowerShell, call `npm.cmd` instead of `npm` when script running is restricted, and use `Copy-Item` instead of `cp`.
- If `php` or `composer` is not found after installing, add its folder to the `PATH` and open a new terminal.
- If port 8000 is already used by another program, start the app on another port: `php artisan serve --port=8123`, and set `APP_URL` to match.
- If Composer shows `curl error 60` (`SSL certificate problem: unable to get local issuer certificate`):

  1. Download the latest CA bundle from `https://curl.se/ca/cacert.pem`.
  2. Save it to a stable path, for example `C:\tools\php\certs\cacert.pem`.
  3. In your active `php.ini` set `curl.cainfo="C:\tools\php\certs\cacert.pem"` and `openssl.cafile="C:\tools\php\certs\cacert.pem"`.
  4. Open a new terminal and check: `php -i | findstr /I "curl.cainfo openssl.cafile"` and `composer diagnose`.

## If something goes wrong

| You see | What to do |
|---|---|
| `Missing required password configuration: ...` | Fill in the three passwords in `.env` (step 3), then run `php artisan db:seed`. |
| `Vite manifest not found` | The front end is not built. Run `npm run build`. |
| `SQLSTATE ... Connection refused`, or `Database file ... does not exist` | The database is not running, the `DB_*` lines are wrong, or the SQLite file was not created (step 3). |
| `Too many sign-in attempts for this account` | Wait 15 minutes, or run `php artisan hoshiask:unlock-login <username>`. |
