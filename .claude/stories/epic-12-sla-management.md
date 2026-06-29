# EPIC-12 — SLA Management

> SLA profiles per client, a business-hours-aware clock that starts on fault jobs, and breach-risk
> flags surfaced everywhere a PM looks (SRA §10). Late-discovered breaches are one of Yeis's core
> pains (personas) — this epic turns the SLA into a live, visible signal.

Related: ADR-002 (scheduler container runs the SLA clock), US-00.4 (client scoping), US-00.5 (audit),
EPIC-02 (clients), EPIC-08 (fault jobs), EPIC-13 (notifications). Sprint: 9.

---

## US-12.1 — CRUD SLA profiles assignable to clients

**As** Yeis (PM)
**I want** to create and maintain SLA profiles and assign one to each client
**So that** every client's response and resolution targets are defined once and consistently applied
(SRA §10.1).

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-01.3, EPIC-02 (clients) · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the SLA profiles screen, **when** a PM creates a profile, **then** they capture
  `AcknowledgementWindow`, `OnSiteResponseMetro`, `OnSiteResponseRegional`, `ResolutionTarget` and
  `MonitoringCoverage` (SRA §10.1), each validated server-side.
- **Given** an existing profile, **when** a PM edits or deactivates it, **then** changes persist and
  the action is audited (US-00.5).
- **Given** a client record, **when** a PM assigns an SLA profile (the linkage consumed by US-02.2),
  **then** the client's `sla_profile_id` is set and visible on the client dashboard.
- **Given** a non-PM actor, **when** they attempt any SLA-profile action, **then** the policy denies
  it (PM-only; defence in depth — UI hidden, Request authorised, Policy enforced).
- **Given** a profile referenced by a client, **when** a PM tries to delete it, **then** deletion is
  blocked (or the profile is soft-deactivated) so no client is left without a profile — enforced at
  the DB via the FK on-delete rule.

### Engineering Bar checklist
- **Secure:** PM-only via role policy; `$fillable` only (never `$guarded = []`); validate every field
  server-side, never trust the client; `client.sla_profile_id` is a **FK** with a deliberate
  on-delete rule (restrict/set null), and the profile table carries NOT NULL on required windows.
  Audited (US-00.5).
- **Clean:** one `SlaProfile` model + one policy; reuse the EPIC-02 client form patterns; no
  per-window bespoke code — windows share a typed value representation.
- **UX:** clear hierarchy with one primary action (Save); inline `@error` per field; plain-language
  help text explaining each window; designed loading/empty/error states; Australian English; 44px
  targets.
- **No guessing:** confirm the §10.1 field set and the metro/regional distinction before modelling;
  verify the `clients` relation/column name with tinker before wiring the FK.

### Definition of Done
SLA profile CRUD works PM-only; assignable to clients (linkage ready for US-02.2); fields validated;
FK + NOT NULL + on-delete enforced at the DB; actions audited; cross-role deny tested; happy-path
integration test.

---

## US-12.2 — SLA clock starts on fault jobs (business-hours, AU holidays)

**As** Yeis (PM)
**I want** the SLA clock to start automatically when a fault-type job is created and to count only
business hours
**So that** response and resolution targets reflect real working time, not weekends or public
holidays (SRA §10.2, §17).

**Estimate:** 8 · **Priority:** P1 · **Depends on:** US-12.1, EPIC-08 (fault jobs), US-00.4 ·
**Status:** 📋 Ready

### Acceptance criteria
- **Given** a fault-type job, **when** it is created (EPIC-08), **then** the SLA clock starts: the
  job records its start instant (UTC) and the applicable target windows are resolved from the
  client's assigned profile (US-12.1).
- **Given** a non-fault job (e.g. routine maintenance, survey), **when** created, **then** no SLA
  clock starts (clock is fault-only per §10.2).
- **Given** elapsed-time computation, **when** the clock advances, **then** it counts **business
  hours only** — excluding weekends and **state-specific public holidays** for the store's state
  (SRA §10.2).
- **Given** a store in a given state, **when** business hours are computed, **then** the AU
  public-holiday data is sourced **per state** from a configured provider (open API or static
  calendar per §17), and the provider is **swappable behind an interface** (config-only swap).
- **Given** the holiday source is unavailable, **when** the clock computes, **then** the failure is
  handled gracefully (cached/fallback calendar, logged) without crashing job creation.
- **Given** the scheduler container (ADR-002), **when** time passes, **then** elapsed/remaining SLA
  time is recomputed on a schedule (no per-request recompute on hot paths).

### Engineering Bar checklist
- **Secure:** SLA fields are computed/owned server-side, never settable from request input; job →
  client → profile lookups respect `client_id` scope (US-00.4); persisted SLA columns are NOT NULL
  where required and indexed for the breach-risk queries that follow.
- **Clean:** one `BusinessHoursCalculator` + a `PublicHolidayProvider` interface (state-keyed)
  reused by US-12.3 and reporting; no duplicated holiday logic; scheduled recompute runs in the
  scheduler container per ADR-002, not ad hoc.
- **UX:** N/A directly (surfaced in US-12.3) — but store timezone/state must be resolved correctly
  so displayed windows are accurate.
- **No guessing:** confirm the per-state AU holiday source (§17) and its data shape before coding;
  verify the store→state relation and store timezone column with tinker; confirm business-hours
  definition (start/end hours) with ONYX rather than assuming.

### Definition of Done
Fault jobs start an SLA clock; non-fault jobs do not; business-hours computation excludes weekends +
state-specific AU holidays; holiday provider is interface-backed and swappable with a fallback;
recompute runs via the scheduler; unit tests cover weekend/holiday edge cases per state.

---

## US-12.3 — SLA breach-risk flags surfaced and audited

**As** Yeis (PM)
**I want** jobs flagged when they approach or exceed their SLA window, with the status shown wherever
I work
**So that** I act before a breach instead of discovering it too late (SRA §10.2, personas).

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-12.2, US-00.5 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** an open fault job with a running clock, **when** elapsed business-hours time crosses a
  **configurable threshold** (e.g. 80% of the window elapsed), **then** the job is flagged
  **at-risk**; **when** the window is exceeded, **then** it is flagged **breached** (`SLABreached`
  computed per §5.1).
- **Given** the threshold, **when** a PM changes it in settings, **then** the new threshold applies
  without code changes (config-driven).
- **Given** SLA status, **when** a PM views the **job list**, the **store dashboard** and the
  **client dashboard**, **then** a clear SLA indicator (on-track / at-risk / breached) is shown on
  each (§10.2) using consistent, plain-language labels and accessible colour + text (not colour
  alone).
- **Given** a breach event, **when** it occurs, **then** it is **audited** (US-00.5, §14.5) and emits
  the signal that feeds notifications (EPIC-13) — at-risk and breach are distinct events.
- **Given** a job that is validated/closed, **when** the clock stops, **then** no further at-risk or
  breach flags are raised for it.

### Engineering Bar checklist
- **Secure:** breach computation server-side only; SLA status on each dashboard respects role +
  `client_id` scope (US-00.4) so no tenant sees another's jobs; breach events written append-only
  via the audit foundation (US-00.5); threshold read from trusted config, not request input.
- **Clean:** reuse the `BusinessHoursCalculator` from US-12.2; one breach-evaluation service feeding
  all three surfaces and the EPIC-13 notification trigger; one shared SLA-status badge component; no
  duplicated threshold logic.
- **UX:** clear SLA status indicators with plain-language labels; colour **and** text/icon for
  accessibility; designed loading/empty/error states on each dashboard; Australian English.
- **No guessing:** confirm the default at-risk threshold with ONYX; verify the job/store/client
  dashboard query relations with tinker before adding the indicator.

### Definition of Done
At-risk and breach flags computed against business-hours windows; threshold configurable; SLA
indicator shown on job list + store dashboard + client dashboard; breach events audited and emit the
EPIC-13 notification signal; stopped clocks raise no further flags; tests cover threshold crossing
and breach.
