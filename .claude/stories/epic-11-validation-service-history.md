# EPIC-11 — Job Validation & Service History

> Where a completed visit becomes the trusted record. The PM reviews completed work, validates it
> (driving asset auto-transitions, §5.2) or flags remediation (§5.3), and every validated job
> writes an **append-only** per-asset service history entry (§7) — enforced no-update/no-delete at
> the application level, like the audit log. This is the closing of the loop that makes the registry
> a single source of truth.

Related: ADR-001 (frontend), ADR-002 (deployment), US-00.5 (audit append-only), US-01.3 (role +
`client_id` policies), US-06.1/US-06.2/US-06.3 (status transitions + auto-transitions), EPIC-08
(jobs + hierarchy), EPIC-10 (completed technician visits). Sprint: 8.

---

## US-11.1 — PM validates a completed job (asset auto-transitions + service history write)

**As** Yeis (PM)
**I want** to review a completed job and mark it Validated, returning each affected asset to its
correct status and capturing an immutable service history record per asset
**So that** approved work updates the registry automatically and leaves a trustworthy, attributable
trail (SRA §5.2, §5.3, §7).

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-01.3, US-06.2, US-06.3, EPIC-08, EPIC-10 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a job in `Completed` status, **when** Yeis opens the validation surface, **then** he sees
  the captured evidence (before/after photos, completion notes, per-asset outcomes from Screen 4, GPS
  + start/end times) and a clear primary **Validate** action.
- **Given** a `Completed` job, **when** Yeis validates it, **then** the job transitions
  `Completed → Validated` per §5.3 — and only from `Completed` (validating any other status is
  rejected at model/service level, not just hidden in the UI).
- **Given** each affected asset currently `Under Maintenance`, **when** validation commits, **then**
  the asset returns to `Active` **unless** the PM overrides per-asset (e.g. `Still Faulty`,
  `Decommissioned`, `Replaced` per §6 Screen 4 / §7) — the override wins — and every change routes
  through the single `transitionTo()` service (US-06.1/US-06.3), never a parallel write.
- **Given** validation commits, **when** it succeeds, **then** **one append-only service history
  record per affected asset** (§7) is written capturing: `asset_id`, `service_job_id`, service date,
  technician id(s), job type, **status before** (at job creation), **status after** (post-validation
  outcome), technician notes (per-asset outcome from Screen 4), before-photo URLs and after-photo URLs.
- **Given** a multi-asset job, **when** validation runs, **then** all status transitions and all
  history rows are written inside **one DB transaction** (hot path; bulk insert via
  `DB::table()->insert()`), so a partial failure leaves no half-validated job.
- **Given** validation commits, **when** it completes, **then** the action is **audit-logged**
  (async, US-00.5) with actor (PM id + role), action type, target job + affected assets, before/after
  job status and UTC timestamp.

### Engineering Bar checklist
- **Secure:** authorise validation against the actor's role (`pm`) **and** the job's `client_id`
  scope (US-01.3) — a PM cannot validate another tenant's job; the job and its affected assets must
  share the same `client_id` (verified, per US-06.3). Job status, asset status and history are
  **never** mass-assignable from request input (`$fillable` only); the `Completed → Validated`
  transition and per-asset overrides are validated server-side. DB enforces the guarantees:
  `service_history` has NOT NULL `asset_id`/`service_job_id` with FKs (correct on-delete), an index on
  `asset_id` (§14.4), and the job status column is enum-backed — defence in depth, not "guard in the
  controller but not the DB".
- **Clean:** reuse `transitionTo()` (US-06.1) and the US-06.3 auto-transition logic — do **not**
  re-implement the §4.5 map here; reuse the US-00.5 auditing concern. One validation service is the
  single entry point; bulk history insert in a transaction on the hot path. Comments in English.
- **UX:** PM **desktop** validation UI (1280px+) with a clear hierarchy and an obvious primary
  **Validate** action; the default (return to `Active`) is the obvious path and per-asset overrides
  are deliberate and clearly labelled; inline `@error` validation on override/notes; designed
  loading/empty/error states; plain-language, non-blaming messages; Australian English.
- **No guessing:** verify the job model's affected-asset relation, the Screen 4 outcome/notes field
  names and the photo-URL columns by reading EPIC-08/EPIC-10 + tinker before wiring — do not assume
  field names or the override enum values.

### Definition of Done
Validation moves `Completed → Validated`; affected assets return to `Active` unless PM per-asset
override; one append-only service history record per affected asset with all §7 fields; all writes in
one transaction; async audit entry written; authorised by role **and** `client_id`; DB constraints
present; tests cover validate, override and cross-tenant deny paths; Pint + Larastan clean.

---

## US-11.2 — PM flags "Requires Remediation" and spawns a remediation sub-job

**As** Yeis (PM)
**I want** to flag a completed job as needing more work and have a remediation sub-job created with
the original context carried forward
**So that** incomplete or substandard visits are tracked and re-dispatched without losing the trail
(SRA §5.3, §5.5 hierarchy).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-11.1, EPIC-08 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a job in `Completed` status, **when** Yeis flags it, **then** it transitions
  `Completed → Requires Remediation` per §5.3 — only from `Completed`, rejected from any other status
  at model/service level.
- **Given** a flag with a required reason, **when** it commits, **then** a **remediation sub-job** is
  created as a child of the flagged job per the EPIC-08 / §5.5 hierarchy, respecting the **max depth
  of level 2** (a remediation sub-job has no children) and **max 1 remediation sub-job** per parent —
  attempting a second is rejected.
- **Given** the remediation sub-job, **when** created, **then** it carries context forward: the same
  client, store, affected assets and a description referencing the parent job + the flag reason, and
  is created in `Draft` for the PM to dispatch (it does not auto-invite).
- **Given** the flag, **when** it commits, **then** the affected assets' status is governed by the
  state machine (US-06.x) — flagging does not silently return assets to `Active`; they remain under
  the open remediation path until that sub-job is itself validated.
- **Given** the flag, **when** it completes, **then** the transition and sub-job creation are
  **audit-logged** (async, US-00.5) with actor, parent + child job ids, reason and UTC timestamp.

### Engineering Bar checklist
- **Secure:** authorise by role (`pm`) **and** the job's `client_id` scope (US-01.3); the remediation
  sub-job inherits the **same `client_id`** as the parent (set from the authorised context, never
  trusted from request input) so remediation can never cross tenants. Depth/child-count invariants
  enforced at model + DB (FK `parent_job_id`, and the level-2 / single-remediation rule guarded in the
  model, not only the UI). Reason is validated and length-bounded.
- **Clean:** reuse the EPIC-08 job-creation + hierarchy logic to spawn the sub-job — do **not**
  re-implement parenting; reuse the US-00.5 audit concern and the US-11.1 validation service's
  transition entry point. No duplicated hierarchy rules.
- **UX:** PM desktop surface; flagging is a clearly secondary action distinct from the primary
  **Validate**; a required, plain-language reason field with inline `@error`; on success, confirm the
  sub-job was created and link to it; designed loading/error states; Australian English.
- **No guessing:** verify the EPIC-08 hierarchy fields (`parent_job_id`, depth/level handling) and the
  affected-asset relation before wiring; confirm the `Requires Remediation` enum value matches §5.3
  exactly rather than assuming the label.

### Definition of Done
`Completed → Requires Remediation` transition enforced; a single remediation sub-job created at the
correct hierarchy level carrying client/store/assets/context forward in `Draft`; depth + single-child
invariants enforced at model + DB; required reason validated; async audit written; same-tenant
inheritance verified; tests cover the flag, the depth/second-remediation rejection and cross-tenant
deny; Pint + Larastan clean.

---

## US-11.3 — Append-only per-asset service history (model + asset-detail log)

**As** Yeis (PM)
**I want** each asset's service history to be immutable and shown as a chronological log on the asset
detail page
**So that** the per-asset record is a tamper-resistant single source of truth I can trust and show
clients (SRA §7).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-00.5, US-11.1, EPIC-04 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** the `service_history` model, **when** any code attempts to **update or delete** a record,
  **then** it is **prevented at the application level** (append-only, exactly like the audit log,
  US-00.5) — proven by a test; only create is permitted.
- **Given** a service history record, **when** stored, **then** it carries every §7 field (`asset_id`,
  `service_job_id`, service date, technician id(s), job type, status before, status after, technician
  notes, before-photo URLs, after-photo URLs).
- **Given** an asset detail page (EPIC-04), **when** Yeis opens it, **then** a **chronological service
  log** lists each entry newest-first with date, job reference, job type, status before → after,
  technician(s) and access to the per-asset notes and before/after photos.
- **Given** an asset with no service history, **when** the page renders, **then** a designed **empty
  state** is shown (not a blank table); while loading, a loading state; on failure, an error state.
- **Given** photos referenced in a history entry, **when** Yeis views them, **then** they are served
  only via **signed URLs** (no public file route, ADR-002/§14.3) and access is authorised by the
  asset's `client_id` scope.

### Engineering Bar checklist
- **Secure (the heart of this story):** append-only enforced in the **model** (block `update`/`delete`,
  same pattern as US-00.5) **and** reinforced at the **DB** (NOT NULL `asset_id`/`service_job_id` with
  FKs and an index on `asset_id`, §14.4) — defence in depth, not application-only. Reads authorised by
  role **and** the asset's `client_id` scope (US-01.3); photos via signed URL only; no secrets leaked
  in notes rendering (escape user-entered technician notes — XSS).
- **Clean:** one `service_history` model + concern reused by US-11.1 (writer) and EPIC-14 (reporting);
  the append-only guard reuses the US-00.5 pattern rather than a bespoke one; no duplicated query logic
  between the asset-detail log and the store view (US-11.4).
- **UX:** clear chronological hierarchy; the log is scannable (date, job type, status delta the most
  prominent); designed empty/loading/error states; Australian English; accessible (keyboard/focus,
  44px targets where interactive).
- **No guessing:** verify the asset relation and the photo-URL storage shape (single vs collection)
  against US-11.1's writer + the migration before rendering — do not assume how URLs are stored.

### Definition of Done
`service_history` is append-only at model + DB (update/delete blocked, proven by test); all §7 fields
present; asset detail shows a newest-first chronological log with designed empty/loading/error states;
photos via signed URL only; reads scoped by `client_id`; notes escaped; reused by US-11.4 + EPIC-14;
Pint + Larastan clean.

---

## US-11.4 — Per-store aggregated service history view

**As** Yeis (PM)
**I want** a chronological service history aggregated across all of a store's assets
**So that** I can see everything done at a store over time and feed it into client reporting (SRA §7,
§8 store dashboard; feeds EPIC-14).

**Estimate:** 3 · **Priority:** P1 · **Depends on:** US-11.3, EPIC-03 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a store, **when** Yeis opens its service history view, **then** he sees a single
  **chronological log aggregating service history across all assets at that store**, newest-first,
  each row showing date, asset code, job reference, job type, status before → after and technician(s).
- **Given** the aggregated view, **when** displayed, **then** it can be filtered (at minimum by asset
  type and by date range) so a busy store stays scannable.
- **Given** a store with no service history yet, **when** the view renders, **then** a designed empty
  state is shown; while loading, a loading state; on failure, an error state.
- **Given** the underlying data, **when** queried for a store with many assets and entries, **then** it
  is **paginated and indexed** (no unbounded query) and loads within the PM dashboard budget (§14.1).
- **Given** EPIC-14 reporting, **when** the per-store service history report is built, **then** it
  consumes **this same** aggregation (one query path, not a reporting-only re-implementation).

### Engineering Bar checklist
- **Secure:** authorise the store view by role **and** the store's `client_id` scope (US-01.3) — a PM
  only sees ONYX clients' stores and never another tenant's; the aggregation respects the same
  append-only, read-only history (no mutation path here); photos still via signed URL only.
- **Clean:** reuse the US-11.3 `service_history` model + query building blocks — the store view and
  EPIC-14's per-store report share one aggregation path (DRY); no copy-pasted query logic. Use proper
  eager-loading/indexing to avoid N+1 on the asset relation.
- **UX:** PM desktop; clear chronological hierarchy and obvious filters; designed empty/loading/error
  states; plain-language labels; pagination controls accessible; Australian English.
- **No guessing:** verify the store → assets → service_history relation chain and the existing
  store-dashboard surface (EPIC-03/§8) by reading them before wiring the aggregation — do not assume
  the relation names.

### Definition of Done
Per-store aggregated chronological service history with asset-type + date-range filters; designed
empty/loading/error states; paginated + indexed within the §14.1 budget; scoped by store `client_id`;
shares one aggregation path with EPIC-14; photos via signed URL; tests cover the aggregation, a filter
and cross-tenant deny; Pint + Larastan clean.
