# EPIC-06 — Asset Status Lifecycle

> The status truth for every asset. One enforced state machine (SRA §4.5) and the job-driven
> auto-transitions (SRA §5.2), routed through a **single transition service** so model, jobs and
> a future automated-monitoring actor (§16 Q4) all change status the same audited way.

Related: ADR-001 (frontend), ADR-002 (deployment), US-00.5 (audit), US-01.3 (role + `client_id`
policies), EPIC-04 (assets), EPIC-08 (job create), EPIC-11 (job validation). Sprint: 4.

---

## US-06.1 — Enforce the asset status state machine at model + DB level

**As** Yeis (PM)
**I want** asset status to move only along the permitted transitions, rejected anywhere else
**So that** the registry can never record an impossible state and the lifecycle stays trustworthy.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-00.4, US-01.3, EPIC-04 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the permitted map (SRA §4.5: `Active→Faulty`; `Faulty→Under Maintenance`;
  `Under Maintenance→Active`; `Under Maintenance→Decommissioned`; `Active→Offline`;
  `Offline→Active`; and `Active`/`Faulty`/`Offline`→`Decommissioned`), **when** a status change is
  requested, **then** only those transitions succeed.
- **Given** an illegal transition (e.g. `Decommissioned→Active`), **when** attempted, **then** it is
  **rejected by the model**, not merely by a controller — proven by a unit test calling the model
  directly.
- **Given** the asset table, **when** the migration is authored, **then** `asset_status` is an
  **enum-backed, NOT NULL** column whose allowed values match the typed PHP enum exactly (DB and app
  agree — no drift).
- **Given** all status changes in the codebase, **when** any caller needs to move an asset, **then**
  they go through **one** `transitionTo()` method/service (single entry point), and direct writes to
  the status column outside it are not used.
- **Given** the transition service, **when** designed, **then** it accepts an **actor** that may be a
  user **or** a non-user system actor, so a future automated network-monitoring input (§16 Q4) can
  call the identical API without reshaping it (out of v1 scope, but the seam exists).

### Engineering Bar checklist
- **Secure:** transition rules enforced in the **model/service** AND the column is enum-backed +
  NOT NULL at the **DB** — defence in depth, not "guard in controller but not DB". `$fillable` only;
  status is **never** mass-assignable from request input — it can only move via `transitionTo()`.
  Authorise the change against the actor's role **and** the asset's `client_id` scope (US-01.3).
- **Clean:** one enum + one transition service reused everywhere (jobs in US-06.3 call it too); the
  permitted-transition map lives in exactly one place; no per-call duplication. YAGNI — build the
  system-actor seam as an interface, not a speculative monitoring integration.
- **UX:** N/A directly (rejection surfaces are owned by the calling UI in EPIC-04/EPIC-10); the
  service returns a clear, catchable failure rather than a silent no-op.
- **No guessing:** verify the asset table's status column name and the enum values against the
  migration/model with tinker before wiring the map — do not assume labels.

### Definition of Done
Typed status enum + DB enum/NOT NULL column aligned; single `transitionTo()` entry point; permitted
map matches §4.5; illegal transitions rejected at the model (unit-tested red/green); system-actor
seam present; Pint + Larastan clean.

---

## US-06.2 — Audited status change with actor, UTC timestamp & reason

**As** Yeis (PM)
**I want** every status change to record who changed it, when, and an optional reason
**So that** an audit (and the asset's own history) always shows a complete, attributable trail.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-00.5, US-06.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a permitted transition via `transitionTo()`, **when** it commits, **then** an
  **append-only audit entry** is written **asynchronously** (US-00.5) capturing actor (user id +
  role, or the system actor identity), action type (status change), target (asset model + id),
  before/after status, IP, user agent and **UTC timestamp** — status transitions are a key audited
  event per §4.5/§14.5.
- **Given** the same transition, **when** it commits, **then** an **asset history** record is also
  written (the asset-detail chronological log) with before status, after status, actor, UTC
  timestamp and the optional reason note.
- **Given** a reason note is supplied, **when** the transition runs, **then** the reason is captured
  and validated (length-bounded, sanitised); **given** none is supplied, **then** who/when/before/
  after are **still always recorded** (reason optional, attribution never).
- **Given** the audit/history records, **when** any code attempts to update or delete one, **then**
  it is **prevented at the application level** (append-only) — covered by a test.
- **Given** a queued audit write fails, **when** the worker retries, **then** the entry is not lost
  (US-00.5 retry behaviour), and the in-band history write is inside the transition transaction.

### Engineering Bar checklist
- **Secure:** attribution (actor + UTC) is mandatory and server-set — never trusted from the client;
  reason note validated and bounded; audit + history append-only at the model (no update/delete); no
  secrets in before/after diffs. Authorised by role **and** `client_id` scope before the write.
- **Clean:** reuse the US-00.5 auditing concern — no bespoke audit code here; history write lives in
  the one transition service from US-06.1, not scattered across callers. Comments in English; any UI
  reason copy in Australian English.
- **UX:** where a PM enters a reason (EPIC-04 surfaces), inline `@error` validation and a
  plain-language, non-blaming message; the field is clearly optional. (Surface owned by callers; this
  story guarantees the data contract.)
- **No guessing:** confirm the audit payload shape and the asset-history table columns/relation by
  reading US-00.5's hook and the migration before writing — do not assume field names.

### Definition of Done
Every transition writes one async audit entry + one asset-history record with actor + UTC + optional
reason; attribution always present; append-only proven by test; reason validated; reuses US-00.5;
Pint + Larastan clean.

---

## US-06.3 — Job-driven automatic status transitions

**As** Yeis (PM)
**I want** asset status to follow the job lifecycle automatically
**So that** creating a service job and validating it keeps asset status correct without manual steps
(SRA §5.2).

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-06.1, US-06.2, EPIC-08, EPIC-11 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a service job is created referencing one or more assets (EPIC-08), **when** it is
  created, **then** each affected asset currently `Active` **or** `Faulty` is moved to
  `Under Maintenance` automatically (§5.2) via the **same** `transitionTo()` service from US-06.1 —
  not a parallel code path.
- **Given** an affected asset is in a state the §4.5 map does not permit moving to
  `Under Maintenance` from (e.g. `Decommissioned`), **when** the job is created, **then** the
  auto-transition is **skipped for that asset** (the state machine still governs) and the skip is
  recorded, not forced.
- **Given** a job is validated (EPIC-11), **when** validation commits, **then** each affected asset
  returns to `Active` **unless the PM overrides** the post-service outcome (e.g. `Still Faulty`,
  `Decommissioned`, `Replaced` per §6 Screen 4 / §7) — the override wins and is honoured.
- **Given** any auto-transition (create or validate), **when** it runs, **then** it produces the same
  audit + asset-history records as US-06.2, attributed to the acting PM (or the system context that
  triggered it) with a UTC timestamp.
- **Given** EPIC-08 (job create) and EPIC-11 (validation), **when** they need to change asset status,
  **then** they **call this transition logic as the single source of truth** — they do not
  re-implement the map. (This story owns the logic; those epics own the triggers.)

### Engineering Bar checklist
- **Secure:** auto-transitions route through the same authorised, enum-backed, DB-constrained
  `transitionTo()` — no shortcut that bypasses the state machine or the `client_id` scope. The job
  and its assets must share the same `client_id` (verified), so a job can never move another tenant's
  asset. Bulk auto-transitions for multi-asset jobs run inside a DB transaction (hot path).
- **Clean:** the transition map and history/audit writes are **not** duplicated in EPIC-08/EPIC-11 —
  they invoke this service. Use `DB::table()->insert()` in a transaction if many history rows are
  written at once. No dead branches; YAGNI on monitoring (§16 Q4) — only the existing system-actor
  seam from US-06.1 is reused.
- **UX:** the PM override at validation is explicit and clearly labelled (Australian English); the
  default (return to `Active`) is the obvious primary path, overrides are deliberate. (Surface in
  EPIC-11.)
- **No guessing:** verify the affected-asset relation on the job model and the validation hook in
  EPIC-08/EPIC-11 before wiring triggers; confirm the override field names rather than assuming.

### Definition of Done
Job create moves eligible affected assets to `Under Maintenance`; validation returns them to
`Active` unless PM override; all routed through the single `transitionTo()` service with US-06.2
auditing; same-tenant guard enforced; EPIC-08/EPIC-11 documented as callers, not re-implementers;
transaction-safe for multi-asset jobs; tests cover create, validate and override paths. Remains
**Blocked** until EPIC-08 and EPIC-11 land.
