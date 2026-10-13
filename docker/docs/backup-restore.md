# ONYX AIP — Backup & Restore Procedure

**Scope:** production on Proxmox (`vm-app` runs the containers, `vm-data` runs MySQL).
**Automated by:** a Proxmox backup job, not by the app.

---

## What gets backed up

| What | How | When | Kept |
|------|-----|------|------|
| `vm-data` (MySQL, all databases) | `vzdump` snapshot, guest-agent `fs-freeze` | daily 02:30 | 7 daily, 4 weekly, 3 monthly |
| `vm-app` (containers, `.env`, photos/attachments in `/srv/docker/onyx-aip/storage`, logs) | same job | daily 02:30 | same |

Backups are written to the NFS storage `nas-backup` (the office ReadyNAS), so they live off the
Proxmox host. They are **not** off-site: losing the office loses both servers and backups.

The app used to run its own `backup:application` command; it was removed (2026-10-13) because it
wrote inside the container, so every deploy deleted its backups.

---

## Taking a manual backup (before a risky change)

On the Proxmox host, run the same job on demand for the VM you are about to change:

```bash
vzdump <vmid> --storage nas-backup --mode snapshot --compress zstd
```

Routine deploys don't need this: the nightly job plus `RUN_MIGRATIONS_ON_BOOT` migrations that
are additive are enough. Do it before destructive migrations or data fixes.

---

## Restore procedure

> Restore to a **new VM ID first** and check it before touching production.

1. On the Proxmox host, list backups: `pvesm list nas-backup --content backup`.
2. Restore to an unused VM ID, unique storage, no network:
   `qmrestore <backup-volid> <new-vmid> --storage local-lvm --unique 1`, then
   `qm set <new-vmid> --net0 virtio,link_down=1` before starting it.
3. Start it and check the data. With the network down, MySQL on the copy will **not** start
   as is: `bind-address` includes `vm-data`'s LAN IP, which the copy cannot bind. On the copy
   only, add `/etc/mysql/mysql.conf.d/zz-restore-drill.cnf` with `[mysqld]` /
   `bind-address=127.0.0.1` / `mysqlx-bind-address=127.0.0.1` and restart MySQL. Row counts
   need the MySQL root password (keep it in the password manager); without it,
   `innochecksum` on the stopped server's `.ibd` files still proves the pages are intact.
   Then either:
   - **drill:** stop and destroy the copy (`qm stop` / `qm destroy`), or
   - **real restore:** stop the production VM, swap the restored VM in (same VM ID and network),
     start it and run the smoke test below.

### Smoke test after a real restore

- Open the app and confirm the dashboard and a store page load.
- `docker compose logs app` on `vm-app` has no PHP errors.
- A recent asset and service job are visible.

---

## Logs

Laravel logs are bind-mounted to `/srv/docker/onyx-aip/logs` on `vm-app` (rotated daily, 14 days
kept), so they survive deploys. The directory must be owned by uid 1000.

---

## Storage threshold alerts

`storage:check-thresholds` runs daily at 08:00 and emails `ALERT_EMAIL` when:

- Disk usage of the filesystem hosting `storage/app` exceeds `STORAGE_ALERT_THRESHOLD_PERCENT`
  (default 80%).
- Redis `used_memory` exceeds `REDIS_ALERT_THRESHOLD_PERCENT` (default 80%) of its `maxmemory`
  (ADR-002: `noeviction` means writes fail if `maxmemory` is hit — alert before that).

On a disk alert: log in, check `df -h`, then remove old exports or resize the VM disk.
On a Redis alert: check `docker compose exec redis redis-cli INFO memory | grep used_memory`, then
raise `maxmemory` in `docker/redis/redis.conf` and restart Redis.

---

## Restore drill

Run before each major release and record it here.

**Last drill:** 2026-10-13 — `vm-data` backup of 2026-10-13 02:30 restored to a temporary VM in
13 s (network disconnected). InnoDB started cleanly; `innochecksum` on all 37 `onyx_aip_prod`
tables: 0 failures; latest writes matched the evening before the backup. Row counts not checked
(MySQL root password not at hand). Temporary VM destroyed.
