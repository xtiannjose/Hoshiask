# Staying up and recovering

What happens when a part fails, how much data a failure can cost, how long getting back takes, and the four things that make the difference: an outside monitor, a rehearsed restore, a second person who can run the server, and (later, when it is needed) Redis. The restore steps themselves are in [backup-and-restore.md](backup-and-restore.md); the daily running is in [operations.md](operations.md).

## What breaks when one part fails

The app runs on one server. Data, sessions, cache and the queue all live in the one PostgreSQL database (`SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION` are all `database`).

| What fails | What people see | What is lost | Back to normal |
|---|---|---|---|
| The database | Every page errors; `/health` answers `503` | Nothing stored; nothing can be saved | Start the database. Nothing else to do |
| The web server or PHP | The site does not load | Nothing | Restart it |
| The queue worker | The site works; no email leaves (receipts, resolved notices, staff alerts) | Nothing: jobs wait in the `jobs` table | Start the worker. Waiting mail goes out |
| The scheduler (cron) | The site works; no reminders, no automatic closing, no nightly backup | Nothing, but the backup gap grows | Fix cron. Reminders catch up on the next run |
| The mail account | The site works; queued mail fails and lands in `failed_jobs` | Mail that failed all three tries | Fix the account, then `php artisan queue:retry all` |
| The disk is full | Uploads, logs and backups fail; if the database shares the disk, everything fails | Nothing already stored | Free space; check `storage/logs` and the backup folder first |
| The whole server | Everything is down | Everything since the newest backup that is **off** the server | Restore on a new server ([backup-and-restore.md](backup-and-restore.md)) |

## Recovery targets

These are the numbers to hold the setup to. The first is a fact of the design; the second is **not measured yet**, so it is a target until a rehearsal gives a real figure.

| Target | Value | Why |
|---|---|---|
| **Data loss** (RPO) | At most about 24 hours of tickets and replies | The backup runs nightly at 02:40. A server lost at 02:39 loses the whole day. Running `php artisan hoshiask:backup` by hand before a risky change shortens it |
| **Time to be back** (RTO) | 4 hours, to be confirmed by a rehearsal | A new server, the code, the `.env`, the database and the files. Most of it is waiting for downloads and the dump to load |

Both are only true if the **second copy** of the backup exists and the backup key is stored outside the server (see [backup-and-restore.md](backup-and-restore.md)). A backup that only lives on the server is lost with it.

### Restore log

Do the full restore on a spare machine or a throwaway server once, time every step, and write the result here. Repeat after a big change (a new PostgreSQL version, a new server) and at least once a year. The quarterly checklist in [backup-and-restore.md](backup-and-restore.md) keeps the backup readable; this log is the proof that the whole recovery works and how long it takes.

| Date | Who | Backup used | Minutes from "server gone" to "signed in and a ticket opens" | What was unclear or broken |
|---|---|---|---|---|
| _not done yet_ | | | | |

## Outside monitor on `/health`

The status check on the server cannot tell you the server is down. An outside monitor can. Use any uptime service (UptimeRobot, Better Stack, Pingdom, a second server running `curl`).

1. Monitor `GET https://<your domain>/health` every 1 to 5 minutes.
2. Alert on **HTTP 503**, a timeout, or a certificate problem. `503` means the database does not answer or PHP is too old.
3. Send the alert to two people (see the second maintainer below), by email and by something a phone shows at night.

What the answer means, and the catch:

- Without a token, `/health` does not look at the process list, so it cannot see whether a queue worker runs and says nothing about it: the public answer is `ok` while the database and PHP are fine. A stopped worker only shows with the token.
- With the token (`HOSHIASK_HEALTH_DETAIL_TOKEN`, sent as `Authorization: Bearer ...` or `X-Health-Token: ...`), `status` is `ok` only when the worker runs and nothing else needs fixing, and the answer lists the checks, warnings and advisories. A monitor that can send a header, or a small cron job on another machine, should alert when `status` is not `ok`:

  ```bash
  curl -fsS -H "X-Health-Token: $TOKEN" https://hoshiask.example.com/health | grep -q '"status":"ok"'
  ```

- Backups and reminders have no web address to watch. The detailed answer's `advisories` says when the newest backup is old, and `php artisan hoshiask:ops-status` says the same on the server.

## Redis (when it is worth it, and how)

Today sessions, cache and queued jobs share the database with the tickets. At the size of one office that is fine, and fewer parts means fewer things to fail. Move to Redis when the evidence says so, not before:

- the slow-query log (`OBSERVABILITY_SLOW_QUERY_ENABLED`) keeps showing statements on `sessions`, `cache` or `jobs`, or
- the oldest queued job waits more than a few minutes while the worker is running, or
- pages slow down at busy hours and the database CPU is the reason.

Do it one step at a time, cache first, and keep a day between steps:

1. **Cache.** Install Redis (listening on `127.0.0.1` only, with a password) and the PHP extension (`php-redis`, which is `phpredis`). In `.env`: `CACHE_STORE=redis`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, and a `CACHE_PREFIX` of your own. Then `php artisan config:clear` (or `optimize`). The caches start empty and refill; the login lockout counters reset once.
2. **Queue.** First let the database queue run empty: `php artisan queue:work --stop-when-empty`. Then set `QUEUE_CONNECTION=redis`, run `php artisan optimize` and `php artisan queue:restart`. Jobs left in the `jobs` table after the switch are never sent.
3. **Sessions** last, only if sessions are still the load: `SESSION_DRIVER=redis`. Everybody is signed out once.

To go back, set the value back to `database`. For the queue, drain the Redis queue first.

What to know before you do it:

- **Persistence.** Turn on `appendonly yes` in Redis. Mail that sits in a Redis queue that is lost is **not** sent again: the "ticket received" and "resolved" emails are marked as sent when they are queued. With the database queue the same mail survives in the database and its backups.
- **The status check still sees the queue.** With the database queue it reads the `jobs` table. With Redis it counts waiting jobs from Redis, and every queue worker writes a heartbeat (the time its last job finished) to the cache; `hoshiask:ops-status` then says "jobs are waiting and no job has finished for 15 minutes", and shows the time of the last finished job. Before any job has finished after the switch there is no heartbeat, so it stays quiet until one has. Failed jobs still land in `failed_jobs`. Make the service that runs the worker (systemd or Supervisor) restart it on failure (systemd: `Restart=always`) and let the outside monitor watch the detailed `/health`.
- Redis is one more thing to keep running and to patch. Add it to the server's monitoring and to the restore steps (an empty Redis is fine to restore: nothing needed is only in it, apart from queued mail).

## A second person who can run it

Today one person knows how everything is set up. If they are unavailable on the day it breaks, the recovery waits. The remedy is not a document; it is a second person who has done it once. Fill in the right-hand column (where each thing is kept, never the secret itself) and let the second person do the test below.

| Thing | Where it is kept |
|---|---|
| Server login (SSH key or password) | |
| Hosting or cloud account, and who pays for it | |
| The domain and its DNS | |
| The GitHub repository (admin or write access for two people) | |
| The production `.env` | |
| The backup key (`HOSHIASK_BACKUP_ENCRYPTION_KEY`) | |
| Where the second backup copy goes, and its login | |
| The SMTP account | |
| The uptime monitor and who gets its alerts | |
| The `shadow` account password | |

The test, once, with the second person at the keyboard and the first only answering questions:

- [ ] Sign in to the server and run `php artisan hoshiask:ops-status`.
- [ ] Put `main` on the server ([operations.md](operations.md)).
- [ ] Take a backup from the second copy and open it on another computer ([backup-and-restore.md](backup-and-restore.md)).
- [ ] Read the System Logs for the last day and say what happened.
- [ ] Add one line to the table above for anything they had to ask.

Both people should be on the monitor's alert list, and each should be able to reach the other's contact in the password manager.
