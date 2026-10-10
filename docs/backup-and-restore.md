# Backup and restore

This is the step-by-step guide for keeping the helpdesk safe and for rebuilding it on a new server. You do not need to be a programmer to follow it, but you need to be able to type commands on the server.

## What a backup is

Every night at 02:40 the server runs `php artisan helpdesk:backup`. It makes **one file**, for example `helpdesk-20261008-024000.tar.enc`, in `storage/app/private/backups/`. That one file holds:

- the whole database (every ticket, reply, account, setting and log), as a compressed dump;
- every uploaded file: ticket and reply attachments, and department logos;
- a list of everything inside with a fingerprint (SHA-256) of each part, so damage can be detected.

The file is **encrypted** with a key that only you keep. Right after making it, the server opens it again and checks every part. Every Sunday at 04:20 it checks the newest backup again (`helpdesk:backup-verify`). Backups older than 14 days (`HELPDESK_BACKUP_RETENTION_DAYS`) are removed from the server, but the newest three are always kept, so a run of failed nights can never leave you with none.

**One backup file plus the key is enough to rebuild all the data.** You also need the `.env` settings (database password, mail password, `APP_KEY`), so keep a copy of `.env` in your password manager as well.

Files on a remote disk (for example if `ATTACHMENTS_DISK` points to S3) are not in the backup; they already live away from this server. The backup says so when it runs.

## One-time setup

1. Make a key:

   ```bash
   php artisan helpdesk:backup-key
   ```

   It prints a line starting with `base64:`. It is not saved anywhere.

2. Store the key **outside the server**: in your password manager, and a printed copy in a safe place. Without it no backup can be opened, and nobody can recover it for you. Do not reuse `APP_KEY` or any password for it.

3. Put it in the server's `.env`:

   ```
   HELPDESK_BACKUP_ENCRYPTION_KEY=base64:...the key...
   ```

4. Choose where the second copy goes (next section) and set it.

5. Apply the settings and run the first backup and check it:

   ```bash
   php artisan config:clear
   php artisan helpdesk:backup
   php artisan helpdesk:backup-verify
   php artisan helpdesk:ops-status
   ```

   `helpdesk:backup-verify` must end with "This backup is complete and usable". `helpdesk:ops-status` lists any remaining backup advice.

Until a key is set, nightly backups are made **unencrypted**, a warning is printed, and they are never copied away.

## The second copy (do not skip this)

A backup that only exists on the server is lost together with the server: a failed disk, a mistaken delete or ransomware takes both. Pick one of these.

**A. A second disk or a mounted network drive.** Mount it (for example at `/mnt/helpdesk-backups`), make it writable by the web server user, and set:

```
HELPDESK_BACKUP_COPY_PATH=/mnt/helpdesk-backups
```

Each night the encrypted backup is copied there, the copy is checked, and copies older than 14 days are removed (only files named `helpdesk-*.tar.enc` / `helpdesk-*.tar`). Check the copy with `php artisan helpdesk:backup-verify --copy`.

**B. Your own copy job**, from the server or pulled by another machine. Run it after 02:40 (for example at 03:30), then set `HELPDESK_BACKUP_OFFSITE_CONFIRMED=true` so the status check stops warning. Examples (replace names and paths):

```bash
# rsync over ssh, from the server to another machine
30 3 * * * rsync -a /var/www/helpdesk/storage/app/private/backups/helpdesk-*.tar.enc backup@other-host:/srv/helpdesk-backups/

# scp, pulled by the other machine (run there)
30 3 * * * scp 'helpdesk@server:/var/www/helpdesk/storage/app/private/backups/helpdesk-*.tar.enc' /srv/helpdesk-backups/

# rclone to any S3 bucket, SFTP server or Google Drive set up with `rclone config` (remote named "offsite")
30 3 * * * rclone copy /var/www/helpdesk/storage/app/private/backups offsite:helpdesk-backups --include "helpdesk-*.tar.enc"
```

The files are encrypted, so a cloud provider cannot read them. Remove old files on the far side with its own rules (rclone `delete --min-age 30d`, a bucket lifecycle rule).

**When to change approach:** each backup holds every uploaded file. Once attachments pass about 5 GB, nightly full copies get slow and large; then copy the attachments folder with rsync instead and keep this backup for the database.

## Restoring on a brand-new server

You need: the newest backup file (from the second copy), the backup key, and the old `.env` (from the password manager).

1. Install the helpdesk on the new server as in [setup.md](setup.md) (code, `composer install`, `npm ci && npm run build`), and put the old `.env` in place. Point `DB_*` at a **new, empty** PostgreSQL database:

   ```bash
   sudo -u postgres createuser --pwprompt helpdesk
   sudo -u postgres createdb --owner=helpdesk helpdesk
   ```

2. Unpack the backup into an empty folder:

   ```bash
   php artisan helpdesk:backup-restore /path/to/helpdesk-20261008-024000.tar.enc /tmp/restore --key="base64:..."
   ```

   If the Laravel app cannot run yet, use the standalone script (needs only PHP with openssl) and any tar program:

   ```bash
   php scripts/helpdesk-backup-decrypt.php helpdesk-20261008-024000.tar.enc helpdesk.tar "base64:..."
   mkdir /tmp/restore && tar -xf helpdesk.tar -C /tmp/restore
   ```

3. Load the database:

   ```bash
   gunzip -c /tmp/restore/database/dump.sql.gz | psql --set ON_ERROR_STOP=1 -h 127.0.0.1 -U helpdesk -d helpdesk
   ```

4. Put the files back:

   ```bash
   mkdir -p storage/app/private/attachments storage/app/public/department-logos
   cp -a /tmp/restore/files/attachments/. storage/app/private/attachments/
   cp -a /tmp/restore/files/department-logos/. storage/app/public/department-logos/
   chown -R www-data:www-data storage
   ```

5. Finish:

   ```bash
   php artisan migrate --force
   php artisan storage:link
   php artisan optimize
   php artisan helpdesk:ops-status
   ```

6. Smoke test: sign in as an administrator, open a recent ticket, download one of its attachments, and send yourself a test reply.

Then delete `/tmp/restore` and `helpdesk.tar`: they are not encrypted.

**About `APP_KEY`:** use the old one from `.env`. The only data that depends on it are pending one-time temporary passwords (credential handoffs that the shadow account issued but has not revealed yet). With a different `APP_KEY` those cannot be read and must be issued again, and everyone has to sign in again; nothing else is lost. Backups do not depend on `APP_KEY` at all.

## Quarterly rehearsal (30 minutes)

- [ ] `php artisan helpdesk:backup-verify --copy` on the server: "complete and usable".
- [ ] Take the newest file from the second copy to another computer.
- [ ] Decrypt it there with the standalone script and the key from the password manager (not from the server).
- [ ] Unpack it, open `manifest.json`, and check that the ticket count looks right.
- [ ] Optional: load the dump into a scratch PostgreSQL database and look at a few tickets.
- [ ] Write down the date and anything that was unclear.

## If the key is lost

Backups made with it cannot be opened, by anyone. Make a new key straight away (`php artisan helpdesk:backup-key`), put it in `.env`, run `php artisan helpdesk:backup`, and store the new key properly. The server's live data is not affected.

## Backups and the privacy promise

The privacy notice promises that personal details are removed after the retention period. Backups keep whatever was in the database when they were made, so removed details stay in older backups for up to `HELPDESK_BACKUP_RETENTION_DAYS` days (14) and in the second copy for as long as it keeps files. Keep the far side's retention short (about 30 days) so the promise still holds.
