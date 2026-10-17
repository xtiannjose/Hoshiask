# Backups are one encrypted tar file with their own key

A backup is a single file holding the database dump, every uploaded file and a manifest of row counts and SHA-256 hashes. It is encrypted with AES-256-GCM through `openssl_encrypt` in authenticated 1 MiB records, under `HOSHIASK_BACKUP_ENCRYPTION_KEY`, which is separate from `APP_KEY` so a lost or rotated app key never makes backups unreadable.

## Considered Options

- **Laravel's encrypter**: rejected. It base64-encodes the whole payload twice, making each backup about 1.8x larger and loading it into memory.
- **`PharData` for the archive**: rejected. It rebuilds the archive as entries are added. A small streaming ustar writer/reader is used instead and is checked against system `tar` in tests.

## Consequences

Any change to the record framing or archive layout must keep `hoshiask:backup-verify`, `hoshiask:backup-restore` and `scripts/hoshiask-backup-decrypt.php` able to open older backups.
