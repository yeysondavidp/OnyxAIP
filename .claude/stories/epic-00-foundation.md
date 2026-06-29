# EPIC-00 — Foundation & Infrastructure

> Everything else stands on this. No tenant-facing features here — the goal is that every later
> story can be built **secure, clean, accessible and audited by default**.
>
> Each story carries an **Engineering Bar checklist** — the four non-negotiables from `/CLAUDE.md`
> turned concrete for this story. Tick every box before the story is Done. The global Definition
> of Done in `../README.md` also applies.

Related: ADR-002 (deployment). Sprint: 0.

---

## US-00.1 — Laravel 11 project scaffold + base configuration

**As** the ONYX engineering team
**I want** a clean Laravel 11 / PHP 8.3 project with sane base configuration
**So that** all feature work has a consistent, correctly-configured starting point.

**Estimate:** 3 · **Priority:** P0 · **Depends on:** none · **Status:** ✅ Done

### Acceptance criteria
- **Given** a fresh checkout, **when** dependencies are installed, **then** Laravel 11 boots with
  PHP 8.3 and `php artisan --version` reports 11.x.
- **Given** the app, **when** I inspect config, **then** **all datetimes are stored in UTC**
  (`app.timezone = UTC`) and a documented helper converts to a job/store IANA timezone for display
  (SRA §2.3, §3.2).
- **Given** the app, **when** I check the DB driver, **then** it matches the ADR-002 choice
  (MySQL 8 or PostgreSQL 16) and connects inside Docker.
- **Given** `.env.example`, **when** a new dev copies it, **then** every required key is present
  and documented (no secret values committed).
- **Given** Livewire and Alpine, **when** the app builds, **then** both are installed and a smoke
  page renders a Livewire component and an Alpine directive.

### Engineering Bar checklist
- **Secure:** `APP_DEBUG=false` in prod config; `.env` git-ignored; `$guarded = []` is **banned**
  project-wide (document the rule); HTTPS-only cookies + `SESSION_SECURE_COOKIE` in prod.
- **Clean:** default Laravel structure preserved; no speculative packages; `composer.json` pinned.
- **UX:** N/A (no user surface) — but the smoke page proves Livewire+Alpine render.
- **No guessing:** per ADR-002 — connect to the **existing** MySQL container with a dedicated
  DB/user (don't spin up a new database); Redis is a **new project-owned, shareable** container.

### Definition of Done
Boots locally and in Docker; UTC + tz-display helper documented; `.env.example` complete; smoke
page renders; README "getting started" section written.

---

## US-00.2 — Docker Compose environment (dev + prod parity)

**As** the engineering team
**I want** the app fully containerised per ADR-002
**So that** it runs identically on a dev machine and the target small server.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-00.1 · **Status:** ✅ Done

### Acceptance criteria
- **Given** the repo, **when** I run `docker compose up`, **then** nginx, app (php-fpm), db, queue
  worker and scheduler all start and the app is reachable.
- **Given** the build, **when** the image is produced, **then** it is **multi-stage** — a Node
  stage compiles Vite assets (Livewire + Alpine technician bundle) and the runtime image contains
  **no Node/build tooling** (ADR-002).
- **Given** the running stack, **when** containers are recreated, **then** the `app_storage` and
  `redis_data` volumes persist (photos and queued jobs survive); the DB data volume is owned by
  the shared MySQL container (ADR-002).
- **Given** the containers, **when** I inspect the process user, **then** the app runs **as
  non-root** and only `storage/` + `bootstrap/cache` are app-writable.
- **Given** prod config, **when** files are requested, **then** `storage/app` is **not** publicly
  served (only via signed URLs later).
- **Given** the infra, **when** the app starts, **then** it connects to the **existing shared
  MySQL container** using a **dedicated database + least-privilege user** (this compose does not
  define a `db` service), and to the **new project-owned Redis** service on a **dedicated logical
  DB index + key prefix** with **AOF on + `maxmemory-policy noeviction`** (ADR-002).
- **Given** the new Redis, **when** defined, **then** it sits on the **shared Docker network** so
  other projects can adopt it, and persists to the `redis_data` volume.

### Engineering Bar checklist
- **Secure:** non-root containers; no public file serving; secrets via env, never baked into the
  image; minimal runtime image (reduced attack surface, §14.3); MySQL user has least-privilege
  grants scoped to the ONYX schema only; Redis `noeviction` so queued jobs are never dropped.
- **Clean:** one image reused by app/queue/scheduler; compose is the single source of topology;
  shared MySQL reused; one Redis service, namespaced, not duplicated per concern.
- **UX:** N/A.
- **No guessing:** topology per ADR-002; reuse the existing shared MySQL over the shared network
  (dedicated DB/user, no new `db` service); **define** the new shared Redis service with AOF +
  `noeviction` and a namespaced DB index + prefix.

### Definition of Done
`docker compose up` works on dev and on the target server; volumes persist across recreation;
non-root verified; deployment steps documented.

---

## US-00.3 — Base layout, design system tokens & accessibility baseline

**As** every future user (Yeis, Michael, Sneider)
**I want** a consistent, accessible base layout for both the PM desktop shell and the technician
mobile shell
**So that** every screen inherits clear hierarchy, accessible defaults and Australian-English UI.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-00.1 · **Status:** ✅ Done

### Acceptance criteria
- **Given** a PM page, **when** rendered on desktop (1280px+), **then** it uses the PM shell
  (nav + content) with a clear primary-action region.
- **Given** a technician page, **when** rendered at **320px**, **then** it uses the mobile shell,
  fully functional, with **≥44×44px** tap targets (§14.2, §14.7).
- **Given** any interactive control, **when** navigated by keyboard, **then** focus order is
  logical and focus is visible (WCAG 2.1 AA, §14.7).
- **Given** reusable states, **when** a page has no data / is loading / errors, **then** shared
  **empty / loading / error** components exist and are used (Engineering Bar #3).
- **Given** UI copy, **when** displayed, **then** it is **Australian English**; helper for
  inline field errors (`@error`) is established.
- **Given** design tokens, **when** used, **then** colour/spacing/typography come from a single
  token source (no scattered magic values).

### Engineering Bar checklist
- **Secure:** layout includes CSRF meta; no inline event handlers that bypass CSP later.
- **Clean:** one token source; shared partials for nav/empty/loading/error; no duplicated markup.
- **UX:** clear hierarchy + obvious primary action; inline `@error` pattern; 44px targets;
  keyboard/focus order; designed empty/loading/error states — **all four are acceptance criteria
  here so later stories inherit them.**
- **No guessing:** verify the AA contrast of tokens before committing them.

### Definition of Done
Both shells render at their target widths; shared state components exist; tokens centralised;
keyboard/focus and contrast checked; copy is en-AU.

---

## US-00.4 — Multi-client scoping foundation

**As** the engineering team (protecting Yeis's tenants and future Rosie)
**I want** a reusable `client_id` scoping mechanism baked into the data layer
**So that** **no query can leak across tenants by accident** (Engineering Bar #1; SRA §2.1, §17).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-00.1 · **Status:** ✅ Done

### Acceptance criteria
- **Given** a tenant-scoped model, **when** it uses the scoping trait, **then** a **global scope**
  automatically constrains reads to the actor's permitted `client_id`(s).
- **Given** a `client_user` (future Rosie) context, **when** they query any scoped model, **then**
  rows of other clients are **never** returned — proven by a failing-without-scope test.
- **Given** a migration for a tenant-scoped table, **when** authored, **then** `client_id` is
  **NOT NULL** with a **foreign key** and an **index** (§14.4) — a documented migration recipe
  exists.
- **Given** a PM (multi-client), **when** they query, **then** scoping allows all their clients
  but still blocks anything outside ONYX's data.
- **Given** a write, **when** a scoped model is created, **then** `client_id` is set from the
  authorised context, never trusted from request input.

### Engineering Bar checklist
- **Secure (the core of this story):** scope enforced at the **model (global scope)** AND the
  **DB (NN + FK + index)** — *not* only in controllers. This story exists specifically to prevent
  ONYX's recurring "guard in controller but not DB" audit failure.
- **Clean:** one trait + one migration recipe reused everywhere; no per-model copy-paste.
- **UX:** N/A.
- **No guessing:** verify the trait behaviour with tinker + tests before declaring done.

### Definition of Done
Trait + migration recipe documented; tests prove cross-tenant reads are blocked for `client_user`
and writes set `client_id` from context; DB constraints present.

---

## US-00.5 — Audit trail foundation (append-only, async)

**As** Yeis (and any future auditor)
**I want** a single append-only, async audit log capturing every significant action
**So that** an audit finds a complete, tamper-resistant history (SRA §14.5).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-00.1, US-00.4 · **Status:** ✅ Done

### Acceptance criteria
- **Given** a significant action, **when** it occurs, **then** an audit entry is written
  **asynchronously (queued)** with: actor (user id + role), action type, target (model + id),
  before/after values, IP, user agent, UTC timestamp.
- **Given** the audit log, **when** any code tries to update or delete an entry, **then** it is
  **prevented at the application level** (append-only) — covered by a test.
- **Given** a reusable hook, **when** a model opts into auditing, **then** create/update/status-
  change/delete events are captured **without per-model boilerplate**.
- **Given** queue downtime, **when** an audit job can't run, **then** it is retried, not lost.

### Engineering Bar checklist
- **Secure:** append-only enforced in model (no `update`/`delete`); audit reads restricted to PM
  later; no sensitive secrets stored in before/after diffs.
- **Clean:** one auditing concern reused by all models; bulk-safe.
- **UX:** N/A (viewer is US-17.1).
- **No guessing:** queue backend is the project-owned Redis (AOF + `noeviction`) per ADR-002 —
  safe for queued jobs; verify the queue connection uses the dedicated logical DB index.

### Definition of Done
Async audit writer + append-only guarantee tested; reusable auditing hook; retry on failure;
documented list of "significant actions" mapped to §14.5.

---

## US-00.6 — Quality gates: Pint, Larastan, Pest, CI

**As** the engineering team
**I want** automated style, static analysis and test gates
**So that** the Engineering Bar's "Clean" + tested requirements are enforced every change, not
by memory.

**Estimate:** 3 · **Priority:** P0 · **Depends on:** US-00.1 · **Status:** ✅ Done

### Acceptance criteria
- **Given** the repo, **when** `./vendor/bin/pint` runs, **then** style is enforced (the project's
  analogue of `brew style`).
- **Given** the repo, **when** Larastan runs, **then** static analysis passes at the agreed level
  (the analogue of `typecheck`).
- **Given** the repo, **when** Pest runs, **then** the test suite (feature + unit) executes; the
  convention "≤1 integration test per command, happy-path, fast" is documented.
- **Given** a push/PR, **when** CI runs, **then** Pint + Larastan + Pest must all pass to merge.

### Engineering Bar checklist
- **Secure:** CI does not echo secrets; dependency audit step included.
- **Clean:** these gates *are* the Clean enforcement; config committed.
- **UX:** N/A.
- **No guessing:** Larastan level chosen deliberately and documented.

### Definition of Done
All three gates run locally and in CI and block merge on failure; testing conventions documented
in `.claude` or `CONTRIBUTING`.

---

## US-00.7 — Backup & storage-volume alerting

**As** Yeis / ONYX ops
**I want** scheduled backups and an alert before the photo volume fills
**So that** a single-server v1 (ADR-002) doesn't lose data or silently run out of disk (§16 Q6).

**Estimate:** 3 · **Priority:** P1 · **Depends on:** US-00.2 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the scheduler, **when** the backup job runs, **then** it produces a DB dump **and** an
  `app_storage` snapshot to a configured destination, on a documented cadence.
- **Given** disk usage, **when** the `app_storage` volume crosses a configurable threshold,
  **then** an alert notification is sent (ties to §12 notification infra when available).
- **Given** the project-owned Redis runs `noeviction`, **when** its memory approaches `maxmemory`,
  **then** an alert is sent so the instance is resized before writes start erroring (ADR-002).
- **Given** a restore drill, **when** documented steps are followed, **then** DB + photos restore
  cleanly — restore procedure is written, not just backup.

### Engineering Bar checklist
- **Secure:** backups stored with restricted access; no public exposure; secrets not in dumps.
- **Clean:** uses Storage abstraction + scheduler; config-driven thresholds.
- **UX:** alert message is plain-language and actionable.
- **No guessing:** storage budget + thresholds **agreed with ONYX** (§16 Q6) before finalising.

### Definition of Done
Backup + snapshot scheduled; threshold alert works; restore procedure documented and drilled.
