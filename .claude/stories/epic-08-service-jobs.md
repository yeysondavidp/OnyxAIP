# EPIC-08 — Service Job Management

> The operational core: the records through which ONYX documents what was done, to which assets, at
> which store, with evidence. Built on the security spine (EPIC-01) and the asset registry
> (EPIC-04/06). Every state transition is a key audited event (US-00.5). The full SRA §5 lives here.

Related: SRA §5 (5.1–5.5), §16 Q5 (campaign client scope). ADR-001 (technician contract), US-00.4
(scoping), US-00.5 (audit), US-01.3 (policies). Depends on EPIC-03 (stores), EPIC-04 (assets),
EPIC-06 (asset auto-transitions). Sprint: 5.

---

## US-08.1 — CRUD a service job

**As** Yeis (PM)
**I want** to create, view, edit and cancel a service job with all its core fields
**So that** every field visit is a single, trustworthy record scoped to one client and store
(SRA §5.1).

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-00.4, US-01.3, EPIC-03, EPIC-04 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the new-job form, **when** a PM saves a valid job, **then** it persists with a
  **unique** `JobReference`, `JobName`, `JobDescription`, `JobType` (enum: Routine Maintenance,
  Fault Repair, New Installation, Deinstall, Survey, Other), and an initial `JobStatus` of `Draft`
  (SRA §5.1, §5.3).
- **Given** a chosen store, **when** the job is created, **then** `ClientID` and `StoreID` are set
  from the authorised context (never trusted from request input) and `JobTimezone` is **derived
  from the store's `StoreTimezone`** (SRA §3.2, §5.1) — the PM cannot set a mismatched client/store.
- **Given** a `ScheduledDate` + `ScheduledTime` entered in the store/job timezone, **when** stored,
  **then** they are persisted as **UTC** and displayed back in the job timezone (SRA §2.3, §5.1).
- **Given** the form, **when** the PM picks an `EarlyStartWindow`, **then** it is constrained to the
  enum (Anytime, 30 min, 1 hr, 2 hr, 4 hr) and `ClientEmail` / `ClientName` are accepted as
  **nullable** optional fields (SRA §5.1).
- **Given** a duplicate `JobReference`, **when** saving, **then** the request is rejected with an
  inline `@error` on that field **and** the DB unique index rejects it even if validation were
  bypassed (layered guarantee).
- **Given** a job owned by another tenant outside the PM's scope, **when** any read/write is
  attempted, **then** the policy denies and the global scope hides the row (US-00.4, US-01.3).
- **Given** any create / edit / cancel, **when** it succeeds, **then** an async audit entry records
  actor + before/after (US-00.5).

### Engineering Bar checklist
- **Secure:** layered guards — UI hides actions a role lacks + Form Request authorises (`pm` role
  **and** `client_id` scope) and validates every input + Policy enforces invariants + DB has a
  **unique index on `JobReference`**, `client_id`/`store_id` as **NOT NULL FKs** with correct
  on-delete (restrict/cascade decided deliberately, not defaulted), and indexes on `JobStatus`,
  `ScheduledDate` (§14.4). `$fillable` only — `$guarded = []` banned. `ClientID`/`StoreID` set
  server-side, never from the client.
- **Clean:** reuse the US-01.3 policy pattern and US-00.4 scoping trait; one Form Request per
  mutation; no per-field copy-paste; timezone conversion via the documented UTC helper (US-00.1).
- **UX:** clear hierarchy with one primary action (Save); inline `@error` per field; plain,
  non-blaming validation messages; designed loading/empty/error states; ≥44px targets; Australian
  English.
- **No guessing:** verify the `stores` columns (`store_timezone`, `client_id`) and the `JobType` /
  `EarlyStartWindow` / `JobStatus` enum values against SRA §5 and the migration with tinker before
  wiring derivation.

### Definition of Done
CRUD works scoped by role + `client_id`; `JobReference` unique at DB + Request; timezone derived
from store and times stored UTC; nullable client fields handled; audit on every mutation; policy +
scope deny tests pass; happy-path integration test.

---

## US-08.2 — Attach affected assets to a job

**As** Yeis (PM)
**I want** to attach the specific assets a job addresses, chosen from that store's inventory
**So that** each visit links cleanly to the assets it touched and feeds asset service history
(SRA §5.2).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-08.1, EPIC-04, EPIC-06 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a job at a store, **when** the PM selects affected assets, **then** the picker offers
  **only assets registered to that store** (and that client) — assets from other stores/tenants are
  never selectable (SRA §5.2; US-00.4).
- **Given** one or more selected assets, **when** the job is saved, **then** the `AffectedAssetIDs`
  links persist via a pivot, and each link is unique per (job, asset).
- **Given** job creation referencing an asset, **when** the job is created, **then** any affected
  asset currently `Active` or `Faulty` auto-transitions to **Under Maintenance** via the EPIC-06
  asset-transition service (SRA §5.2) — not by ad-hoc writes here.
- **Given** a later validation (US-08.3 → `Validated`), **when** it occurs, **then** a service
  history record is created per affected asset (the EPIC-07/§7 linkage) — this story creates the
  linkage on validation, not before.
- **Given** an attempt to attach an asset not in the store's inventory, **when** submitted, **then**
  it is rejected at the Form Request **and** blocked by the FK/scope at the DB (layered).
- **Given** asset attach/detach, **when** it occurs, **then** it is audited (US-00.5).

### Engineering Bar checklist
- **Secure:** pivot table has **composite unique** (job_id, asset_id), both as **NOT NULL FKs** with
  correct on-delete; membership validated against the store's inventory in the Form Request **and**
  enforced by FK + scope at the DB. Status transitions go through the EPIC-06 service, which holds
  the invariants — never raw status writes here.
- **Clean:** reuse the EPIC-06 transition service and US-00.4 scope; one pivot, no duplicated link
  logic; bulk attach via a single transactional write on the hot path.
- **UX:** asset picker shows AssetCode + type + current status + location notes; empty state when the
  store has no assets; clear that selecting an asset will move it to Under Maintenance; ≥44px rows.
- **No guessing:** confirm the EPIC-06 transition service signature and the asset→store relation
  (`assets.store_id`) before wiring; verify which statuses are eligible to transition.

### Definition of Done
Affected assets attach via a scoped, unique pivot; only in-store assets selectable; create triggers
Active/Faulty→Under Maintenance through EPIC-06; service-history linkage created on validation;
attach/detach audited; cross-store/tenant attach denied by Request + DB; tests cover the transition.

---

## US-08.3 — Job status state machine

**As** Yeis (PM)
**I want** job status to move only through permitted transitions
**So that** a job can never be in an impossible state and every move is recorded (SRA §5.3).

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-08.1, US-00.5 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the status enum, **when** modelled, **then** it is exactly: `Draft`, `Invited`,
  `Accepted`, `In Progress`, `Completed`, `Validated`, `Requires Remediation`, `Cancelled`
  (SRA §5.3) — a typed enum, no magic strings.
- **Given** a current status, **when** a transition is attempted, **then** only the permitted next
  states are allowed (e.g. `Draft → Invited`, `Invited → Accepted`, `Accepted → In Progress`,
  `In Progress → Completed`, `Completed → Validated`, `Completed → Requires Remediation`); an
  illegal transition is **rejected at the model** and cannot be persisted.
- **Given** `Cancelled`, **when** a PM cancels a job, **then** it is a **soft-delete** — removed from
  active job views/board but retained for audit/history (SRA §5.3).
- **Given** any transition, **when** it occurs, **then** an **async audit entry** records the from→to
  status, actor + role, and any reason note — job state transitions are a **key audited event**
  (US-00.5, SRA §14.5).
- **Given** a transition that another epic owns the trigger for (e.g. `Accepted`/`In Progress`/
  `Completed` driven by technicians in EPIC-08.4/EPIC-10), **when** it fires, **then** it routes
  through this same single state-machine guard — no bypass path exists.
- **Given** a non-PM actor, **when** a PM-only transition (e.g. `Validate`, `Cancel`) is attempted,
  **then** the policy denies it (role + scope).

### Engineering Bar checklist
- **Secure:** transitions enforced at the **model** (a single `transitionTo` guard) **and** the
  **DB** (status column constrained to the enum; soft-delete via `deleted_at` NOT-NULLable index,
  excluded from active queries). No code path writes `JobStatus` directly bypassing the guard —
  this is the "guard in controller but not DB" failure mode, avoided by centralising the invariant.
- **Clean:** one enum + one transition map reused by US-08.4 and EPIC-10; no scattered status
  string comparisons; soft-delete via the framework trait.
- **UX:** the job view shows current status clearly; disallowed actions are hidden/disabled in the
  UI (first layer of d-i-d); transition errors are plain-language, not raw exceptions.
- **No guessing:** verify the permitted-transition table against SRA §5.3 (and the Field Job
  Management SRA v1.2 transition rules it mirrors) before encoding; confirm soft-delete column name.

### Definition of Done
Typed status enum; centralised transition guard rejecting illegal moves at model + DB; Cancelled is
soft-deleted and hidden from active views; every transition async-audited with from→to + reason;
PM-only transitions policy-guarded; tests cover one legal and one illegal transition plus the
soft-delete exclusion.

---

## US-08.4 — Multi-technician assignment

**As** Yeis (PM)
**I want** to assign several technicians to one job, each with their own invite lifecycle
**So that** a multi-person visit reflects who accepted, started and submitted (SRA §5.4).

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-08.3, US-01.2 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a job, **when** the PM assigns technicians, **then** they persist via a **many-to-many
  pivot**, each carrying that technician's **independent** lifecycle state: invited → accepted →
  started → completed (SRA §5.4).
- **Given** an assigned technician, **when** the **first** of them starts, **then** the job-level
  status enters **In Progress** (via the US-08.3 guard) (SRA §5.4).
- **Given** all assigned technicians, **when** **all** have submitted, **then** the job reaches
  **Completed**; **given** not all have submitted, **when** the PM **force-completes**, **then** it
  reaches Completed **only with a mandatory reason note** (SRA §5.4).
- **Given** a force-completion, **when** it occurs, **then** the reason note and actor are audited
  (US-00.5).
- **Given** a technician (Sneider, account holder) or guest (Michael, signed URL), **when** they act,
  **then** they can only advance **their own** pivot lifecycle, never another technician's or
  another job's (scope honoured for both authenticated and guest actors; US-01.3, US-01.4).
- **Given** an attempt to assign a non-technician user, **when** submitted, **then** it is rejected
  at the Request and blocked by the FK at the DB.

### Engineering Bar checklist
- **Secure:** pivot has **composite unique** (job_id, user_id) with **NOT NULL FKs** and correct
  on-delete; per-technician lifecycle state constrained to its enum at the DB. A technician can only
  mutate their own pivot row — authorised by role **and** the (technician, job) binding (matches the
  US-01.4 signed-URL scope for guests). Force-complete requires a reason — enforced at Request
  **and** persisted/audited. Job-level status changes only via the US-08.3 guard.
- **Clean:** reuse the US-08.3 transition guard for the In Progress / Completed roll-up; one pivot
  model; the "all submitted?" check is a single reusable method, not duplicated.
- **UX:** assignment list shows each technician's current lifecycle state; force-complete is a
  deliberate secondary action gated behind a required reason field with inline `@error`; ≥44px
  targets; plain-language confirmation.
- **No guessing:** confirm the technician role value (`technician`) and the users↔jobs relation
  before wiring; verify how guest (US-01.4) vs account technician (US-01.2) identity binds to a
  pivot row before enforcing "own row only".

### Definition of Done
Many-to-many assignment with independent per-technician lifecycle; first start → In Progress; all
submit → Completed, or PM force-complete with a required, audited reason note; technicians can only
advance their own row (guest + account); pivot unique + FK-guarded; tests cover first-start roll-up
and force-complete-with-reason.

---

## US-08.5 — Job hierarchy (campaign / sub-job / remediation)

**As** Yeis (PM)
**I want** to model parent campaigns, per-store sub-jobs and at most one remediation child
**So that** national rollouts and post-visit fixes are structured, not ad-hoc (SRA §5.5).

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-08.1, US-08.3 · **Status:** 📋 Ready

> **Decision (ONYX, 2026-06-24, resolving SRA §16 Q5): a Parent Job / campaign is scoped to a
> single client. Campaigns do not span clients.** Rationale: it aligns with the multi-client
> scoping spine (US-00.4) — every job already carries one `client_id`, so the whole tree shares it;
> it keeps client reporting, SLA attribution and `client_id` authorisation unambiguous; and a
> cross-client rollout has no real-world meaning here (a "national Pandora rollout" is one campaign
> for Pandora — a simultaneous Pandora + Dior push is simply **two campaigns**, one per client).
> Practical rule: a campaign's `client_id` is fixed at creation; every sub-job and remediation
> inherits it and **cannot** be moved to another client. This invariant is enforced at Request,
> model and DB.

### Acceptance criteria
- **Given** the hierarchy, **when** modelled, **then** three levels exist (SRA §5.5):
  **Level 0** Parent Job / Campaign (PM-only, unlimited sub-jobs), **Level 1** Sub-Job / Store Visit
  (executed by one or more technicians, **max 1** remediation child), **Level 2** Remediation Sub-Job
  (no children). Depth beyond Level 2 is rejected.
- **Given** the single-client rule, **when** a sub-job is attached to a parent, **then** the
  sub-job's `ClientID` **must equal** the parent's `ClientID` — a cross-client child is rejected at
  the Request **and** by a DB constraint/scope; the child inherits the parent's `client_id` and
  cannot be reassigned to another client.
- **Given** a Level 1 sub-job that already has a remediation child, **when** a second remediation is
  attempted, **then** it is **rejected** (max 1 remediation per sub-job) — enforced at model + DB.
- **Given** a remediation sub-job (Level 2), **when** a child is attempted under it, **then** it is
  **rejected** (no Level 3) — enforced at model + DB.
- **Given** a **standalone job** (single store, no parent), **when** created, **then** it is fully
  supported with `ParentJobID` null (SRA §5.5).
- **Given** only a PM, **when** a Level 0 campaign is created, **then** it is allowed; a technician
  cannot create a parent/campaign (role-guarded).
- **Given** any parent/child link change, **when** it occurs, **then** it is audited (US-00.5).

### Engineering Bar checklist
- **Secure:** `ParentJobID` is a **self-referencing nullable FK** with correct on-delete; level/depth
  and "max 1 remediation" invariants enforced at the **model** **and** at the **DB** (e.g. a
  generated/checked level column with a partial unique guard on remediation children) — not in the
  controller alone. Single-client invariant: child `client_id` must match the parent's, validated at
  Request + enforced by scope/constraint at DB. Campaign creation is PM-only.
- **Clean:** one hierarchy concern (parent/children + level) reused; no duplicated depth checks;
  reuse US-08.3 status guard for sub-job/remediation lifecycle.
- **UX:** the campaign view shows its sub-jobs and their states; "add remediation" is hidden/disabled
  once one exists; plain-language explanation when a depth/limit rule blocks an action; ≥44px targets.
- **No guessing:** verify the self-FK relation name and the level-tracking approach before encoding;
  confirm the child-inherits-parent-`client_id` behaviour with a test.

### Definition of Done
Three-level hierarchy with PM-only campaigns, max-1-remediation and no-Level-3 enforced at model +
DB; standalone jobs supported; single-client parent invariant enforced (child inherits parent's
`client_id`, cross-client child rejected); hierarchy changes audited; tests cover max-1-remediation,
no-Level-3, standalone, and cross-client rejection.

---

## US-08.6 — PM attachments on a job

**As** Yeis (PM)
**I want** to attach briefs, diagrams and reference photos to a job
**So that** technicians have the context they need, served safely and never publicly (SRA §5.1,
§14.3).

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-08.1, US-01.5 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the job form, **when** the PM uploads `PMAttachments` (briefs/diagrams/photos), **then**
  each file is validated against a **MIME-type and extension allow-list** and a **maximum size**
  before storage (SRA §14.3); a disallowed type is rejected with a plain `@error`.
- **Given** a valid upload, **when** stored, **then** it is written **only** via the Laravel Storage
  abstraction to the `app_storage` volume (never a public path) (ADR-002).
- **Given** a stored attachment, **when** a PM or an assigned technician views it, **then** it is
  served **only via a signed, expiring URL** validated server-side — there is **no public file
  route** (SRA §14.3; US-01.5).
- **Given** an unauthorised actor or another tenant, **when** they request an attachment URL, **then**
  access is denied (role + `client_id` scope; signed-URL check).
- **Given** an attachment record, **when** persisted, **then** it links to the job via a **NOT NULL
  FK** and stores the path + original filename + MIME, never trusting the client-supplied MIME alone.
- **Given** upload or deletion of an attachment, **when** it occurs, **then** it is audited
  (US-00.5).

### Engineering Bar checklist
- **Secure:** allow-list validation by **both** extension and detected MIME (don't trust the
  client-sent content-type); size cap; Storage abstraction only; signed-URL serving with expiry, no
  public route; attachment FK NOT NULL with correct on-delete; scope + policy on every read. This is
  exactly the §14.3 file-handling contract — apply it fully, don't shortcut.
- **Clean:** reuse the US-01.4/US-01.5 signed-URL service and the Storage abstraction; one upload
  handler, no duplicated validation; correct MIME set on serve.
- **UX:** drag/drop or file picker with progress feedback (non-blocking); per-file inline `@error`;
  thumbnail/name list with remove; empty state when no attachments; ≥44px controls; en-AU copy.
- **No guessing:** confirm the allow-list (which MIME/extensions) and size cap against §14.3 before
  coding; verify the Storage disk name for `app_storage`.

### Definition of Done
Attachments validated by MIME + extension + size; stored via Storage abstraction on `app_storage`;
served only via signed expiring URLs (no public route); reads scoped by role + client; FK-linked and
audited; tests cover a rejected disallowed type and a denied cross-tenant fetch.

---

## US-08.7 — Job board / list with filters

**As** Yeis (PM)
**I want** a filterable, paginated board of jobs across my clients
**So that** I can find, triage and act on jobs fast without hand-assembling lists (persona: Yeis).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-08.1, US-08.3, US-00.4 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the job board, **when** a PM opens it, **then** it lists jobs **scoped to their permitted
  `client_id`(s)** — no other tenant's jobs ever appear (US-00.4) — and `Cancelled` (soft-deleted)
  jobs are excluded from the active board (US-08.3).
- **Given** the filters, **when** applied, **then** the PM can filter by **status**, **client**,
  **state** (via the store), and **SLA breach** flag; filters combine and are reflected in the URL so
  a view is shareable/bookmarkable (SRA §5.1 `SLABreached`, §10).
- **Given** a large result set, **when** displayed, **then** it is **paginated** and loads within the
  §14.1 dashboard budget; queries hit the indexes from US-08.1 (`JobStatus`, `ScheduledDate`,
  `client_id`, `store_id`).
- **Given** no matching jobs, **when** filters return nothing, **then** a designed **empty state**
  with a clear next action shows (not a blank table).
- **Given** data is loading or a query errors, **when** it happens, **then** designed **loading** and
  **error** states show (shared components from US-00.3).
- **Given** a technician, **when** they reach this route, **then** the board is **PM-only** —
  denied by middleware + policy.

### Engineering Bar checklist
- **Secure:** PM-only via role middleware **and** policy; results constrained by the US-00.4 global
  scope (defence in depth — scoping at the model, not just a WHERE in the controller); filter inputs
  validated/whitelisted (no raw SQL from query params — Laravel query builder, no string
  interpolation).
- **Clean:** reuse the scoping trait and shared state components; one filter component; index-backed
  queries (no N+1 — eager-load store/client); pagination via the framework, not hand-rolled.
- **UX:** dense-but-legible desktop table (Yeis's need); obvious filter controls; persisted filters
  in the URL; clear empty/loading/error states; sortable by scheduled date; en-AU copy; ≥44px
  controls.
- **No guessing:** confirm the `SLABreached` computed source (§10) and the store→state relation
  before wiring the SLA and state filters; verify indexes exist before claiming the perf budget.

### Definition of Done
PM-only, `client_id`-scoped, paginated job board excluding cancelled jobs; combinable status / client
/ state / SLA-breach filters reflected in the URL; index-backed within the §14.1 budget; designed
empty / loading / error states; cross-tenant rows never shown (tested); technician access denied
(tested).
