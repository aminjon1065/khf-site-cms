# Operations runbook

This runbook covers the Laravel CMS/API and the public Next.js application.
Commands assume the current release directory and the production environment.

## Release and rollback

Before switching traffic:

```bash
composer install --no-dev --prefer-dist --classmap-authoritative
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan ops:production-check
php artisan ops:backup
```

Deploy into a new timestamped release directory, then atomically point the
`current` symlink at it. After the switch:

```bash
php artisan schedule:interrupt
php artisan queue:restart
curl --fail --silent https://cms.khf.tj/api/v1/health
curl --fail --silent https://cms.khf.tj/api/v1/ready
```

Rollback application code by repointing `current` to the exact previous release,
then run `php artisan optimize`, `php artisan schedule:interrupt`,
`php artisan queue:restart` and both health checks. Do not automatically roll
back a database migration: migrations must remain backward-compatible for at
least one release. If a data rollback is genuinely required, stop writes, take
a fresh backup, have the incident commander approve the exact restore target,
and use `php artisan ops:restore-drill <backup>` before restoring production.

## Queue or scheduler is unhealthy

Symptoms: `/api/v1/ready` is `503`, `scheduler` or `queue_worker` is false,
`queue_failed_jobs` grows, or queue p95/errors rise in **Центр контроля**.

1. Keep `/health` serving; readiness should remove the instance from new work.
2. Run `php artisan schedule:list` and check cron invokes `schedule:run` every
   minute.
3. Check Supervisor/systemd for all `critical,notifications,revalidation,default`
   workers and the separate `media` worker.
4. Run `php artisan queue:monitor` with the configured queues and inspect
   `php artisan queue:failed`.
5. Fix the dependency or worker, then run `php artisan queue:restart`.
6. Retry only identified idempotent jobs with `php artisan queue:retry <uuid>`.
   Never use `queue:flush` during an incident.
7. Confirm `/ready` is 200 and the queue telemetry timestamp advances.

## Redis unavailable

Expected behavior is Redis → database failover for cache and queues. Content
writes and publication remain durable, although latency may increase.

1. Confirm MySQL and database queue workers are healthy.
2. Check structured events `cache_failed_over` and `queue_failed_over`.
3. Repair Redis without clearing the database queue.
4. Restart Redis workers, then `php artisan queue:restart`.
5. Verify a content publish reaches the public API and the revalidation queue
   drains. Cache versions make stale read models self-invalidating.

## Frontend webhook unavailable

Publication is not rolled back when Next.js revalidation is unavailable.
`RevalidateFrontend` retries independently and ISR remains the fallback.

1. Check the `revalidation` queue and `queue_job_failed` events.
2. Verify `FRONTEND_REVALIDATION_URL` and the shared secret without printing the
   secret into logs.
3. Confirm the Next readiness/build preflight separately.
4. Retry the failed job after the endpoint recovers.
5. Open one changed public detail page and verify its updated content.

## Media conversion failure

The original is immutable and must never be deleted as a recovery shortcut.

```bash
php artisan media:audit
php artisan media:audit --regenerate
```

Inspect the media status/error in CMS, repair `avifenc`/storage/worker capacity,
then use the authorized retry action or `--regenerate`. Confirm all expected
AVIF/WebP/fallback and CMS thumbnails exist and rerun `media:audit`. The weekly
`media:cleanup-orphans --delete --grace-hours=24` removes only unreferenced
derivatives; originals are excluded by construction.

The full lifecycle now runs on its own, and each step alerts through the
scheduled-task listener when it fails:

| Когда | Команда | Что делает |
|---|---|---|
| ежедневно 04:45 | `media:audit --regenerate` | чинит недостающие производные; падает, если чинить нечем (нет оригинала) |
| понедельник 04:15 | `media:cleanup-orphans --delete --grace-hours=24` | удаляет производные без записи в БД; оригиналы не трогает |
| понедельник 05:15 | `media:purge-trashed --delete` | окончательно удаляет ассеты, пролежавшие в корзине дольше `MEDIA_TRASH_GRACE_DAYS` (30) |

`media:purge-trashed` без `--delete` только отчитывается и **повторно проверяет
использование**: ссылка на файл могла появиться уже после удаления — например,
редактор восстановил старую ревизию материала.

## Backup and restore

Set `BACKUP_ENABLED=true` and a restricted `BACKUP_PATH` outside the release
directory. Daily backups contain a consistent database snapshot plus every
media original, a manifest and SHA-256 for each file. Derivatives are
regenerable and intentionally excluded.

```bash
php artisan ops:backup
php artisan ops:restore-drill
```

The weekly drill restores the latest backup into an isolated SQLite file or a
randomly named temporary MySQL database, verifies table integrity and all
checksums, and then removes the temporary database. Alert if either scheduled
command fails. Copy backups to a separate account/region and apply the approved
retention policy there; a backup on the application host alone is not a backup.

Locally the command keeps the last `BACKUP_KEEP` (default 7) backups and prunes
older ones **after** a new backup is complete — a failed run never removes the
previous good copy. Without this the daily copy of every media original filled
the disk, and the first thing to fail was the next backup.

`mysqldump`/`mysql` on many hosts are symlinks to the MariaDB client, which
cannot authenticate against MySQL 8 (`caching_sha2_password`). The failure then
looks like a permissions problem in a 02:15 cron log, so `ops:backup` detects
the MariaDB client and says so explicitly; point `MYSQLDUMP_BINARY` and
`MYSQL_BINARY` at the real MySQL client.

## Dashboards and alert thresholds

- Edge/Nginx owns full access logs.
- Application records structured errors, slow requests and a sample, keyed by
  route name rather than slug. Alert on API p95 above 750 ms, any sustained 5xx,
  stale scheduler/worker heartbeat, failed jobs, queue depth above
  `QUEUE_MONITOR_MAX`, or `scheduled_task_failed` / repeated
  `scheduled_task_skipped` — a nightly backup that fails does not move the
  scheduler heartbeat, so `/ready` alone would keep answering "ready".
- **Центр контроля** shows API p95/errors, queue p95/failures and exact p75
  LCP/INP/CLS by route/device. Logs contain no request body, query bindings,
  search terms, IP, user agent or session identifiers.
- `/health` is liveness (DB); `/ready` additionally covers storage, scheduler,
  queue worker and failed-job count. Neither endpoint exposes secrets.

## Controlled chaos checklist

Run on staging after every infrastructure change:

1. Stop Redis: a cache write and queued job must land in database fallback.
2. Return 401/500/timeout from the frontend webhook: publication remains saved,
   and the unique revalidation job retries/fails visibly.
3. Disable `avifenc`: draft save succeeds, original SHA-256 is unchanged,
   conversion state becomes failed and retry works after recovery.
4. Stop the queue worker and scheduler: `/ready` becomes 503 while `/health`
   remains 200; after restart both heartbeats recover.
5. Corrupt a copied backup file: `ops:restore-drill` must fail its checksum
   before touching a restore target.
6. Place an unreferenced file under `conversions/`: dry-run reports it, delete
   mode removes it, and the neighboring original remains byte-identical.

Record timestamps, release SHA, observed recovery time and evidence links in
the incident/change ticket. Never run destructive chaos against production.
