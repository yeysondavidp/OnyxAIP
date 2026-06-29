# ONYX AIP — Backup & Restore Procedure

**Scope:** v1 single-server Docker Compose deployment (ADR-002).
**Automated by:** `backup:application` Artisan command, scheduled daily at 02:00 via the
`scheduler` container.

---

## What gets backed up

| Artefact | File in backup dir | Command used |
|----------|--------------------|--------------|
| MySQL database | `db_YYYYMMDD_HHmmss.sql.gz` | `mysqldump \| gzip` |
| App storage (photos, QR labels, generated PDFs) | `storage_YYYYMMDD_HHmmss.tar.gz` | `tar -czf` |

Backups land in `BACKUP_PATH/{YYYY-MM-DD}/`. Daily directories older than `BACKUP_RETAIN_DAYS`
(default: 7) are pruned automatically.

---

## Backup location

`BACKUP_PATH` defaults to `storage/backups` inside the container. **For production**, bind-mount
a separate host path so backups survive a container rebuild and can be copied off-server:

```yaml
# docker-compose.yml (app + scheduler services)
volumes:
  - /data/backups/onyx-aip:/backups
```

```env
# .env
BACKUP_PATH=/backups
```

---

## Running a manual backup

```bash
docker compose exec app php artisan backup:application
```

Verify the output shows both `DB →` and `Storage →` lines and exits 0.

---

## Restore procedure

> **Always test restores on a staging/clone environment first. Never restore directly to
> production without confirming the backup is clean.**

### 1 — Identify the backup to restore

```bash
ls /data/backups/onyx-aip/          # list daily dirs
ls /data/backups/onyx-aip/2026-06-29/  # inspect a specific day
```

### 2 — Stop the app (prevent writes during restore)

```bash
docker compose stop app queue scheduler
```

### 3 — Restore the database

```bash
# Copy the dump out of the container / mount (adjust path as needed)
DUMP=/data/backups/onyx-aip/2026-06-29/db_20260629_020000.sql.gz

# Decompress and pipe into MySQL
# Replace DB_HOST, DB_USER, DB_NAME, DB_PASS with values from .env
gunzip -c "$DUMP" | docker exec -i <mysql_container> \
    mysql -h DB_HOST -u DB_USER -pDB_PASS DB_NAME
```

Verify row counts match expectations:

```bash
docker exec -i <mysql_container> mysql -u DB_USER -pDB_PASS DB_NAME \
    -e "SELECT COUNT(*) FROM audit_logs;"
```

### 4 — Restore app storage

```bash
SNAP=/data/backups/onyx-aip/2026-06-29/storage_20260629_020000.tar.gz

# Clear existing storage/app (dangerous — confirm first)
docker compose exec app rm -rf storage/app/*

# Extract the snapshot
docker run --rm \
    -v onyx-aip_app_storage:/var/www/html/storage/app \
    -v /data/backups/onyx-aip:/backup:ro \
    php:8.3-fpm-alpine \
    tar -xzf /backup/2026-06-29/storage_20260629_020000.tar.gz \
        -C /var/www/html/storage --strip-components=1
```

### 5 — Restart the stack

```bash
docker compose start app queue scheduler
```

### 6 — Smoke test

- Open the app in a browser and confirm the dashboard loads.
- Check `docker compose logs app` for PHP errors.
- Verify a recent asset record and a service job are visible.
- Confirm `storage/app` contains the expected subdirectories (`photos/`, etc.).

---

## Storage threshold alerts

`storage:check-thresholds` runs daily at 08:00 and sends an email to `ALERT_EMAIL` when:

- Disk usage of the filesystem hosting `storage/app` exceeds `STORAGE_ALERT_THRESHOLD_PERCENT`
  (default 80%).
- Redis `used_memory` exceeds `REDIS_ALERT_THRESHOLD_PERCENT` (default 80%) of its `maxmemory`
  limit (ADR-002: `noeviction` policy means writes fail if maxmemory is hit — alert before that).

When you receive a disk alert:
1. Log into the server.
2. Run `df -h` to confirm remaining space.
3. Either remove old backup dirs, compress large logs, or resize the Docker volume.

When you receive a Redis alert:
1. Check current usage: `docker compose exec redis redis-cli INFO memory | grep used_memory`.
2. Increase `maxmemory` in `docker/redis/redis.conf` and restart the Redis container.

---

## Restore drill checklist (run before each major release)

- [ ] Locate latest backup directory and confirm both `.sql.gz` and `.tar.gz` exist.
- [ ] Restore DB to a test MySQL instance and verify record counts.
- [ ] Restore storage snapshot to a temp directory and spot-check file contents.
- [ ] Record the date of last successful drill in this file.

**Last drill:** _(not yet performed — schedule before first production deploy)_
