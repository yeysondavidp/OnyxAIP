# ADR-002 — Deployment: Docker Compose on a single small server

- **Status:** Accepted
- **Date:** 2026-06-24
- **Deciders:** ONYX Visual (engineering)
- **Context source:** SRA §2.3, §14.1, §14.3, §14.4, §17 (assumptions), §16 Q6 (storage)

## Context

The app will run in **Docker on a single small server** (think 2 vCPU / 2–4 GB RAM class).
Lean memory footprint is a **first-class constraint** — every service must justify its RAM.
Requirements that shape the topology:

- Queued email and async audit logging (§12, §14.5) → a queue + a worker.
- Photo storage on local disk, cloud-swappable later via Laravel Storage (§14.4, §17) →
  **persistent volume** for `storage/app`.
- DB indexed and durable (§14.4) → **persistent volume** for DB data.
- Scheduled work: SLA clock, warranty/reminder notifications (§10, §12) → a scheduler.

## Decision

Run with **Docker Compose**. Baseline services:

| Service | Image (baseline) | Role | Notes |
|---------|------------------|------|-------|
| `nginx` | nginx:alpine | TLS termination + static + reverse proxy to php-fpm | |
| `app` | php:8.3-fpm-alpine (custom) | Laravel (PM Livewire + technician endpoints) | |
| `queue` | same image as `app` | `php artisan queue:work` | email + async audit |
| `scheduler`| same image as `app` | `php artisan schedule:work` | SLA, reminders, warranty |
| `db` | **existing shared MySQL 8 container** | primary datastore | reused (not defined by this compose); provision a dedicated DB + user — see "Shared infrastructure" |
| `redis` | **new `redis:alpine`, owned by this project** | queue + cache + sessions | defined by this compose on a shared network so other projects can adopt it; persistent (AOF) + non-evicting — see "Shared infrastructure" |

**Persistent volumes (must survive container recreation):**
- `app_storage` → `storage/app` (photos, generated PDFs/QR labels). **Owned by this project.**
- `redis_data` → Redis AOF persistence so queued jobs + sessions survive a restart. **Owned by
  this project.**
- The database data volume is owned by the **existing shared MySQL container**, not this compose.

**Build the app image as multi-stage**: a Node stage compiles assets (Vite → Blade/Livewire +
the Alpine technician bundle), output copied into the slim PHP-FPM runtime. No Node in the
runtime image — keeps it small and reduces attack surface (§14.3).

## Resolved — shared infrastructure (MySQL reused + Redis newly created)

(Decisions: ONYX, 2026-06-24.) The target server already runs a **shared MySQL** container that
ONYX AIP **reuses**, and ONYX AIP **stands up a new, shareable Redis** container that it owns.

**MySQL (existing container — reused).** Provision a **dedicated database and a dedicated DB user**
for ONYX AIP with **least-privilege grants** scoped to that schema only. The app connects to the
shared container over a shared Docker network; this compose does **not** define a `db` service and
does **not** own the DB data volume.

**Redis (new container — owned by ONYX AIP, shareable).** We define a fresh `redis:alpine` service
in this compose and expose it on a **shared Docker network** so other projects can adopt it later —
but ONYX AIP owns and configures it. Redis is light (idle a few MB; tens of MB under this load), so
the small server absorbs it easily. Because we control the config, the earlier eviction risk is
**designed out**:
- **Durability:** `appendonly yes` (AOF) + the `redis_data` volume so **queued jobs and sessions
  survive a restart**.
- **Eviction = none:** a modest `maxmemory` cap with **`maxmemory-policy noeviction`**, so
  queue/session keys are **never silently dropped** — a full instance errors loudly (caught +
  alerted) instead of losing work.
- **Namespacing (it's shared):** ONYX AIP uses a **dedicated logical DB index** + a unique key
  prefix (`REDIS_PREFIX=onyx_aip_`), with **separate logical DBs for queue vs cache** so a
  `cache:clear` (FLUSHDB) never touches queued jobs. Other adopters pick different indices/prefixes.
- **Memory alerting:** alert as Redis approaches `maxmemory` (ties to US-00.7) so `noeviction`
  never surprises us.

**Consequence:** Horizon is available; the queue runs safely on Redis. Horizon's dashboard is
optional — enable it only if its overhead is justified; otherwise the plain `queue:work` worker
suffices.

## Consequences

- `.env` driven config means the cloud storage swap (§14.4) and the Redis upgrade are
  config-only changes, no code change.
- Single host = single point of failure; acceptable for v1 per scope. Backups (DB dump +
  `app_storage` snapshot) must be a documented, scheduled job — tracked as a Foundation story.
- Storage growth from original-quality photos (§16 Q6) needs an alert threshold on the
  `app_storage` volume — tracked as a story; budget to be agreed with ONYX.

## Guardrails for implementers

- Never write uploads anywhere but the Storage abstraction → `app_storage` volume.
- Containers run as non-root; `storage/` and `bootstrap/cache` writable by the app user only.
- Files are **not** publicly served — only via signed URLs with expiry (§14.3).
- Keep the runtime image free of build tooling (Node, compilers).

## Related

- ADR-001 — Alpine bundle is part of the Vite build in the Node stage.
- Stories: EPIC-00 (Foundation & Infrastructure).
